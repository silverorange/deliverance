<?php

require_once 'SwatDB/SwatDB.php';
require_once 'Deliverance/pages/DeliveranceSignupPage.php';

/**
 * @package   Deliverance
 * @copyright 2009-2014 silverorange
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

		$shortnames = $this->getInterests()->getDefaultShortnames();
		if (count($shortnames) > 0) {
			$info['interests'] = $shortnames;
		}

		return $info;
	}

	// }}}
}

?>
