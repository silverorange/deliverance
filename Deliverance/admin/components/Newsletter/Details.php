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
class DeliveranceNewsletterDetails extends AdminIndex
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

	/**
	 * @var string
	 */
	protected $ui_xml = 'Deliverance/admin/components/Newsletter/details.xml';

	// }}}

	// init phase
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		parent::initInternal();
		$this->id = SiteApplication::initVar('id');
		$this->ui->loadFromXML($this->ui_xml);
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
		$toolbars = $this->ui->getRoot()->getDescendants('SwatToolbar');
		foreach ($toolbars as $toolbar) {
			$toolbar->setToolLinkValues($this->newsletter->id);
		}

		// Preview link can be unavailable if the database save was successful,
		// but the mailing list call failed.
		$preview_link = $this->ui->getWidget('preview_link');
		if ($this->newsletter->campaign_id === null) {
			$preview_link->sensitive = false;
			$preview_link->tooltip   = Deliverance::_(
				'This newsletter’s preview is not available due to connection '.
				'issues with the email service provider. Edit the newsletter '.
				'to enable the preview.');
		} else {
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
		}

		if ($this->newsletter->isSent()) {
			$preview_link->title = Deliverance::_('View Email');

			$this->ui->getWidget('edit_link')->visible         = false;
			$this->ui->getWidget('delete_link')->visible       = false;
			$this->ui->getWidget('cancel_link')->visible       = false;
			$this->ui->getWidget('schedule_link')->visible     = false;
			$this->ui->getWidget('send_preview_link')->visible = false;

			// reports may not exist yet if the newsletter was recently sent.
			$stats_link = $this->ui->getWidget('stats_link');
			if ($this->newsletter->campaign_report_url === null) {
				$stats_link->sensitive = false;
				$stats_link->tooltip   = Deliverance::_(
					'This newletter’s status report will become available '.
					'shortly after the newsletter has been sent.');
			} else {
				$stats_link->link = $this->newsletter->campaign_report_url;
			}
		} elseif ($this->newsletter->isScheduled()) {
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

		$this->navbar->createEntry($this->newsletter->getCampaignTitle());
	}

	// }}}
	// {{{ protected function getTableModel()

	protected function getTableModel(SwatView $view)
	{
		return null;
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
}

?>
