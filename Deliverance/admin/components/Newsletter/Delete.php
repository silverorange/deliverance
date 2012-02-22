<?php

require_once 'SwatDB/SwatDB.php';
require_once 'Admin/pages/AdminDBDelete.php';
require_once 'Admin/AdminListDependency.php';
require_once 'Admin/AdminDependencyEntryWrapper.php';
require_once 'Deliverance/DeliveranceList.php';
require_once 'Deliverance/dataobjects/DeliveranceNewsletter.php';

/**
 * Delete confirmation page for Newsletters
 *
 * @package   Deliverance
 * @copyright 2011-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class DeliveranceNewsletterDelete extends AdminDBDelete
{
	// process phase
	// {{{ protected function processDBData()

	protected function processDBData()
	{
		parent::processDBData();

		$newsletters = $this->getNewsletters();
		foreach ($newsletters as $newsletter) {
			$list = new MailChimpList($this->app);

			try {
				$list->deleteCampaign($newsletter->getCampaign($this->app));
			} catch (XML_RPC2_Exception $e) {
				if ($e->faultCode)
				var_dump($e); exit;
			}

		}

		$sql = 'delete from Newsletter where id in (%s);';

		$item_list = $this->getItemList('integer');
		$sql = sprintf($sql, $item_list);
		$num = SwatDB::exec($this->app->db, $sql);

		$message = new SwatMessage(sprintf(Deliverance::ngettext(
			'One newsletter has been deleted.',
			'%s newsletters have been deleted.', $num),
			SwatString::numberFormat($num)),
			'notice');

		$this->app->messages->add($message);
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
