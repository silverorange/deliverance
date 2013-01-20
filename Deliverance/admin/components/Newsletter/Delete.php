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
 * @copyright 2011-2013 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
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
	 * @var boolean
	 *
	 * @see DeliveranceNewsletterDelete::relocate()
	 * @todo would this behaviour work in AdminDBDelete
	 */
	protected $success = true;

	/**
	 * Array of DeliveranceList objects
	 *
	 * @var array
	 */
	protected $lists = array();

	// }}}

	// process phase
	// {{{ protected function processResponse()

	protected function processResponse()
	{
		// override AdminDBConfirmation::processResponse to allow wrapping
		// each delete in a transaction instead of wrapping all of
		// processDBData() in a single transaction
		$form = $this->ui->getWidget('confirmation_form');
		$relocate = true;

		if ($this->ui->getWidget('yes_button')->hasBeenClicked()) {
			try {
				$relocate = $this->processDBData();
			} catch (SwatException $e) {
				$this->generateMessage($e);
				$e->processAndContinue();
			}
		}

		return $relocate;
	}

	// }}}
	// {{{ protected function processDBData()

	protected function processDBData()
	{
		parent::processDBData();

		$locale       = SwatI18NLocale::get();
		$relocate     = true;
		$message      = null;
		$delete_count = 0;
		$error_count  = 0;

		$newsletters = $this->getNewsletters();
		foreach ($newsletters as $newsletter) {
			// only allow deleting unsent newsletters. There is nothing
			// technically stopping us from deleting ones that have been sent,
			// but do this for the sake of stats until deleting sent newsletters
			// is required.
			if ($newsletter->isSent() == false) {
				$list = $this->getList($newsletter);

				$campaign_type =
					($newsletter->instance instanceof SiteInstance) ?
						$newsletter->instance->shortname :
						null;

				$campaign = $newsletter->getCampaign(
					$this->app,
					$campaign_type
				);

				$transaction = new SwatDBTransaction($this->app->db);
				try {
					DeliveranceCampaign::removeResources($this->app, $campaign);

					$list->deleteCampaign($campaign);

					$sql = 'delete from Newsletter where id = %s';
					$sql = sprintf(
						$sql,
						$this->app->db->quote($newsletter->id, 'integer')
					);

					$delete_count+= SwatDB::exec($this->app->db, $sql);

					$transaction->commit();
				} catch (DeliveranceAPIConnectionException $e) {
					$transaction->rollback();
					$e->processAndContinue();
					$error_count++;
					$relocate = false;
				} catch (Exception $e) {
					$transaction->rollback();
					$e = new DeliveranceException($e);
					$e->processAndContinue();
					$error_count++;
					$relocate = false;
				}
			}
		}

		if ($delete_count > 0) {
			$message = new SwatMessage(
				sprintf(
					Deliverance::ngettext(
						'One newsletter has been deleted.',
						'%s newsletters have been deleted.',
						$delete_count
					),
					$locale->formatNumber($delete_count)
				),
				'notice'
			);

			$this->app->messages->add($message);
		}

		if ($error_count > 0) {
			$message = new SwatMessage(
				Deliverance::_(
					'There was an issue connecting to the email service '.
					'provider.'
				),
				'error'
			);

			$message->content_type = 'text/xml';
			$message->secondary_content = sprintf(
				'<strong>%s</strong><br />%s',
				sprintf(
					Deliverance::ngettext(
						'One newsletter has not been deleted.',
						'%s newsletters have not been deleted.',
						$error_count
					),
					$locale->formatNumber($error_count)
				),
				Deliverance::ngettext(
					'Connection issues are typically short-lived and '.
						'attempting to delete the newsletter again after a '.
						'delay  will usually be successful.',
					'Connection issues are typically short-lived and '.
						'attempting to delete the newsletters again after a '.
						'delay will usually be successful.',
					$error_count
				)
			);

		}

		return $relocate;
	}

	// }}}
	// {{{ protected function initList()

	protected function getList(DeliveranceNewsletter $newsletter)
	{
		$key = ($newsletter->instance instanceof SiteInstance) ?
			$newsletter->instance->id : null;

		if (!isset($this->lists[$key])) {
			$list = DeliveranceListFactory::get(
				$this->app,
				'default',
				$newsletter->getDefaultList($this->app)
			);

			$list->setTimeout(
				$this->app->config->deliverance->list_admin_connection_timeout
			);

			$this->lists[$key] = $list;
		}

		return $this->lists[$key];
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
