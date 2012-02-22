<?php

require_once 'SwatDB/SwatDBRecordsetWrapper.php';
require_once dirname(__FILE__).'/DeliveranceMailChimpNewsletter.php';

/**
 * A recordset wrapper class for DeliveranceMailChimpNewsletter objects
 *
 * @package   Deliverance
 * @copyright 2011-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @see       DeliveranceMailChimpNewsletter
 */
class DeliveranceMailChimpNewsletterWrapper extends DeliveranceNewsletterWrapper
{
	// {{{ protected function init()

	protected function init()
	{
		parent::init();

		$this->row_wrapper_class =
			SwatDBClassMap::get('DeliveranceMailChimpNewsletter');
	}

	// }}}
}

?>
