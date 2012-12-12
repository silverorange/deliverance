<?php

require_once 'Admin/pages/AdminIndex.php';
require_once 'SwatDB/SwatDB.php';
require_once 'Swat/SwatDetailsStore.php';
require_once 'Swat/SwatTableStore.php';
require_once 'Deliverance/dataobjects/DeliveranceNewsletterWrapper.php';

/**
 * Page used to send a preview/test newsletter email
 *
 * @package   Deliverance
 * @copyright 2011-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class DeliveranceNewsletterIndex extends AdminIndex
{
	// process phase
	// {{{ protected function processInternal()

	protected function processInternal()
	{
		parent::processInternal();

		$pager = $this->ui->getWidget('pager');
		$pager->total_records = SwatDB::queryOne($this->app->db,
			'select count(id) from Newsletter');

		$pager->process();
	}

	// }}}

	// init phase
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		parent::initInternal();

		$this->ui->loadFromXML(dirname(__FILE__).'/index.xml');
	}

	// }}}

	// build phase
	// {{{ protected function getTableModel()

	protected function getTableModel(SwatView $view)
	{
		$sql = sprintf('select Newsletter.* from Newsletter order by %s',
			$this->getOrderByClause($view,
				'send_date desc nulls first, createdate desc'));

		$pager = $this->ui->getWidget('pager');
		$this->app->db->setLimit($pager->page_size, $pager->current_record);
		$newsletters = SwatDB::query($this->app->db, $sql,
			SwatDBClassMap::get('DeliveranceNewsletterWrapper'));

		$store = new SwatTableStore();
		foreach ($newsletters as $newsletter) {
			$ds = new SwatDetailsStore($newsletter);
			$ds->title  = $newsletter->getCampaignTitle();
			$ds->status = $newsletter->getCampaignStatus($this->app);

			$store->add($ds);
		}

		return $store;
	}

	// }}}
}

?>
