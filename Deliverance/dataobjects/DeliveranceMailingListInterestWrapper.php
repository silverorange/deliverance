<?php

/**
 * A recordset wrapper class for DeliveranceMailingListInterest objects
 *
 * @package   Deliverance
 * @copyright 2014-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @see       DeliveranceMailingListInterest
 */
class DeliveranceMailingListInterestWrapper extends SwatDBRecordsetWrapper
{
	// {{{ public function getShortnames()

	public function getShortnames()
	{
		$shortnames = array();

		foreach ($this as $interest) {
			$shortnames[] = $interest->shortname;
		}

		return $shortnames;
	}

	// }}}
	// {{{ public function getDefaultShortnames()

	public function getDefaultShortnames()
	{
		$shortnames = array();

		foreach ($this as $interest) {
			if ($interest->is_default) {
				$shortnames[] = $interest->shortname;
			}
		}

		return $shortnames;
	}

	// }}}
	// {{{ protected function init()

	protected function init()
	{
		parent::init();

		$this->row_wrapper_class = SwatDBClassMap::get(
			'DeliveranceMailingListInterest'
		);

		$this->index_field = 'id';
	}

	// }}}
}

?>
