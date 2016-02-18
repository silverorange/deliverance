<?php

require_once 'SwatDB/SwatDB.php';
require_once 'Deliverance/pages/DeliveranceSignupPage.php';

/**
 * @package   Deliverance
 * @copyright 2009-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class DeliveranceMailChimpSignupPage extends DeliveranceSignupPage
{
	// process phase
	// {{{ protected getSubscriberInfo()

	protected function getSubscriberInfo(DeliveranceList $list)
	{
		$info = $list->getDefaultSubscriberInfo();

		// Send welcome is used to signify a new signup to the list. In that
		// case set correct site as the source.
		if ($this->send_welcome &&
			$this->app->config->mail_chimp->source != '') {
			$info['source'] = $this->app->config->mail_chimp->source;
		}

		return $info;
	}

	// }}}
}

?>
