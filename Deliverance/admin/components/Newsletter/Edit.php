<?php

require_once 'Admin/pages/AdminDBEdit.php';
require_once 'Admin/exceptions/AdminNotFoundException.php';
require_once 'SwatDB/SwatDB.php';
require_once 'Swat/SwatMessage.php';
require_once 'Deliverance/DeliveranceListFactory.php';
require_once 'Deliverance/dataobjects/DeliveranceNewsletter.php';

/**
 * Edit page for episodes
 *
 * @package   Deliverance
 * @copyright 2011-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class DeliveranceNewsletterEdit extends AdminDBEdit
{
	// {{{ protected properties

	/**
	 * @var DeliveranceNewsletter
	 */
	protected $newsletter;

	// }}}

	// init phase
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		parent::initInternal();

		$this->ui->loadFromXML(dirname(__FILE__).'/edit.xml');

		$this->initNewsletter();
		$this->initCampaignSegments();
	}

	// }}}
	// {{{ protected function initNewsletter()

	protected function initNewsletter()
	{
		$class_name = SwatDBClassMap::get('DeliveranceNewsletter');
		$this->newsletter = new $class_name();
		$this->newsletter->setDatabase($this->app->db);
		if ($this->id !== null && !$this->newsletter->load($this->id)) {
			throw new AdminNotFoundException(sprintf(
				'A newsletter with the id of ‘%s’ does not exist',
				$this->id));
		}

		// Can't edit a newsletter that has been scheduled. This check will
		// also cover the case where the newsletter has been sent.
		if ($this->newsletter->isScheduled()) {
			$this->relocate();
		}
	}

	// }}}
	// {{{ protected function initCampaignSegments()

	protected function initCampaignSegments()
	{
		$options = SwatDB::getOptionArray($this->app->db,
			'MailingListCampaignSegment', 'title', 'shortname',
			'displayorder');

		if (count($options)) {
			$type_widget = $this->ui->getWidget('newsletter_type');
			$type_widget->addOptionsByArray($options);
			$type_widget->required = true;
			$type_widget->parent->visible = true;
		}
	}

	// }}}

	// process phase
	// {{{ protected function saveDBData()

	protected function saveDBData()
	{
		$relocate = true;
		$message = null;
		$values = $this->ui->getValues(array(
			'subject',
			'campaign_segment',
			'html_content',
			'text_content',
			));

		$this->newsletter->subject          = $values['subject'];
		$this->newsletter->campaign_segment = $values['campaign_segment'];
		$this->newsletter->html_content     = $values['html_content'];
		$this->newsletter->text_content     = $values['text_content'];

		// save/update on MailChimp.
		// TODO. save the db, but throw the error for MailChimp saving.
		try {
			$campaign_id = $this->saveMailChimpCampaign();

			if ($this->newsletter->id === null) {
				$this->newsletter->campaign_id = $campaign_id;
				$this->newsletter->createdate  = new SwatDate();
				$this->newsletter->createdate->toUTC();
			}

			$this->newsletter->save();

			$message = new SwatMessage(sprintf(
				Deliverance::_('“%s” has been saved.'),
				$this->newsletter->getCampaignTitle()));
		} catch (DeliveranceAPIConnectionException $e) {
			$e->processAndContinue();

			$relocate = false;
			$message = new SwatMessage(
				Deliverance::_('There was an issue connecting to the email '.
					'service provider.'),
				'error'
			);

			$message->secondary_content = Deliverance::_('The newsletter has '.
					'not been saved. Connection issues are typically '.
					'short-lived, and attempting to save the Newsletter again '.
					'should work.');
		} catch (Exception $e) {
			$e = new DeliveranceException($e);
			$e->processAndContinue();

			$relocate = false;
			$message = new SwatMessage(
				Deliverance::_('An error has occurred. The newsletter was not '.
					'cancelled.'),
				'system-error'
			);
		}

		if ($message !== null) {
			$this->app->messages->add($message);
		}

		return $relocate;
	}

	// }}}
	// {{{ protected function saveMailChimpCampaign()

	protected function saveMailChimpCampaign()
	{
		// Set a long timeout on mailchimp calls as we're in the admin & patient
		$list = DeliveranceListFactory::get($this->app, 'default');
		$list->setTimeout(
			$this->app->config->deliverance->list_admin_connection_timeout);

		$campaign = $this->newsletter->getCampaign($this->app);
		$campaign_id = $list->saveCampaign($campaign, false);

		// save/update campaign resources.
		Campaign::uploadResources($this->app, $campaign);

		return $campaign_id;
	}

	// }}}
	// {{{ protected function relocate()

	protected function relocate()
	{
		$this->app->relocate(sprintf('Newsletter/Details?id=%s',
			$this->newsletter->id));
	}

	// }}}

	// build phase
	// {{{ protected function buildNavBar()

	protected function buildNavBar()
	{
		parent::buildNavBar();

		if ($this->newsletter->id !== null) {
			$last = $this->navbar->popEntry();

			$title = $this->newsletter->subject;
			$link  = sprintf('Newsletter/Details?id=%s', $this->newsletter->id);
			$this->navbar->createEntry($title, $link);

			$this->navbar->addEntry($last);
		}
	}

	// }}}
	// {{{ protected function loadDBData()

	protected function loadDBData()
	{
		$this->ui->setValues(get_object_vars($this->newsletter));
	}

	// }}}
}

?>
