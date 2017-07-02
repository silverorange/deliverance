<?php

/**
 * Factory for creating Deliverance Campaigns
 *
 * When an undefined campaign class is requested, the factory attempts to find
 * and require a class-definition file for the campaign class using the factory
 * search path. All search paths are relative to the PHP include path. The
 * search path '<code>Deliverance</code>' is included by default. Search paths
 * can be added and removed using the
 * {@link DeliveranceCampaignFactory::addPath()} and
 * {@link DeliveranceCampaignFactory::removePath()} methods.
 *
 * @package   Deliverance
 * @copyright 2012-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @see       DeliveranceCampaign
 */
class DeliveranceCampaignFactory extends SwatObject
{
	// {{{ private static properties

	/**
	 * Campaign of registered campaign classes indexed by the campaign type
	 *
	 * @var array
	 */
	private static $campaign_class_names_by_type = array();

	/**
	 * Paths to search for class-definition files
	 *
	 * @var array
	 */
	private static $search_paths = array('Deliverance');

	// }}}
	// {{{ public static function get()

	/**
	 * Gets a campaign of the specified type
	 *
	 * @param SiteApplication $app the application in which to get the campaign.
	 * @param string $type the type of campaign to get. There must be a campaign
	 *                      class registered for this type.
	 *
	 * @return DeliveranceCampaign the campaign of the specified type. The
	 *                              campaign will be an instance of whatever
	 *                              class was registered for the campaign type.
	 *
	 * @throws InvalidArgumentException if there is no campaign registered for
	 *                                   the requested <i>$type</i>.
	 */
	public static function get(SiteApplication $app, $type = 'default')
	{
		if ($type === null) {
			$type = 'default';
		}

		$type = strval($type);
		if (!array_key_exists($type, self::$campaign_class_names_by_type)) {
			throw new InvalidArgumentException(sprintf(
				'No campaigns are registered with the type "%s".',
				$type));
		}

		$campaign_class_name = self::$campaign_class_names_by_type[$type];
		self::loadCampaignClass($campaign_class_name);

		return new $campaign_class_name($app);
	}

	// }}}
	// {{{ public static function registerCampaign()

	/**
	 * Registers a campaign class with the factory
	 *
	 * Campaign classes must be registed with the factory before they are used.
	 * When a campaign class is registered for a particular type, an instance of
	 * the campaign class is returned whenever a campaign of that type is
	 * requested.
	 *
	 * @param string $type the campaign type.
	 * @param string $campaign_class_name the class name of the campaign. The
	 *                                     class does not need to be defined
	 *                                     until a campaign of the specified
	 *                                     type is requested.
	 */
	public static function registerCampaign($type, $campaign_class_name)
	{
		$type = strval($type);
		self::$campaign_class_names_by_type[$type] = $campaign_class_name;
	}

	// }}}
	// {{{ public static function addPath()

	/**
	 * Adds a search path for class-definition files
	 *
	 * When an undefined campaign class is requested, the factory attempts to
	 * find and require a class-definition file for the campaign class.
	 *
	 * All search paths are relative to the PHP include path. The search path
	 * '<code>Deliverance</code>' is included by default.
	 *
	 * @param string $search_path the path to search for campaign
	 *                              class-definition files.
	 *
	 * @see DeliveranceCampaignFactory::removePath()
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
	 * Removes a search path for campaign class-definition files
	 *
	 * @param string $path the path to remove.
	 *
	 * @see DeliveranceCampaignFactory::addPath()
	 */
	public static function removePath($path)
	{
		$index = array_search($path, self::$paths);
		if ($index !== false) {
			array_splice(self::$paths, $index, 1);
		}
	}

	// }}}
	// {{{ private static function loadCampaignClass()

	/**
	 * Loads a campaign class-definition if it is not defined
	 *
	 * This checks the factory search path for an appropriate source file.
	 *
	 * @param string $campaign_class_name the name of the campaign class.
	 *
	 * @throws SwatClassNotFoundException if the campaign class is not defined
	 *                                     and no suitable file in the campaign
	 *                                     search path contains the class
	 *                                     definition.
	 */
	private static function loadCampaignClass($campaign_class_name)
	{
		if (!class_exists($campaign_class_name)) {
			throw new SwatClassNotFoundException(sprintf(
				'Campaign class "%s" does not exist and could not be found in '.
				'the search path.',
				$campaign_class_name), 0, $campaign_class_name);
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
