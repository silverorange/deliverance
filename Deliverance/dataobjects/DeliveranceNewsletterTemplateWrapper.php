<?php

require_once 'SwatDB/SwatDBRecordsetWrapper.php';
require_once 'Deliverance/dataobjects/DeliveranceNewsletterTemplate.php';

/**
 * A recordset wrapper class for DeliveranceNewsletterTemplate objects
 *
 * @package   Deliverance
 * @copyright 2015-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @see       DeliveranceNewsletterTemplate
 */
class DeliveranceNewsletterTemplateWrapper extends SwatDBRecordsetWrapper
{
	// {{{ protected function init()

	protected function init()
	{
		parent::init();

		$this->index_field = 'id';
		$this->row_wrapper_class = SwatDBClassMap::get(
			'DeliveranceNewsletterTemplate'
		);
	}

	// }}}
}

?>
