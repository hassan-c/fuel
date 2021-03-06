<?php
/**
 * Fuel
 *
 * Fuel is a fast, lightweight, community driven PHP5 framework.
 *
 * @package		Fuel
 * @version		1.0
 * @author		Fuel Development Team
 * @license		MIT License
 * @copyright	2010 Dan Horrigan
 * @link		http://fuelphp.com
 */

namespace Fuel;

use Fuel\Application as App;

/**
 * The core of the framework.
 *
 * @package		Fuel
 * @subpackage	Core
 * @category	Core
 */
class Fuel {

	public static $initialized = false;

	public static $env;

	public static $bm = true;

	public static $locale;

	protected static $_paths = array();

	protected static $packages = array();

	final private function __construct() { }

	/**
	 * Initializes the framework.  This can only be called once.
	 *
	 * @access	public
	 * @return	void
	 */
	public static function init($autoloaders)
	{
		if (static::$initialized)
		{
			throw new Exception("You can't initialize Fuel more than once.");
		}

		static::$_paths = array(APPPATH, COREPATH);

		// Add the core and optional application loader to the packages array
		static::$packages = $autoloaders;

		register_shutdown_function('Fuel\\Application\\Error::shutdown_handler');
		set_exception_handler('Fuel\\Application\\Error::exception_handler');
		set_error_handler('Fuel\\Application\\Error::error_handler');

		// Start up output buffering
		ob_start();

		App\Config::load('config');

		static::$bm = App\Config::get('benchmarking', true);
		static::$env = App\Config::get('environment');
		static::$locale = App\Config::get('locale');

		App\Route::$routes = App\Config::load('routes', true);

		//Load in the packages
		foreach (App\Config::get('packages', array()) as $package)
		{
			static::add_package($package);
		}

		if (App\Config::get('base_url') === false)
		{
			if (isset($_SERVER['SCRIPT_NAME']))
			{
				$base_url = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));

				// Add a slash if it is missing
				substr($base_url, -1, 1) == '/' OR $base_url .= '/';

				App\Config::set('base_url', $base_url);
			}
		}

		// Set some server options
		setlocale(LC_ALL, static::$locale);

		// Set default timezone when given in config
		if (($timezone = App\Config::get('default_timezone', null)) != null)
		{
			date_default_timezone_set($timezone);
		}
		// ... or set it to UTC when none was set
		elseif ( ! ini_get('date.timezone'))
		{
			date_default_timezone_set('UTC');
		}

		// Clean input
		App\Security::clean_input();

		// Always load classes, config & language set in always_load.php config
		static::always_load();

		static::$initialized = true;
	}
	
	/**
	 * Cleans up Fuel execution, ends the output buffering, and outputs the
	 * buffer contents.
	 * 
	 * @access	public
	 * @return	void
	 */
	public static function finish()
	{
		// Grab the output buffer
		$output = ob_get_clean();

		$bm = App\Benchmark::app_total();

		// TODO: There is probably a better way of doing this, but this works for now.
		$output = \str_replace(
				array('{exec_time}', '{mem_usage}'),
				array(round($bm[0], 4), round($bm[1] / pow(1024, 2), 3)),
				$output
		);


		// Send the buffer to the browser.
		echo $output;
	}

	/**
	 * Finds a file in the given directory.  It allows for a cascading filesystem.
	 *
	 * @access	public
	 * @param	string	The directory to look in.
	 * @param	string	The name of the file
	 * @param	string	The file extension
	 * @return	string	The path to the file
	 */
	public static function find_file($directory, $file, $ext = '.php')
	{
		$path = $directory.DS.strtolower($file).$ext;

		$found = false;
		foreach (static::$_paths as $dir)
		{
			if (is_file($dir.$path))
			{
				$found = $dir.$path;
				break;
			}
		}
		return $found;
	}

	/**
	 * Loading in the given file
	 *
	 * @access	public
	 * @param	string	The path to the file
	 * @return	mixed	The results of the include
	 */
	public static function load($file)
	{
		return include $file;
	}

	/**
	 * Adds a package or multiple packages to the stack.
	 * 
	 * Examples:
	 * 
	 * static::add_package('foo');
	 * static::add_package(array('foo' => PKGPATH.'bar/foo/'));
	 * 
	 * @access	public
	 * @param	array|string	the package name or array of packages
	 * @return	void
	 */
	public static function add_package($package)
	{
		if ( ! is_array($package))
		{
			$package = array($package => PKGPATH.$package.DS);
		}
		foreach ($package as $name => $path)
		{
			if (array_key_exists($name, static::$packages))
			{
				continue;
			}
			static::$packages[$name] = static::load($path.'autoload.php');
		}

		// Put the APP autoloader back on top
		spl_autoload_unregister(array(static::$packages['app'], 'load'));
		spl_autoload_register(array(static::$packages['app'], 'load'), true, true);
	}

	/**
	 * Removes a package from the stack.
	 * 
	 * @access	public
	 * @param	string	the package name
	 * @return	void
	 */
	public static function remove_package($name)
	{
		spl_autoload_unregister(array(static::$packages[$name], 'load'));
		unset(static::$packages[$name]);
	}

	/**
	 * Add module
	 *
	 * Registers a given module as a class prefix and returns the path to the
	 * module. Won't register twice, will just return the path on a second call.
	 *
	 * @param	string	module name (lowercase prefix without underscore)
	 */
	public static function add_module($name)
	{
		// First attempt registered prefixes
		$mod_path = static::$packages['app']->prefix_path(ucfirst($name).'_');
		if ($mod_path !== false)
		{
			return $mod_path;
		}

		// Or try registered module paths
		foreach (App\Config::get('module_paths', array()) as $path)
		{
			if (is_dir($mod_path = $path.strtolower($name).DS))
			{
				// Load module and end search
				App\Fuel::$packages['app']->add_prefix(ucfirst($name).'_', $mod_path);
				return $mod_path;
			}
		}

		// not found
		return false;
	}

	/**
	 * Always load classes, config & language files set in always_load.php config
	 */
	public static function always_load($array = null)
	{
		$array = is_null($array) ? App\Fuel::load(APPPATH.'config'.DS.'always_load.php') : $array;

		foreach ($array['classes'] as $class)
		{
			if ( ! class_exists($class))
			{
				throw new Exception('Always load class does not exist.');
			}
		}

		/**
		 * Config and Lang must be either just the filename, example: array(filename)
		 * or the filename as key and the group as value, example: array(filename => some_group)
		 */

		foreach ($array['config'] as $config => $config_group)
		{
			App\Config::load((is_int($config) ? $config_group : $config), (is_int($config) ? true : $config_group));
		}

		foreach ($array['language'] as $lang => $lang_group)
		{
			App\Lang::load((is_int($lang) ? $lang_group : $lang), (is_int($lang) ? true : $lang_group));
		}
	}

	/**
	 * Cleans a file path so that it does not contain absolute file paths.
	 * 
	 * @access	public
	 * @param	string	the filepath
	 * @return	string
	 */
	public static function clean_path($path)
	{
		static $search = array(APPPATH, COREPATH, DOCROOT, '\\');
		static $replace = array('APPPATH/', 'COREPATH/', 'DOCROOT/', '/');
		return str_replace($search, $replace, $path);
	}
}

/* End of file core.php */
