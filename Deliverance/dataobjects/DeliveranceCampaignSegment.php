<?php

require_once 'SwatDB/SwatDBDataObject.php';
require_once 'Site/dataobjects/SiteInstance.php';

/**
 * A dataobject representing a segment of subscribers to send a campaign to.
 *
 * @package   Deliverance
 * @copyright 2012-2013 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class DeliveranceCampaignSegment extends SwatDBDataObject
{
	// {{{ public properties

	/**
	 * @var integer
	 */
	public $id;

	/**
	 * @var string
	 */
	public $shortname;

	/**
	 * @var string
	 */
	public $title;

	/**
	 * @var int
	 */
	public $displayorder;

	/**
	 * A json_encoded array of segement options.
	 *
	 * @var string
	 */
	public $segment_options;

	/**
	 * Whether or not to show as an option when building new newsletters.
	 *
	 * @var boolean
	 */
	public $enabled;

	/**
	 * @var integer
	 */
	public $cached_segment_size;

	// }}}
	// {{{ public function getSegmentOptions()

	public function getSegmentOptions()
	{
		return json_decode($this->segment_options, true);
	}

	// }}}
	// {{{ public function setSegmentOptions()

	public function setSegmentOptions(array $segment_options)
	{
		$this->segment_options = json_encode($segment_options);
	}

	// }}}
	// {{{ protected function init()

	protected function init()
	{
		parent::init();

		$this->registerInternalProperty(
			'instance',
			SwatDBClassMap::get('SiteInstance')
		);

		$this->table = 'MailingListCampaignSegment';
		$this->id_field = 'integer:id';
	}

	// }}}
}

?>
