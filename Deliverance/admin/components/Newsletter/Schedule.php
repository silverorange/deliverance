<?php

require_once 'Admin/pages/AdminDBEdit.php';
require_once 'Deliverance/DeliveranceList.php';
require_once 'Deliverance/dataobjects/DeliveranceNewsletter.php';

/**
 * Newsletter schedule/send confirmation page
 *
 * @package   Deliverance
 * @copyright 2011-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class DeliveranceNewsletterSchedule extends AdminDBEdit
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

	/**
	 * @var int
	 */
	protected $send_count;

	// }}}

	// init phase
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		$this->ui->loadFromXML(dirname(__FILE__).'/schedule.xml');

		parent::initInternal();

		$this->initNewsletter();
		$this->initList();
		$this->initSendCount();

		// only allow dates in the future, and only a year out for sanity's sake
		$action_date = $this->ui->getWidget('send_date');
		$action_date->setValidRange(0,1);
		$action_date->valid_range_start = new SwatDate();
		$action_date->valid_range_start->convertTZ(
			$this->app->default_time_zone);
	}

	// }}}
	// {{{ protected function initNewsletter()

	protected function initNewsletter()
	{
		if ($this->id == '') {
			$this->app->relocate('Newsletter');
		}

		$this->newsletter = new Newsletter();
		$this->newsletter->setDatabase($this->app->db);
		if (!$this->newsletter->load($this->id)) {
			throw new AdminNotFoundException(sprintf(
				'A newsletter with the id of ‘%s’ does not exist',
				$this->id));
		}

		// prevent editing of already sent newsletters
		if ($this->newsletter->isSent()) {
			$this->app->messages->add(new SwatMessage(
				Deliverance::_('Newsletters can not be re-sent.')));

			$this->relocate();
		}
	}

	// }}}
	// {{{ protected function initList()

	protected function initList()
	{
		$this->list = new MailChimpList($this->app);
		$this->list->setTimeout(
			$this->app->config->mail_chimp->admin_connection_timeout);
	}

	// }}}
	// {{{ protected function initSendCount()

	protected function initSendCount()
	{
		$campaign = $this->newsletter->getCampaign($this->app);
		if ($campaign->getSegmentOptions() == null) {
			$this->send_count = $this->list->getMemberCount();
		} else {
			$this->send_count = $this->list->getSegmentSize(
				$campaign->getSegmentOptions());
		}
	}

	// }}}

	// process phase
	// {{{ protected function saveDBData()

	protected function saveDBData()
	{
		$schedule = true;

		// Note: DeliveranceMailChimpList expects campaign send dates to be in
		// local time. Send date must be set on the newsletter so that its
		// internal campaign can get the correct send_date.
		if ($this->ui->getWidget('send_date')->value instanceof SwatDate) {
			$message_text = Deliverance::ngettext(
				'“%1$s” will be sent to one subscriber on %3$s at %4$s %5$s.',
				'“%1$s” will be sent to %2$s subscribers on %3$s at %4$s %5$s.',
				$this->send_count);

			// Preserve all the date fields
			$send_date = $this->ui->getWidget('send_date')->value;
			$send_date->setTZ($this->app->default_time_zone);
		} else {
			$schedule = false;

			$message_text = Deliverance::ngettext(
				'“%1$s” was sent to one subscriber.',
				'“%1$s” was sent to %2$s subscribers.',
				$this->send_count);

			// Convert all the date fields to the timezone
			$send_date = new SwatDate();
			$send_date->setTimezone($this->app->default_time_zone);
		}

		// message before date conversion to prevent needing to convert to UTC
		// for saving and then back to local time for display.
		$message = new SwatMessage(sprintf($message_text,
			$this->newsletter->subject,
			$this->send_count,
			$send_date->formatLikeIntl(SwatDate::DF_DATE),
			$send_date->formatLikeIntl(SwatDate::DF_TIME),
			$send_date->formatTZ(SwatDate::TZ_CURRENT_SHORT)));

		$message->secondary_content = Deliverance::_('Subscriber counts are '.
			'estimates. Full statistics will be available once the newsletter '.
			'has been sent.');

		// Finally set the date with the local timezone.
		// As DeliveranceMailChimpList expects.
		$this->newsletter->send_date = $send_date;
		$campaign = $this->newsletter->getCampaign($this->app);

		try {
			// resave campaign so that resource urls are rewritten.
			$this->list->saveCampaign($campaign, false);

			// save/update campaign resources.
			Campaign::uploadResources($this->app, $campaign);

			if ($schedule) {
				$this->list->scheduleCampaign($campaign);
			} else {
				$this->list->sendCampaign($campaign);
			}
		} catch(Exception $e) {
			$e = new SiteException($e);
			$e->process();
		}

		// Before we save the newsletter we need to convert it to UTC.
		$this->newsletter->send_date->toUTC();
		$this->newsletter->save();

		$this->app->messages->add($message);
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

		$message = $this->ui->getWidget('confirmation_message');
		$message->content = $this->getConfirmationMessage();
		$message->content_type = 'text/xml';

		$date = new SwatDate();
		$date->setTZ($this->app->default_time_zone);
		$note = sprintf(Deliverance::_('Scheduled times in '.
			'<strong>%s</strong>. Leave date and time blank to send '.
			'immediately.'),
			$date->formatTZ(SwatDate::TZ_COMBINED));

		$this->ui->getWidget('send_date_field')->note = $note;
		$this->ui->getWidget('send_date_field')->note_content_type = 'txt/xml';
	}

	// }}}
	// {{{ protected function buildNavBar()

	protected function buildNavBar()
	{
		$this->navbar->popEntry();

		$title = $this->newsletter->getCampaignTitle();
		$link  = sprintf('Newsletter/Details?id=%s', $this->newsletter->id);
		$this->navbar->createEntry($title, $link);

		if ($this->newsletter->isScheduled()) {
			$this->navbar->createEntry(Deliverance::_('Reschedule Newsletter'));
		} else {
			$this->navbar->createEntry(Deliverance::_('Schedule Newsletter'));
		}
	}

	// }}}
	// {{{ protected function buildFrame()

	protected function buildFrame()
	{
		$frame = $this->ui->getWidget('edit_frame');

		if ($this->newsletter->isScheduled()) {
			$frame->title = Deliverance::_('Reschedule Newsletter');
		} else {
			$frame->title = Deliverance::_('Schedule Newsletter');
		}
	}

	// }}}
	// {{{ protected function buildButton()

	protected function buildButton()
	{
		$button = $this->ui->getWidget('submit_button');

		if ($this->newsletter->isScheduled()) {
			$button->title = Deliverance::_('Reschedule');
		} else {
			$button->title = Deliverance::_('Schedule');
		}
	}

	// }}}
	// {{{ protected function loadDBData()

	protected function loadDBData()
	{
		if ($this->newsletter->isScheduled()) {
			$send_date = clone $this->newsletter->send_date;
			$send_date->setTimezone($this->app->default_time_zone);

			$this->ui->getWidget('send_date')->value = $send_date;
		}
	}

	// }}}
	// {{{ protected function getConfirmationMessage()

	protected function getConfirmationMessage()
	{
		ob_start();
		printf(Deliverance::ngettext(
				'<p>The newsletter “%s” will be sent to one subscriber.</p>',
				'<p>The newsletter “%s” will be sent to %s subscribers.</p>',
				$this->send_count),
			$this->newsletter->subject,
			$this->send_count);

		printf('<p>%s</p>',
			Deliverance::_('Subscriber counts are estimates. Full statistics '.
			'will be available once the newsletter has been sent.'));

		return ob_get_clean();
	}

	// }}}
}

?>
