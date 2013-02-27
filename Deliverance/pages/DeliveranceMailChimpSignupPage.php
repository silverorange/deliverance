<?php

require_once 'SwatDB/SwatDB.php';
require_once 'Deliverance/pages/DeliveranceSignupPage.php';

/**
 * @package   Deliverance
 * @copyright 2009-2013 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class DeliveranceMailChimpSignupPage extends DeliveranceSignupPage
{
	// process phase
	// {{{ protected getSubscriberInfo()

	protected function getSubscriberInfo()
	{
		$info = array(
			'user_ip' => $this->app->getRemoteIP(),
		);

		// add to all interests by default
		$interests = $this->getInterests();
		if (count($interests) > 0) {
			$info['interests'] = $interests;
		}

		return $info;
	}

	// }}}
}

?>
