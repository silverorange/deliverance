<?php

require_once 'SwatDB/SwatDB.php';
require_once 'Deliverance/pages/DeliveranceSignupPage.php';

/**
 * @package   Deliverance
 * @copyright 2009-2015 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class DeliveranceMailChimpSignupPage extends DeliveranceSignupPage
{
	// process phase
	// {{{ protected getSubscriberInfo()

	protected function getSubscriberInfo(DeliveranceList $list)
	{
		return $list->getDefaultSubscriberInfo();
	}

	// }}}
}

?>
