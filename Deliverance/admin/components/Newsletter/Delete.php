<?php

require_once 'SwatDB/SwatDB.php';
require_once 'Admin/pages/AdminDBDelete.php';
require_once 'Admin/AdminListDependency.php';
require_once 'Admin/AdminDependencyEntryWrapper.php';
require_once 'Deliverance/DeliveranceListFactory.php';
require_once 'Deliverance/dataobjects/DeliveranceNewsletterWrapper.php';

/**
 * Delete confirmation page for Newsletters
 *
 * @package   Deliverance
 * @copyright 2011-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @todo      If the API connection drops midway through deleting a group of
 *            newsletters, the database entries for the successful deletes won't
 *            be removed, causing further errors. Delete entries as each api
 *            call succeeds, and build better messages reflecting this.
 */
class DeliveranceNewsletterDelete extends AdminDBDelete
{
	// {{{ protected properties

	/**
	 * Whether or not the delte was successful.
	 *
	 * This is tracked to allow the page to relocate, but go back to the
	 * newsletter details page when exceptions are thrown. Defaults to true to
	 * match normal AdminDBDelete behaviour.
	 *
	 * @var boolean.
	 *
	 * @see DeliveranceNewsletterDelete::relocate()
	 * @todo would this behaviour work in AdminDBDelete
	 */
	protected $success = true;

	// }}}

	// process phase
	// {{{ protected function processDBData()

	protected function processDBData()
	{
		parent::processDBData();

		$relocate = true;
		$message  = null;
		$count    = $this->getItemCount();

		try {
			$list = DeliveranceListFactory::get($this->app, 'default');
			$list->setTimeout(
				$this->app->config->deliverance->list_admin_connection_timeout);

			$newsletters = $this->getNewsletters();
			foreach ($newsletters as $newsletter) {
				$list->deleteCampaign($newsletter->getCampaign($this->app));
			}

			$sql = 'delete from Newsletter where id in (%s);';

			$item_list = $this->getItemList('integer');
			$sql = sprintf($sql, $item_list);
			$count = SwatDB::exec($this->app->db, $sql);

			$locale = SwatI18NLocale::get();
			$message = new SwatMessage(sprintf(
				Deliverance::ngettext(
					'One newsletter has been deleted.',
					'%s newsletters have been deleted.', $count),
				$locale->formatNumber($count)),
				'notice');
		} catch (DeliveranceAPIConnectionException $e) {
			$relocate = false;

			// log api connection exceptions in the admin to keep a track of how
			// frequent they are.
			$e->processAndContinue();

			$message = new SwatMessage(
				Deliverance::_('There was an issue connecting to the email '.
					'service provider.'),
				'error'
			);

			$message->content_type = 'text/xml';
			$message->secondary_content = sprintf(
				'<strong>%s</strong><br />%s',
				Deliverance::ngettext(
					'The newsletter has not been deleted.',
					'The newsletters have not been deleted.',
					$count),
				Deliverance::ngettext(
					'Connection issues are typically short-lived and '.
						'attempting to delete the newsletter again after a '.
						'delay  will usually be successful.',
					'Connection issues are typically short-lived and '.
						'attempting to delete the newsletters again after a '.
						'delay will usually be successful.',
					$count)
				);
		} catch (Exception $e) {
			// mimic the behaviour of other admin deletes and relocate on errors
			// that aren't the mailing list api timing out.
			$relocate = true;
			$this->success = false;

			$e = new DeliveranceException($e);
			$e->processAndContinue();

			$locale = SwatI18NLocale::get();
			$message = new SwatMessage(
				Deliverance::ngettext(
					'An error has occurred. The newsletter has not been '.
						'deleted.',
					'An error has occurred. The newsletters have not been '.
						'deleted.',
					$count),
				'system-error'
			);
		}

		if ($message !== null) {
			$this->app->messages->add($message);
		}

		return $relocate;
	}

	// }}}
	// {{{ protected function relocate()

	/**
	 * Relocates to the previous page after processsing confirmation response
	 */
	protected function relocate()
	{
		// Work around AdminDBDelete behaviour. If the delete wasn't succesful
		// and we still want to relocate, relocate to the page we can from,
		// don't force a relocate to the root of the component.
		if ($this->success) {
			parent::relocate();
		} else {
			AdminDBConfirmation::relocate();
		}
	}

	// }}}

	// build phase
	// {{{ protected function buildInternal()

	protected function buildInternal()
	{
		parent::buildInternal();

		$dep = new AdminListDependency();
		$dep->setTitle(Deliverance::_('newsletter'),
			Deliverance::_('newsletters'));

		$entries = array();
		$newsletters = $this->getNewsletters();
		foreach ($newsletters as $newsletter) {
			$entry = new AdminDependencyEntry();
			$entry->title = $newsletter->getCampaignTitle();
			$entry->status_level = ($newsletter->isSent() ?
				AdminDependency::NODELETE : AdminDependency::DELETE);

			$entries[] = $entry;
		}

		$dep->entries = $entries;

		$message = $this->ui->getWidget('confirmation_message');
		$message->content = $dep->getMessage();
		$message->content_type = 'text/xml';

		if ($dep->getStatusLevelCount(AdminDependency::DELETE) == 0)
			$this->switchToCancelButton();

	}

	// }}}

	// helper methods
	// {{{ private function getNewsletters()

	private function getNewsletters()
	{
		$item_list = $this->getItemList('integer');

		$sql = 'select * from Newsletter where id in (%s)';
		$sql = sprintf($sql, $item_list);

		$newsletters = SwatDB::query($this->app->db, $sql,
			SwatDBClassMap::get('DeliveranceNewsletterWrapper'));

		return $newsletters;
	}

	// }}}
}

?>
