<?php

require_once 'Admin/pages/AdminDBEdit.php';
require_once 'Admin/exceptions/AdminNotFoundException.php';
require_once 'SwatDB/SwatDB.php';
require_once 'Swat/SwatMessage.php';
require_once 'Deliverance/DeliveranceList.php';
require_once 'Deliverance/dataobjects/DeliveranceNewsletter.php';

/**
 * Edit page for episodes
 *
 * @package   Deliverance
 * @copyright 2011-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class NewsletterEdit extends AdminDBEdit
{
	// {{{ protected properties

	/**
	 * @var Newsletter
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

		$type_widget = $this->ui->getWidget('newsletter_type');
		$type_widget->addOptionsByArray(SwatDB::getOptionArray(
			$this->app->db, 'MailingListCampaignSegment', 'title', 'shortname',
			'displayorder'));
	}

	// }}}
	// {{{ protected function initNewsletter()

	protected function initNewsletter()
	{
		$this->newsletter = new Newsletter();
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

	// process phase
	// {{{ protected function saveDBData()

	protected function saveDBData()
	{
		$values = $this->ui->getValues(array(
			'subject',
			'newsletter_type',
			'html_content',
			'text_content',
			));

		$this->newsletter->subject         = $values['subject'];
		$this->newsletter->newsletter_type = $values['newsletter_type'];
		$this->newsletter->html_content    = $values['html_content'];
		$this->newsletter->text_content    = $values['text_content'];

		// save/update on MailChimp.
		$mailchimp_campaign_id = $this->saveMailChimpCampaign();

		if ($this->newsletter->id === null) {
			$this->newsletter->mailchimp_campaign_id = $mailchimp_campaign_id;
			$this->newsletter->createdate            = new SwatDate();
			$this->newsletter->createdate->toUTC();
		}

		$this->newsletter->save();

		$this->app->messages->add(new SwatMessage(sprintf(
			Deliverance::_('“%s” has been saved.'),
			$this->newsletter->getCampaignTitle())));
	}

	// }}}
	// {{{ protected function saveMailChimpCampaign()

	protected function saveMailChimpCampaign()
	{
		// Set a long timeout on mailchimp calls as we're in the admin & patient
		$list = new DeliveranceMailChimpList($this->app);
		$list->setTimeout(
			$this->app->config->mail_chimp->admin_connection_timeout);

		$campaign = $this->newsletter->getCampaign($this->app);
		$mailchimp_campaign_id = $list->saveCampaign($campaign, false);

		// save/update campaign resources.
		Campaign::uploadResources($this->app, $campaign);

		return $mailchimp_campaign_id;
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
