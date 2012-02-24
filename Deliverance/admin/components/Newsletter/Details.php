<?php

require_once 'Swat/SwatString.php';
require_once 'Swat/SwatDetailsStore.php';
require_once 'Admin/pages/AdminIndex.php';
require_once 'Deliverance/DeliveranceListFactory.php';
require_once 'Deliverance/DeliveranceCampaignFactory.php';
require_once 'Deliverance/dataobjects/DeliveranceNewsletter.php';

/**
 * Details page for newsletters
 *
 * @package   Deliverance
 * @copyright 2011-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class DeliveranceNewsletterDetails extends AdminPage
{
	// {{{ protected properties

	/**
	 * @var integer
	 */
	protected $id;

	/**
	 * @var DeliveranceNewsletter
	 */
	protected $newsletter;

	// }}}
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		parent::initInternal();
		$this->id = SiteApplication::initVar('id');
		$this->ui->loadFromXML(dirname(__FILE__).'/details.xml');
		$this->initNewsletter();
		$this->updateNewsletterStats();
	}

	// }}}
	// {{{ protected function initNewsletter()

	protected function initNewsletter()
	{
		$class_name = SwatDBClassMap::get('DeliveranceNewsletter');
		$this->newsletter = new $class_name();
		$this->newsletter->setDatabase($this->app->db);

		if ($this->id !== null) {
			if (!$this->newsletter->load($this->id)) {
				throw new AdminNotFoundException(
					sprintf('Newsletter with id ‘%s’ not found.', $this->id));
			}
		}
	}

	// }}}
	// {{{ protected function updateNewsletterStats()

	protected function updateNewsletterStats()
	{
		// the url for stats can only be generated once the campaign has sent.
		// It may not be immediately available, so try to grab it if we've sent
		// the campaign but it hasn't been saved yet.
		if ($this->newsletter->isSent() &&
			$this->newsletter->campaign_report_url === null) {
			$list = DeliveranceListFactory::get($this->app, 'default');
			$list->setTimeout(
				$this->app->config->deliverance->list_admin_connection_timeout);

			try {
 				$this->newsletter->campaign_report_url =
					$list->getCampaignReportUrl($this->newsletter->campaign_id);

				$this->newsletter->save();
			/* } catch(DeliveranceException $e) {
				// if stats aren't ready, don't care about the exception.
				if ($e->getCode() != '301') {
					$e = new SiteException($e);
					$e->processAndContinue();
				} */
			} catch(Exception $e) {
				$e = new SiteException($e);
				$e->processAndContinue();
			}
		}
	}

	// }}}

	// build phase
	// {{{ protected function buildInternal()

	protected function buildInternal()
	{
		parent::buildInternal();

		$this->buildMessages();
		$this->buildToolbars();

		$this->ui->getWidget('details_frame')->title =
			$this->newsletter->getCampaignTitle();

		$view = $this->ui->getWidget('details_view');
		$view->data = $this->getDetailsStore();
		if ($this->newsletter->campaign_segment == null) {
			$view->getField('campaign_segment_field')->visible = false;
		}
	}

	// }}}
	// {{{ protected function buildToolbars()

	protected function buildToolbars()
	{
		$toolbar = $this->ui->getWidget('details_toolbar');
		$toolbar->setToolLinkValues($this->newsletter->id);

		$campaign = DeliveranceCampaignFactory::get($this->app, 'default');
		$this->ui->getWidget('preview_link')->link = call_user_func_array(
			array(
				$campaign,
				'getPreviewUrl',
			),
			array(
				$this->app,
				$this->newsletter->campaign_id,
			));

		if ($this->newsletter->isSent()) {
			$this->ui->getWidget('preview_link')->title =
				Deliverance::_('View Email');

			$this->ui->getWidget('edit_link')->visible         = false;
			$this->ui->getWidget('delete_link')->visible       = false;
			$this->ui->getWidget('cancel_link')->visible       = false;
			$this->ui->getWidget('schedule_link')->visible     = false;
			$this->ui->getWidget('send_preview_link')->visible = false;

			// reports may not exist yet if the newsletter was recently sent.
			$stats_link = $this->ui->getWidget('stats_link');
			if ($this->newsletter->campaign_report_url != '') {
				$stats_link->link = $this->newsletter->campaign_report_url;
			} else {
				$stats_link->sensitive = false;
				$stats_link->tooltip   = $this->getStatsToolTip();
			}
		} else if ($this->newsletter->isScheduled()) {
			$this->ui->getWidget('edit_link')->visible         = false;
			$this->ui->getWidget('stats_link')->visible        = false;
			$this->ui->getWidget('delete_link')->visible       = false;
			$this->ui->getWidget('send_preview_link')->visible = false;

			$this->ui->getWidget('schedule_link')->title =
				Deliverance::_('Reschedule Delivery');
		} else {
			$this->ui->getWidget('stats_link')->visible  = false;
			$this->ui->getWidget('cancel_link')->visible = false;
		}
	}

	// }}}
	// {{{ protected function buildNavBar()

	protected function buildNavBar()
	{
		parent::buildNavBar();

		$this->navbar->createEntry($this->newsletter->subject);
	}

	// }}}
	// {{{ protected function getDetailsStore()

	protected function getDetailsStore()
	{
		$newsletter = $this->newsletter;

		$ds = new SwatDetailsStore($newsletter);
		$ds->newsletter_status = $newsletter->getCampaignStatus($this->app);

		return $ds;
	}

	// }}}
	// {{{ protected function getStatsToolTip()

	protected function getStatsToolTip()
	{
		return Deliverance::_('This newletter’s status report will become '.
			'available a few hours after the newsletter has been sent.');
	}

	// }}}
}

?>
