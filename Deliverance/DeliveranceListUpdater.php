<?php

/**
 * Cron job application to update mailing list with new and queued subscriber
 * requests.
 *
 * @package   Deliverance
 * @copyright 2009-2019 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class DeliveranceListUpdater extends DeliveranceCommandLineApplication
{
	// {{{ protected function runInternal()

	protected function runInternal()
	{
		parent::runInternal();

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
	// {{{ protected function subscribeQueued()

	protected function subscribeQueued(DeliveranceList $list)
	{
		$addresses = $this->getQueuedSubscribes();

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
			$subscribed_ids = $list->batchSubscribe($addresses);

			$this->debug(
				sprintf(
					Deliverance::_('%s queued addresses subscribed.')."\n",
					count($subscribed_ids)
				)
			);

			$this->clearQueuedSubscribes($subscribed_ids);
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
				Deliverance::_('Updating %s queued addresses.')."\n",
				count($addresses)
			)
		);

		if ($this->dry_run === false) {
			$updated_ids = $list->batchUpdate($addresses);

			$this->debug(
				sprintf(
					Deliverance::_('%s queued addresses updated.')."\n",
					count($updated_ids)
				)
			);

			$this->clearQueuedUpdates($updated_ids);
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
			$unsubscribed_ids = $list->batchUnsubscribe($addresses);

			$this->debug(
				sprintf(
					Deliverance::_('%s queued addresses unsubscribed.')."\n",
					count($unsubscribed_ids)
				)
			);

			$this->clearQueuedUnsubscribes($unsubscribed_ids);
		}

		$this->debug(
			Deliverance::_(
				'done unsubscribing queued addresses.'
			)."\n\n"
		);
	}

	// }}}
	// {{{ protected function getQueuedSubscribes()

	protected function getQueuedSubscribes()
	{
		$addresses = array();

		$sql = 'select id, email, info
			from MailingListSubscribeQueue
			where instance %s %s';

		$sql = sprintf(
			$sql,
			SwatDB::equalityOperator($this->getInstanceId()),
			$this->db->quote($this->getInstanceId(), 'integer')
		);

		$rows = SwatDB::query($this->db, $sql);
		foreach ($rows as $row) {
			$address          = unserialize($row->info);
			$address['id']    = $row->id;
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

		$sql = 'select id, email, info
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
			$address['id']    = $row->id;
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

		$sql = 'select id, email
			from MailingListUnsubscribeQueue
			where instance %s %s';

		$sql = sprintf(
			$sql,
			SwatDB::equalityOperator($this->getInstanceId()),
			$this->db->quote($this->getInstanceId(), 'integer')
		);

		$rows = SwatDB::query($this->db, $sql);
		foreach ($rows as $row) {
			$addresses[$row->id] = $row->email;
		}

		return $addresses;
	}

	// }}}
	// {{{ protected function clearQueuedSubscribes()

	protected function clearQueuedSubscribes(array $ids)
	{
		$sql = 'delete from MailingListSubscribeQueue
			where id in (%s) and instance %s %s';

		$sql = sprintf(
			$sql,
			$this->getQuotedIds($ids),
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
				count($ids)
			)
		);
	}

	// }}}
	// {{{ protected function clearQueuedSubscribes()

	protected function clearQueuedUpdates(array $ids)
	{
		$sql = 'delete from MailingListUpdateQueue
			where id in (%s) and instance %s %s';

		$sql = sprintf(
			$sql,
			$this->getQuotedIds($ids),
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
				count($ids)
			)
		);
	}

	// }}}
	// {{{ protected function clearQueuedUnsubscribes()

	protected function clearQueuedUnsubscribes(array $ids)
	{
		$sql = 'delete from MailingListUnsubscribeQueue
			where id in (%s) and instance %s %s';

		$sql = sprintf(
			$sql,
			$this->getQuotedIds($ids),
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
				count($ids)
			)
		);
	}

	// }}}
	// {{{ protected function getQuotedIds()

	protected function getQuotedIds(array $ids)
	{
		$quoted_id_array = array();

		foreach ($ids as $id) {
			$quoted_id_array[] = $this->db->quote($id, 'integer');
		}

		return implode(',', $quoted_id_array);
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
		return array_merge(
			parent::getDefaultModuleList(),
			[
				'config' => SiteCommandLineConfigModule::class,
				'database' => SiteDatabaseModule::class,
			]
		);
	}

	// }}}
}

?>
