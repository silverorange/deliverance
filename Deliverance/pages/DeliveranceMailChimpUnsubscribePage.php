<?php

require_once 'Deliverance/pages/DeliveranceUnsubscribePage.php';
require_once 'Deliverance/DeliveranceMailChimpList.php';

/**
 * @package   Deliverance
 * @copyright 2009-2011 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class DeliveranceMailChimpUnsubscribePage extends DeliveranceUnsubscribePage
{
	// process phase
	// {{{ protected function getList()

	protected function getList()
	{
		$list = new DeliveranceMailChimpList($this->app);
		$list->setReplaceInterests(true);
		return $list;
	}

	// }}}
	// {{{ protected function getInterestInfo();

	protected function getInterestInfo(array $interests)
	{
		$info = array();

		// we update the member with the difference of available interests and
		// removed interests
		$available_interests = $this->getInterests();
		if (count($available_interests) > 0) {
			$new_interests = array_diff($available_interests, $interests);
			// make sure interests changed
			if (count($new_interests) > 0 &&
				count($new_interests) !== count($available_interests)) {
				$info['interests'] = $new_interests;
			}
		}

		return $info;
	}

	// }}}
}

?>
