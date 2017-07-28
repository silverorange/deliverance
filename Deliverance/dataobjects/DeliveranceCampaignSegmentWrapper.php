<?php

/**
 * A recordset wrapper class for DeliveranceCampaignSegment objects
 *
 * @package   Deliverance
 * @copyright 2012-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @see       DeliveranceCampaignSegment
 */
class DeliveranceCampaignSegmentWrapper extends SwatDBRecordsetWrapper
{
	// {{{ protected function init()

	protected function init()
	{
		parent::init();

		$this->index_field = 'id';
		$this->row_wrapper_class =
			SwatDBClassMap::get('DeliveranceCampaignSegment');
	}

	// }}}
}

?>
