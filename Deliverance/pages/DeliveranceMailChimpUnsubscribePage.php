<?php

require_once 'Deliverance/pages/DeliveranceUnsubscribePage.php';
require_once 'Deliverance/DeliveranceMailChimpList.php';

/**
 * @package   Deliverance
 * @copyright 2009-2010 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class DeliveranceMailChimpUnsubscribePage extends DeliveranceUnsubscribePage
{
	// process phase
	// {{{ protected function save()

	protected function getList()
	{
		return new DeliveranceMailChimpList($this->app);
	}

	// }}}
}

?>
