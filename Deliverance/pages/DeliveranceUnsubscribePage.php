<?php

require_once 'Swat/SwatMessage.php';
require_once 'Site/pages/SiteEditPage.php';
require_once 'Deliverance/DeliveranceListFactory.php';

/**
 * @package   Deliverance
 * @copyright 2009-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @todo      Members with both non-visible and visible interests will lose all
 *            non-visible interests when updating their subscription. This
 *            should be improved if and when its needed.
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
				$sql = 'select id, title, shortname
					from MailingListInterest
					where visible = %s
					order by displayorder';

				$sql = sprintf($sql,
					$this->app->db->quote(true, 'boolean'));

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

	// init phase
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		parent::initInternal();

		if ($this->ui->hasWidget('email_interests_field') &&
			$this->ui->hasWidget('email_interests')) {

			$interests = $this->getInterests();
			if (count($interests) <= 1) {
				$this->ui->getWidget('email_interests_field')->visible = false;
			} else {
				$this->ui->getWidget('email_interests')->addOptionsByArray(
					$interests);

				$this->ui->getWidget('email_interests')->values =
					$this->getDefaultInterestValues();
			}
		}
	}

	// }}}
	// {{{ protected function getDefaultInterestValues()

	protected function getDefaultInterestValues()
	{
		// default to unsubscribing all interests.
		return array_flip($this->getInterests());
	}

	// }}}

	// process phase
	// {{{ protected function save()

	protected function save(SwatForm $form)
	{
		$list              = $this->getList();
		$interests         = $this->getInterests();
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
	// {{{ protected function getList()

	protected function getList()
	{
		return DeliveranceListFactory::get($this->app, 'default');
	}

	// }}}
	// {{{ protected function unsubscribe()

	protected function unsubscribe(DeliveranceList $list)
	{
		$email    = $this->getEmail();
		$response = $list->unsubscribe($email);

		$this->handleUnsubscribeResponse($list, $response);
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

			$this->handleUpdateResponse($list, $response);
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
		$removed = array();

		if ($this->ui->hasWidget('email_interests')) {
			$removed = $this->ui->getWidget('email_interests')->values;
		}

		return $removed;
	}

	// }}}
	// {{{ protected function getNewInterests()

	protected function getNewInterests()
	{
		$new = array();

		// new interests are the difference of available interests and removed
		// interests. flip so we can compare ids.
		$interests = array_flip($this->getInterests());
		if (count($interests) > 0) {
			$new = array_diff($interests, $this->getRemovedInterests());
		}

		return $new;
	}

	// }}}
	// {{{ protected function handleUnsubscribeResponse()

	protected function handleUnsubscribeResponse(DeliveranceList $list,
		$response)
	{
		$message = $list->handleUnsubscribeResponse($response);

		$this->handleMessage($message);

	}

	// }}}
	// {{{ protected function handleUpdateResponse($response)

	protected function handleUpdateResponse(DeliveranceList $list, $response)
	{
		$message = $list->handleUpdateResponse($response);

		$this->handleMessage($message);

	}

	// }}}
	// {{{ protected function handleMessage()

	protected function handleMessage(SwatMessage $message = null)
	{
		if ($message instanceof SwatMessage) {
			$this->ui->getWidget('message_display')->add($message);
		}
	}

	// }}}
	// {{{ protected function relocate()

	protected function relocate(SwatForm $form)
	{
		if ($this->canRelocate($form)) {
			$this->addUnsubscribeMessage();
			$this->app->relocate($this->getRelocateUri());
		}
	}

	// }}}
	// {{{ protected function canRelocate()

	protected function canRelocate(SwatForm $form)
	{
		return ($this->ui->getWidget('message_display')->getMessageCount() ==
			0);
	}

	// }}}
	// {{{ protected function addUnsubscribeMessage()

	protected function addUnsubscribeMessage()
	{
		// TODO - add interest update messages.
	}

	// }}}
	// {{{ protected function getRelocateUri()

	protected function getRelocateUri()
	{
		return $this->source.'/thankyou';
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
	}

	// }}}

}

?>
