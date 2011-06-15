<?php

require_once 'Swat/SwatMessage.php';
require_once 'Site/pages/SiteEditPage.php';

/**
 * @package   Deliverance
 * @copyright 2009-2011 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class DeliveranceUnsubscribePage extends SiteEditPage
{
	// {{{ protected properties

	/**
	 * @var array
	 */
	protected $interests = null;

	// }}}
	// {{{ protected function getUiXml()

	protected function getUiXml()
	{
		return 'Deliverance/pages/unsubscribe.xml';
	}

	// }}}
	// {{{ protected function getInterests()

	protected function getInterests()
	{
		if ($this->interests === null) {
			$this->interests = array();

			if ($this->app->hasModule('SiteDatabaseModule')) {
				$sql = 'select id, title, shortname from MailingListInterest
					order by displayorder';

				$rs = SwatDB::query($this->app->db, $sql, null,
					array('integer', 'text'));

				while ($row = $rs->fetchRow(MDB2_FETCHMODE_OBJECT)) {
					$this->interests[$row->shortname] = $row->title;
				}
			}
		}

		return $this->interests;
	}

	// }}}

	// process phase
	// {{{ protected function save()

	protected function save(SwatForm $form)
	{
		$list = $this->getList();

		$interests = $this->getInterests();
		$removed_interests = $this->getRemovedInterests();

		// if address is removed from all interest groups, unsubscribe
		if (count($interests) === 0 ||
			count($interests) === count($removed_interests)) {
			$this->unsubscribe($list);
		} else {
			$this->removeInterests($list, $removed_interests);
		}
	}

	// }}}
	// {{{ abstract protected function getList()

	abstract protected function getList();

	// }}}
	// {{{ protected function unsubscribe()

	protected function unsubscribe(DeliveranceList $list)
	{
		$email = $this->getEmail();
		$response = $list->unsubscribe($email);
		$message = $list->handleUnsubscribeResponse($response);
		if ($message instanceof SwatMessage) {
			$this->ui->getWidget('message_display')->add($message);
		}
	}

	// }}}
	// {{{ protected function removeInterests()

	protected function removeInterests(DeliveranceList $list, array $interests)
	{
		$email = $this->getEmail();
		$info  = $this->getInterestInfo($interests);

		if (count($info) > 0) {
			$array_map = $this->getInterestArrayMap($interests);
			$response  = $list->update($email, $info, $array_map);
			$message   = $list->handleUpdateResponse($response);
			if ($message instanceof SwatMessage) {
				$this->ui->getWidget('message_display')->add($message);
			}
		}
	}

	// }}}
	// {{{ abstract protected function getInterestInfo();

	abstract protected function getInterestInfo(array $interests_to_remove);

	// }}}
	// {{{ protected function getInterestArrayMap()

	protected function getInterestArrayMap()
	{
		return array();
	}

	// }}}
	// {{{ protected function getEmail()

	protected function getEmail()
	{
		return $this->ui->getWidget('email')->value;
	}

	// }}}
	// {{{ protected function getRemovedInterests()

	protected function getRemovedInterests()
	{
		return $this->ui->getWidget('email_interests')->values;
	}

	// }}}
	// {{{ protected function relocate()

	protected function relocate(SwatForm $form)
	{
		if ($this->ui->getWidget('message_display')->getMessageCount() == 0) {
			$this->app->relocate($this->source.'/thankyou');
		}
	}

	// }}}

	// build phase
	// {{{ protected function buildInternal()

	protected function buildInternal()
	{
		parent::buildInternal();

		$email = SiteApplication::initVar('email');
		if (strlen($email) > 0) {
			$this->ui->getWidget('email')->value = $email;
		}

		$interests = $this->getInterests();
		if (count($interests) <= 1) {
			$this->ui->getWidget('email_interests_field')->visible = false;
		} else {
			$this->ui->getWidget('email_interests')->addOptionsByArray(
				$interests);
		}
	}

	// }}}

}

?>
