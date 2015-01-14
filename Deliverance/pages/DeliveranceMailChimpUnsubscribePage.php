<?php

require_once 'Deliverance/pages/DeliveranceUnsubscribePage.php';

/**
 * @package   Deliverance
 * @copyright 2009-2015 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class DeliveranceMailChimpUnsubscribePage extends DeliveranceUnsubscribePage
{
	// process phase
	// {{{ protected function removeInterests()

	protected function removeInterests(DeliveranceList $list, array $interests)
	{
		$list->setReplaceInterests(true);
		parent::removeInterests($list, $interests);
	}

	// }}}
	// {{{ protected function getInterestInfo();

	protected function getInterestInfo(array $interests_to_remove)
	{
		$info = array();

		$new_interests = $this->getNewInterests();

		// make sure interests changed, if not, don't update the info.
		if (count($new_interests) > 0 &&
			count($new_interests) !== count($this->getInterests())) {
			$info['interests'] = $new_interests;
		}

		return $info;
	}

	// }}}
}

?>
