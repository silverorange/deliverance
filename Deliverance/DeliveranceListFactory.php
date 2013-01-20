<?php

require_once 'Swat/SwatObject.php';
require_once 'Swat/exceptions/SwatClassNotFoundException.php';

/**
 * Factory for creating Deliverance Lists
 *
 * When an undefined list class is requested, the factory attempts to find and
 * require a class-definition file for the list class using the factory search
 * path. All search paths are relative to the PHP include path. The search path
 * '<code>Deliverance</code>' is included by default. Search paths can be added
 * and removed using the {@link DeliveranceListFactory::addPath()} and
 * {@link DeliveranceListFactory::removePath()} methods.
 *
 * @package   Deliverance
 * @copyright 2012-2013 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @see       DeliveranceList
 */
class DeliveranceListFactory extends SwatObject
{
	// {{{ private static properties

	/**
	 * List of registered list classes indexed by the list type
	 *
	 * @var array
	 */
	private static $list_class_names_by_type = array();

	/**
	 * Paths to search for class-definition files
	 *
	 * @var array
	 */
	private static $search_paths = array('Deliverance');

	// }}}
	// {{{ public static function get()

	/**
	 * Gets a list of the specified type
	 *
	 * @param SiteApplication $app the application in which to get the list.
	 * @param string $type the type of list to get. There must be a list class
	 *                      registered for this type.
	 * @param string $shortname the shortname of the list to call the
	 *                           constructor with.
	 *
	 * @return DeliveranceList the list of the specified type. The list will be
	 *                          an instance of whatever class was registered for
	 *                          the list type.
	 *
	 * @throws InvalidArgumentException if there is no list registered for the
	 *                                  requested <i>$type</i>.
	 */
	public static function get(SiteApplication $app, $type = 'default',
		$shortname = null)
	{
		if ($type === null) {
			$type = 'default';
		}

		$type = strval($type);
		if (!array_key_exists($type, self::$list_class_names_by_type)) {
			throw new InvalidArgumentException(sprintf(
				'No lists are registered with the type "%s".',
				$type));
		}

		$list_class_name = self::$list_class_names_by_type[$type];
		self::loadListClass($list_class_name);

		return new $list_class_name($app, $shortname);
	}

	// }}}
	// {{{ public static function registerList()

	/**
	 * Registers a list class with the factory
	 *
	 * List classes must be registed with the factory before they are used.
	 * When a list class is registered for a particular type, an instance of
	 * the list class is returned whenever a list of that type is requested.
	 *
	 * @param string $type the list type.
	 * @param string $list_class_name the class name of the list. The class
	 *                                 does not need to be defined until a
	 *                                 list of the specified type is requested.
	 */
	public static function registerList($type, $list_class_name)
	{
		$type = strval($type);
		self::$list_class_names_by_type[$type] = $list_class_name;
	}

	// }}}
	// {{{ public static function addPath()

	/**
	 * Adds a search path for class-definition files
	 *
	 * When an undefined list class is requested, the factory attempts to find
	 * and require a class-definition file for the list class.
	 *
	 * All search paths are relative to the PHP include path. The search path
	 * '<code>Deliverance</code>' is included by default.
	 *
	 * @param string $search_path the path to search for list class-definition
	 *                             files.
	 *
	 * @see DeliveranceListFactory::removePath()
	 */
	public static function addPath($search_path)
	{
		if (!in_array($search_path, self::$search_paths, true)) {
			// add path to front of array since it is more likely we will find
			// class-definitions in manually added search paths
			array_unshift(self::$search_paths, $search_path);
		}
	}

	// }}}
	// {{{ public static function removePath()

	/**
	 * Removes a search path for list class-definition files
	 *
	 * @param string $path the path to remove.
	 *
	 * @see DeliveranceListFactory::addPath()
	 */
	public static function removePath($path)
	{
		$index = array_search($path, self::$paths);
		if ($index !== false) {
			array_splice(self::$paths, $index, 1);
		}
	}

	// }}}
	// {{{ private static function loadListClass()

	/**
	 * Loads a list class-definition if it is not defined
	 *
	 * This checks the factory search path for an appropriate source file.
	 *
	 * @param string $list_class_name the name of the list class.
	 *
	 * @throws SwatClassNotFoundException if the list class is not defined and
	 *                                    no suitable file in the list search
	 *                                    path contains the class definition.
	 */
	private static function loadListClass($list_class_name)
	{
		// try to load class definition for $list_class_name
		if (!class_exists($list_class_name) &&
			count(self::$search_paths) > 0) {
			$include_paths = explode(':', get_include_path());
			foreach (self::$search_paths as $search_path) {
				// check if search path is relative
				if ($search_path[0] == '/') {
					$filename = sprintf('%s/%s.php',
						$search_path, $list_class_name);

					if (file_exists($filename)) {
						require_once $filename;
						break;
					}
				} else {
					foreach ($include_paths as $include_path) {
						$filename = sprintf('%s/%s/%s.php',
							$include_path, $search_path, $list_class_name);

						if (file_exists($filename)) {
							require_once $filename;
							break 2;
						}
					}
				}
			}
		}

		if (!class_exists($list_class_name)) {
			throw new SwatClassNotFoundException(sprintf(
				'List class "%s" does not exist and could not be found in '.
				'the search path.',
				$list_class_name), 0, $list_class_name);
		}
	}

	// }}}
	// {{{ private function __construct()

	/**
	 * This class contains only static methods and should not be instantiated
	 */
	private function __construct()
	{
	}

	// }}}
}

?>
