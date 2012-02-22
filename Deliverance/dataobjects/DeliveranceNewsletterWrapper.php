<?php

require_once 'SwatDB/SwatDBRecordsetWrapper.php';
require_once dirname(__FILE__).'/DeliveranceNewsletter.php';

/**
 * A recordset wrapper class for DeliveranceNewsletter objects
 *
 * @package   Deliverance
 * @copyright 2011-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @see       DeliveranceNewsletter
 */
class DeliveranceNewsletterWrapper extends SwatDBRecordsetWrapper
{
	// {{{ protected function init()

	protected function init()
	{
		parent::init();

		$this->row_wrapper_class = 'Newsletter';
		$this->index_field = 'id';
	}

	// }}}
}

?>
