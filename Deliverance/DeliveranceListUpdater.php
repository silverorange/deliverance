<?php

require_once 'Deliverance/DeliveranceCommandLineApplication.php';

/**
 * Cron job application to update mailing list with new and queued subscriber
 * requests.
 *
 * @package   Deliverance
 * @copyright 2009-2013 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class DeliveranceListUpdater extends DeliveranceCommandLineApplication
{
	// {{{ public function run()

	public function run()
	{
		parent::run();

		$list = $this->getList();

		$this->debug(Deliverance::_('Updating Mailing List')."\n\n", true);

		$this->debug(Deliverance::_('Subscribing:')."\n--------------------\n");
		$this->subscribe($list);
		$this->debug(Deliverance::_('Done subscribing.')."\n\n");

		$this->debug(Deliverance::_('Updating:')."\n--------------------\n");
		$this->update($list);
		$this->debug(Deliverance::_('Done updating.')."\n\n");

		$this->debug(
			Deliverance::_('Unsubscribing:')."\n--------------------\n");

		$this->unsubscribe($list);
		$this->debug(Deliverance::_('Done unsubscribing.')."\n\n");

		$this->debug(Deliverance::_('All Done.')."\n", true);
	}

	// }}}
	// {{{ protected function subscribe()

	protected function subscribe(DeliveranceList $list)
	{
		if ($list->isAvailable()) {
			// broken into two methods since we sometimes have to use different
			// api calls to send the welcome email.
			$this->subscribeQueuedWithWelcome($list);
			$this->subscribeQueued($list);
		} else {
			$this->debug(
				Deliverance::_(
					'Mailing list unavailable. No queued addresses subscribed.'
				)."\n"
			);
		}
	}

	// }}}
	// {{{ protected function update()

	protected function update(DeliveranceList $list)
	{
		if ($list->isAvailable()) {
			$this->updateQueued($list);
		} else {
			$this->debug(
				Deliverance::_(
					'Mailing list unavailable. No queued addresses updated.'
				)."\n"
			);
		}
	}

	// }}}
	// {{{ protected function unsubscribe()

	protected function unsubscribe(DeliveranceList $list)
	{
		if ($list->isAvailable()) {
			$this->unsubscribeQueued($list);
		} else {
			$this->debug(
				Deliverance::_(
					'Mailing list unavailable. No queued addresses '.
					'unsubscribed.'
				)."\n"
			);
		}
	}

	// }}}
	// {{{ protected function subscribeQueuedWithWelcome()

	protected function subscribeQueuedWithWelcome(DeliveranceList $list)
	{
		$with_welcome = true;
		$addresses = $this->getQueuedSubscribes($with_welcome);

		if (count($addresses) == 0) {
			$this->debug(
				Deliverance::_(
					'No queued addresses with welcome message to subscribe.'
				)."\n"
			);
			return;
		}

		$this->debug(
			sprintf(
				Deliverance::_(
					'Subscribing %s queued addresses with welcome message.'
				)."\n",
				count($addresses)
			)
		);

		if ($this->dry_run === false) {
			$result = $list->batchSubscribe($addresses, true,
				$this->getArrayMap());

			$clear_queued = $this->handleResult(
				$result,
				Deliverance::_(
					'%s queued addresses with welcome message subscribed.'
				)."\n"
			);

			// don't clean the queued subscribes if they have been re-queued.
			if ($clear_queued === true) {
				$this->clearQueuedSubscribes($addresses, $with_welcome);
			}
		}

		$this->debug(
			Deliverance::_(
				'done subscribing queued addresses with welcome message.'
			)."\n\n"
		);
	}

	// }}}
	// {{{ protected function subscribeQueued()

	protected function subscribeQueued(DeliveranceList $list)
	{
		$with_welcome = false;
		$addresses = $this->getQueuedSubscribes($with_welcome);

		if (count($addresses) == 0) {
			$this->debug(
				Deliverance::_(
					'No queued addresses to subscribe.'
				)."\n"
			);
			return;
		}

		$this->debug(
			sprintf(
				Deliverance::_('Subscribing %s queued addresses.')."\n",
				count($addresses)
			)
		);

		if ($this->dry_run === false) {
			$result = $list->batchSubscribe($addresses, false,
				$this->getArrayMap());

			$clear_queued = $this->handleResult(
				$result,
				Deliverance::_('%s queued addresses subscribed.')."\n"
			);

			// don't clean the queued subscribes if they have been re-queued.
			if ($clear_queued === true) {
				$this->clearQueuedSubscribes($addresses, $with_welcome);
			}
		}

		$this->debug(
			Deliverance::_(
				'done subscribing queued addresses.'
			)."\n\n"
		);
	}

	// }}}
	// {{{ protected function updateQueued()

	protected function updateQueued(DeliveranceList $list)
	{
		$list->setReplaceInterests(true);
		$addresses = $this->getQueuedUpdates();

		if (count($addresses) == 0) {
			$this->debug(
				Deliverance::_(
					'No queued addresses to update.'
				)."\n"
			);
			return;
		}

		$this->debug(
			sprintf(
				Deliverance::_(
					'Updating %s queued addresses.'
				)."\n",
				count($addresses)
			)
		);

		if ($this->dry_run === false) {
			$result = $list->batchUpdate($addresses);

			$clear_queued = $this->handleResult(
				$result,
				Deliverance::_(
					'%s queued addresses updated.'
				)."\n"
			);

			// don't clean the queued subscribes if they have been re-queued.
			if ($clear_queued === true) {
				$this->clearQueuedUpdates($addresses);
			}
		}

		$this->debug(
			Deliverance::_(
				'done updating queued addresses.'
			)."\n\n"
		);
	}

	// }}}
	// {{{ protected function unsubscribeQueued()

	protected function unsubscribeQueued(DeliveranceList $list)
	{
		$addresses = $this->getQueuedUnsubscribes();

		if (count($addresses) == 0) {
			$this->debug(
				Deliverance::_(
					'No queued addresses to unsubscribe.'
				)."\n"
			);
			return;
		}

		$this->debug(
			sprintf(
				Deliverance::_(
					'Unsubscribing %s queued addresses.'
				)."\n",
				count($addresses)
			)
		);

		if ($this->dry_run === false) {
			$result = $list->batchUnsubscribe($addresses);

			$clear_queued = $this->handleResult(
				$result,
				Deliverance::_(
					'%s queued addresses unsubscribed.'
				)."\n"
			);

			// don't clean the queued subscribes if they have been re-queued.
			if ($clear_queued === true) {
				$this->clearQueuedUnsubscribes($addresses);
			}
		}

		$this->debug(
			Deliverance::_(
				'done unsubscribing queued addresses.'
			)."\n\n"
		);
	}

	// }}}
	// {{{ protected function handleResult()

	protected function handleResult($result, $success_message)
	{
		$clear_queued = false;

		if ($result === DeliveranceList::QUEUED) {
			$this->debug(Deliverance::_('All requests queued.')."\n");
		} elseif ($result === DeliveranceList::SUCCESS) {
			$this->debug(Deliverance::_('All requests successful.')."\n");
			$clear_queued = true;
		} elseif (is_int($result) && $result > 0) {
			$this->debug(sprintf($success_message, $result));
		}

		return $clear_queued;
	}

	// }}}
	// {{{ protected function getArrayMap()

	protected function getArrayMap()
	{
		return array();
	}

	// }}}
	// {{{ protected function getQueuedSubscribes()

	private function getQueuedSubscribes($with_welcome)
	{
		$addresses = array();

		$sql = 'select email, info
			from MailingListSubscribeQueue
			where send_welcome = %s and instance %s %s';

		$sql = sprintf(
			$sql,
			$this->db->quote($with_welcome, 'boolean'),
			SwatDB::equalityOperator($this->getInstanceId()),
			$this->db->quote($this->getInstanceId(), 'integer')
		);

		$rows = SwatDB::query($this->db, $sql);
		foreach ($rows as $row) {
			$address          = unserialize($row->info);
			$address['email'] = $row->email;

			$addresses[] = $address;
		}

		return $addresses;
	}

	// }}}
	// {{{ protected function getQueuedUpdates()

	protected function getQueuedUpdates()
	{
		$addresses = array();

		$sql = 'select email, info
			from MailingListUpdateQueue
			where instance %s %s';

		$sql = sprintf(
			$sql,
			SwatDB::equalityOperator($this->getInstanceId()),
			$this->db->quote($this->getInstanceId(), 'integer')
		);

		$rows = SwatDB::query($this->db, $sql);
		foreach ($rows as $row) {
			$address          = unserialize($row->info);
			$address['email'] = $row->email;

			$addresses[] = $address;
		}

		return $addresses;
	}

	// }}}
	// {{{ protected function getQueuedUnsubscribes()

	protected function getQueuedUnsubscribes()
	{
		$addresses = array();

		$sql = 'select email
			from MailingListUnsubscribeQueue
			where instance %s %s';

		$sql = sprintf(
			$sql,
			SwatDB::equalityOperator($this->getInstanceId()),
			$this->db->quote($this->getInstanceId(), 'integer')
		);

		$rows = SwatDB::query($this->db, $sql);
		foreach ($rows as $row) {
			$addresses[] = $row->email;
		}

		return $addresses;
	}

	// }}}
	// {{{ protected function clearQueuedSubscribes()

	protected function clearQueuedSubscribes(array $addresses, $with_welcome)
	{
		$sql = 'delete from MailingListSubscribeQueue
			where email in (%s) and send_welcome = %s and instance %s %s';

		$sql = sprintf(
			$sql,
			$this->app->db->datatype->implodeArray(
				$addresses,
				'text'
			),
			$this->db->quote($with_welcome, 'boolean'),
			SwatDB::equalityOperator($this->getInstanceId()),
			$this->db->quote($this->getInstanceId(), 'integer')
		);

		$delete_count = SwatDB::exec($this->db, $sql);

		$this->debug(
			sprintf(
				Deliverance::_(
					'%s rows (%s addresses) cleared from the queue.'
				)."\n",
				$delete_count,
				count($addresses)
			)
		);
	}

	// }}}
	// {{{ protected function clearQueuedUpdates()

	protected function clearQueuedUpdates(array $addresses)
	{
		$sql = 'delete from MailingListUpdateQueue
			where email in (%s) and instance %s %s';

		$sql = sprintf(
			$sql,
			$this->app->db->datatype->implodeArray(
				$addresses,
				'text'
			),
			SwatDB::equalityOperator($this->getInstanceId()),
			$this->db->quote($this->getInstanceId(), 'integer')
		);

		$delete_count = SwatDB::exec($this->db, $sql);

		$this->debug(
			sprintf(
				Deliverance::_(
					'%s rows (%s addresses) cleared from the queue.'
				)."\n",
				$delete_count,
				count($addresses)
			)
		);
	}

	// }}}
	// {{{ protected function clearQueuedUnsubscribes()

	protected function clearQueuedUnsubscribes(array $addresses)
	{
		$sql = 'delete from MailingListUnsubscribeQueue
			where email in (%s) and instance %s %s';

		$sql = sprintf(
			$sql,
			$this->app->db->datatype->implodeArray(
				$addresses,
				'text'
			),
			SwatDB::equalityOperator($this->getInstanceId()),
			$this->db->quote($this->getInstanceId(), 'integer')
		);

		$delete_count = SwatDB::exec($this->db, $sql);

		$this->debug(
			sprintf(
				Deliverance::_(
					'%s rows (%s addresses) cleared from the queue.'
				)."\n",
				$delete_count,
				count($addresses)
			)
		);
	}

	// }}}

	// boilerplate
	// {{{ protected function addConfigDefinitions()

	protected function addConfigDefinitions(SiteConfigModule $config)
	{
		parent::addConfigDefinitions($config);
		$config->addDefinitions(Deliverance::getConfigDefinitions());
	}

	// }}}
	// {{{ protected function getDefaultModuleList()

	protected function getDefaultModuleList()
	{
		$list = parent::getDefaultModuleList();
		$list['config']   = 'SiteCommandLineConfigModule';
		$list['database'] = 'SiteDatabaseModule';

		return $list;
	}

	// }}}
}

?>
