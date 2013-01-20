<?php

require_once 'Admin/pages/AdminDBEdit.php';
require_once 'Deliverance/DeliveranceListFactory.php';
require_once 'Deliverance/dataobjects/DeliveranceNewsletter.php';

/**
 * Confirmation page for cancelling a scheduled newsletter.
 *
 * @package   Deliverance
 * @copyright 2011-2013 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class DeliveranceNewsletterCancel extends AdminDBEdit
{
	// {{{ protected properties

	/**
	 * @var DeliveranceNewsletter
	 */
	protected $newsletter;

	/**
	 * @var DeliveranceList
	 */
	protected $list;

	// }}}

	// init phase
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		$this->ui->loadFromXML(dirname(__FILE__).'/cancel.xml');

		parent::initInternal();

		$this->initNewsletter();
		$this->initList();
	}

	// }}}
	// {{{ protected function initNewsletter()

	protected function initNewsletter()
	{
		if ($this->id == '') {
			$this->relocate('Newsletter');
		}

		$class_name = SwatDBClassMap::get('DeliveranceNewsletter');
		$this->newsletter = new $class_name();
		$this->newsletter->setDatabase($this->app->db);
		if (!$this->newsletter->load($this->id)) {
			throw new AdminNotFoundException(sprintf(
				'A newsletter with the id of ‘%s’ does not exist',
				$this->id));
		}

		// Can't cancel a newsletter that has not been scheduled.
		if (!$this->newsletter->isScheduled()) {
			$this->relocate();
		}

		// Can't cancel a newsletter that has been sent.
		if ($this->newsletter->isSent()) {
			$this->relocate();
		}
	}

	// }}}
	// {{{ protected function initList()

	protected function initList()
	{
		$this->list = DeliveranceListFactory::get(
			$this->app,
			'default',
			$this->newsletter->getDefaultList($this->app)
		);

		$this->list->setTimeout(
			$this->app->config->deliverance->list_admin_connection_timeout
		);
	}

	// }}}

	// process phase
	// {{{ protected function processInternal()

	protected function processInternal()
	{
		// AdminDBEdit pages don't support cancel buttons by default, so
		// just relocate here.
		if ($this->ui->getWidget('cancel_button')->hasBeenClicked()) {
			$this->relocate();
		}

		parent::processInternal();
	}

	// }}}
	// {{{ protected function saveDBData()

	protected function saveDBData()
	{
		$campaign_type = ($this->newsletter->instance instanceof SiteInstance) ?
			$this->newsletter->instance->shortname : null;

		$campaign = $this->newsletter->getCampaign(
			$this->app,
			$campaign_type
		);

		$message  = null;
		$relocate = true;
		try {
			$this->list->unscheduleCampaign($campaign);

			$this->newsletter->send_date = null;
			$this->newsletter->save();

			$message = new SwatMessage(sprintf(
				Deliverance::_('The delivery of “%s” has been canceled.'),
				$this->newsletter->subject
			));
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
				sprintf(Deliverance::_(
					'The delivery of “%s” has not been canceled.'),
					$this->newsletter->subject),
				Deliverance::_('Connection issues are typically short-lived '.
					'and attempting to cancel the newsletter again after a '.
					'delay will usually be successful.')
				);
		} catch (Exception $e) {
			$relocate = false;

			$e = new DeliveranceException($e);
			$e->processAndContinue();

			$message = new SwatMessage(
				Deliverance::_('An error has occurred. The newsletter has not '.
					'been cancelled.'),
				'system-error'
			);
		}

		if ($message !== null) {
			$this->app->messages->add($message);
		}

		return $relocate;
	}

	// }}}
	// {{{  protected function relocate()

	protected function relocate()
	{
		$this->app->relocate(sprintf('Newsletter/Details?id=%s',
			$this->newsletter->id));
	}

	// }}}

	// build phase
	// {{{ protected function buildInternal()

	protected function buildInternal()
	{
		parent::buildInternal();

		$message = $this->ui->getWidget('content_block');
		$message->content = $this->getMessage();
		$message->content_type = 'text/xml';
	}

	// }}}
	// {{{ protected function buildNavBar()

	protected function buildNavBar()
	{
		$this->navbar->popEntry();

		$title = $this->newsletter->getCampaignTitle();
		$link  = sprintf('Newsletter/Details?id=%s', $this->newsletter->id);
		$this->navbar->createEntry($title, $link);

		$this->navbar->createEntry(Deliverance::_('Cancel Delivery'));
	}

	// }}}
	// {{{ protected function buildFrame()

	protected function buildFrame()
	{
		$frame = $this->ui->getWidget('edit_frame');
		$frame->title = Deliverance::_('Cancel Delivery');
	}

	// }}}
	// {{{ protected function loadDBData()

	protected function loadDBData()
	{
	}

	// }}}
	// {{{ protected function getMessage()

	protected function getMessage()
	{
		$message = sprintf('<p>%s</p><p>%s</p>',
			Deliverance::_('The delivery of “%s” will canceled.'),
			Deliverance::_('The newsletter will not be deleted and can be '.
			'rescheduled for a later delivery date.'));

		return sprintf($message, $this->newsletter->subject);
	}

	// }}}
}

?>
