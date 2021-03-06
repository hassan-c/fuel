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

class Route {
	
	public static $routes = array();

	/**
	 * Attemptes to find the correct route for the given URI
	 *
	 * @access	public
	 * @param	object	The URI object
	 * @return	array
	 */
	public static function parse($uri)
	{
		// This handles the default route
		if ($uri->uri == '')
		{
			if ( ! isset(static::$routes['#']) || static::$routes['#'] == '')
			{
				// TODO: write logic to deal with missing default route.
				return FALSE;
			}
			else
			{
				return static::parse_match(static::$routes['#']);
			}
		}

		foreach (static::$routes as $search => $route)
		{
			$search = str_replace(array(':any', ':segment'), array('.+', '[^/]+'), $search);
			$search = preg_replace('|:([a-z\_]+)|uD', '(?P<$1>.+)', $search);

			if (preg_match('|'.$search.'|uD', $uri->uri, $params) != false)
			{
				$route = preg_replace('|'.$search.'|uD', $route, $uri->uri);

				return static::parse_match($route, $params);
			}
		}
		
		return static::parse_match($uri->uri);
	}

	/**
	 * Parse module routes
	 *
	 * This first adds the given routes to the current loaded routes and then
	 * reparses the given uri.
	 *
	 * @param	string	current module name
	 * @param	array	new routes
	 * @param	string	uri to reparse
	 * @return	array	parsed routing info
	 */
	public static function parse_module($module, Array $routes, $current_uri)
	{
		// Load module routes and add to router
		foreach ($routes as $uri => $route)
		{
			$prefix = in_array($uri, array('404')) ? '' : $module.'/';
			static::$routes[$prefix.$uri] = $prefix.$route;
		}

		// Reroute with module routes
		$route = static::parse($current_uri);
		// Remove first segment, that's the module
		array_shift($route['segments']);

		return $route;
	}
	
	/**
	 * Parses a route match and returns the controller, action and params.
	 *
	 * @access	protected
	 * @param	string	The matched route
	 * @return	array
	 */
	protected static function parse_match($route, $named_params = array())
	{
		$method_params = array();

		$segments = explode('/', $route);

		// Clean out all the non-named stuff out of $named_params
		foreach($named_params as $key => $val)
		{
			if (is_numeric($key))
			{
				unset($named_params[$key]);
			}
		}

		return array(
			'uri'			=> $route,
			'segments'		=> $segments,
			'named_params'	=> $named_params,
		);
	}
}

/* End of file route.php */