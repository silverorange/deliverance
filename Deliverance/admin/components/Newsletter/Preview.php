<?php

require_once 'Admin/pages/AdminEdit.php';
require_once 'Deliverance/DeliveranceListFactory.php';
require_once 'Deliverance/dataobjects/DeliveranceNewsletter.php';

/**
 * Page used to send a preview/test newsletter email
 *
 * @package   Deliverance
 * @copyright 2011-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class NewsletterPreview extends AdminEdit
{
	// {{{ protected properties

	/**
	 * @var Newsletter
	 */
	protected $newsletter;

	/**
	 * @var DeliveranceMailChimpList
	 */
	protected $list;

	// }}}

	// init phase
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		$this->ui->loadFromXML(dirname(__FILE__).'/preview.xml');

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

		// Can't send a preview of a newsletter that has been scheduled. This
		// check will also cover the case where the newsletter has been sent.
		if ($this->newsletter->isScheduled()) {
			$this->relocate();
		}
	}

	// }}}
	// {{{ protected function initList()

	protected function initList()
	{
		$this->list = DeliveranceListFactory::get($this->app, 'default');
		$this->list->setTimeout(
			$this->app->config->deliverance->list_admin_connection_timeout);
	}

	// }}}

	// process phase
	// {{{ protected function saveData()

	protected function saveData()
	{
		$relocate = true;
		$message = null;

		$form  = $this->ui->getWidget('edit_form');
		$email = $this->ui->getWidget('email')->value;

		$campaign = $this->newsletter->getCampaign($this->app);

		try {
			// re-save campaign, this makes life easier when testing template
			// changes
			$this->list->saveCampaign($campaign);
			// save/update campaign resources.
			Campaign::uploadResources($this->app, $campaign);

			$this->list->sendCampaignTest($campaign, array($email));
		} catch (DeliveranceAPIConnectionException $e) {
			$e->processAndContinue();

			$relocate = false;
			$message = new SwatMessage(
				Deliverance::_('There was an issue connecting to the email '.
					'service provider.'),
				'error'
			);

			$message->secondary_content = Deliverance::_('The preview has '.
					'not been sent. Connection issues are typically '.
					'short-lived, and attempting to send the Newsletter '.
					'preview again should work.');
		} catch (Exception $e) {
			$e = new DeliveranceException($e);
			$e->processAndContinue();

			$relocate = false;
			$message = new SwatMessage(
				Deliverance::_('An error has occurred. The newsletter preview '.
					' was not sent.'),
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
		$this->navbar->addEntry(new SwatNavBarEntry(
			$this->newsletter->getCampaignTitle(),
			sprintf('Newsletter/Details?id=%s', $this->newsletter->id)));

		$this->navbar->addEntry(new SwatNavBarEntry(
			Deliverance::_('Send Preview')));
	}

	// }}}
	// {{{ protected function buildFrame()

	protected function buildFrame()
	{
		$frame = $this->ui->getWidget('edit_frame');
		$frame->title = Deliverance::_('Send Preview');
	}

	// }}}
	// {{{ protected function loadData()

	protected function loadData()
	{
	}

	// }}}
	// {{{ protected function getMessage()

	protected function getMessage()
	{
		ob_start();
		printf(Deliverance::_('<p>The newsletter “%s” will be sent to '.
				'following email address.</p>'),
			$this->newsletter->subject);

		return ob_get_clean();
	}

	// }}}
}

?>
