<?php

require_once 'SwatDB/SwatDBRecordsetWrapper.php';
require_once dirname(__FILE__).'/DeliveranceNewsletter.php';

/**
 * A recordset wrapper class for DeliveranceNewsletter objects
 *
 * @package   Deliverance
 * @copyright 2011-2015 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @see       DeliveranceNewsletter
 */
class DeliveranceNewsletterWrapper extends SwatDBRecordsetWrapper
{
	// {{{ protected function init()

	protected function init()
	{
		parent::init();

		$this->row_wrapper_class = SwatDBClassMap::get('DeliveranceNewsletter');
		$this->index_field = 'id';
	}

	// }}}
}

?>
