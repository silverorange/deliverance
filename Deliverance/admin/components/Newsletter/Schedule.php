<?php

require_once 'Admin/pages/AdminDBEdit.php';
require_once 'Deliverance/DeliveranceListFactory.php';
require_once 'Deliverance/dataobjects/DeliveranceNewsletter.php';

/**
 * Newsletter schedule/send confirmation page
 *
 * @package   Deliverance
 * @copyright 2011-2013 silverorange
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
		$this->ui->loadFromXML($this->getUiXml());

		parent::initInternal();

		$this->initNewsletter();
		$this->initList();
		$this->initSendCount();

		// only allow dates in the future, and only a year out for sanity's sake
		$action_date = $this->ui->getWidget('send_date');
		$action_date->setValidRange(0, 1);
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

		$class_name = SwatDBClassMap::get('DeliveranceNewsletter');
		$this->newsletter = new $class_name();
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
		$this->list = DeliveranceListFactory::get(
			$this->app,
			'default',
			$this->getDefaultList()
		);

		$this->list->setTimeout(
			$this->app->config->deliverance->list_admin_connection_timeout
		);
	}

	// }}}
	// {{{ protected function getDefaultList()

	protected function getDefaultList()
	{
		$instance = $this->newsletter->instance;

		// TODO: make sure this method returns null for non-instanced admins.
		// All code below only makes sense for multiple instance admin. Is
		// repeated in Edit and Details. Refactor.
		$sql = 'select value from InstanceConfigSetting
			where name = %s and instance = %s';

		$sql = sprintf(
			$sql,
			$this->app->db->quote('mail_chimp.default_list', 'text'),
			$this->app->db->quote($instance->id, 'integer')
		);

		return SwatDB::queryOne($this->app->db, $sql);
	}

	// }}}
	// {{{ protected function initSendCount()

	protected function initSendCount()
	{
		if ($this->newsletter->campaign_segment !== null) {
			$this->send_count =
				$this->newsletter->campaign_segment->cached_segment_size;
		} else {
			$this->send_count = $this->list->getMemberCount();
		}
	}

	// }}}
	// {{{ protected function getUiXml()

	protected function getUiXml()
	{
		return 'Deliverance/admin/components/Newsletter/schedule.xml';
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
		$schedule = true;
		$relocate = true;
		$message  = null;

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

		// build the success message before date conversion to prevent needing
		// to convert to UTC for saving and then back to local time for display.
		$locale = SwatI18NLocale::get();
		$message = new SwatMessage(sprintf($message_text,
			$this->newsletter->subject,
			$locale->formatNumber($this->send_count),
			$send_date->formatLikeIntl(SwatDate::DF_DATE),
			$send_date->formatLikeIntl(SwatDate::DF_TIME),
			$send_date->formatTZ(SwatDate::TZ_CURRENT_SHORT)));

		$message->secondary_content = Deliverance::_('Subscriber counts are '.
			'estimates. Full statistics will be available once the newsletter '.
			'has been sent.');

		// Finally set the date with the local timezone.
		// As DeliveranceMailChimpList expects.
		$this->newsletter->send_date = $send_date;

		// TODO: Clean up for non-multiple instance admin.
		$campaign_type = ($this->newsletter->instance instanceof SiteInstance) ?
			$this->newsletter->instance->shortname : null;

		$campaign = $this->newsletter->getCampaign(
			$this->app,
			$campaign_type
		);

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

			// Before we save the newsletter we need to convert it to UTC.
			$this->newsletter->send_date->toUTC();
			$this->newsletter->save();
		} catch (DeliveranceAPIConnectionException $e) {
			// send date needs to be reset to null so the page titles stay
			// correct.
			$this->newsletter->send_date = null;
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
			if ($schedule) {
				$message->secondary_content = sprintf(
					'<strong>%s</strong><br />%s',
					sprintf(Deliverance::_('“%s” has not been scheduled.'),
						$this->newsletter->subject),
					Deliverance::_('Connection issues are typically '.
						'short-lived  and attempting to schedule the '.
						'newsletter again after a delay will usually be '.
						'successful.')
					);
			} else {
				$message->secondary_content = sprintf(
					'<strong>%s</strong><br />%s',
					sprintf(Deliverance::_('“%s” has not been sent.'),
						$this->newsletter->subject),
					Deliverance::_('Connection issues are typically '.
						'short-lived and attempting to send the newsletter '.
						'again after a delay will usually be successful.')
					);
			}
		} catch (Exception $e) {
			// send date needs to be reset to null so the page titles stay
			// correct.
			$this->newsletter->send_date = null;
			$relocate = false;

			$e = new DeliveranceException($e);
			$e->processAndContinue();

			if ($schedule) {
				$message_text = Deliverance::_('An error has occurred. The '.
					'newsletter has not been scheduled.');
			} else {
				$message_text = Deliverance::_('An error has occurred. The '.
					'newsletter has not been sent.');
			}

			$message = new SwatMessage($message_text, 'system-error');
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
		$this->navbar->createEntry($this->getTitle());
	}

	// }}}
	// {{{ protected function buildFrame()

	protected function buildFrame()
	{
		$frame = $this->ui->getWidget('edit_frame');
		$frame->title = $this->getTitle();
	}

	// }}}
	// {{{ protected function buildButton()

	protected function buildButton()
	{
		$button = $this->ui->getWidget('submit_button');
		$button->title = ($this->newsletter->isScheduled()) ?
			Deliverance::_('Reschedule') : Deliverance::_('Schedule');
	}

	// }}}
	// {{{ protected function getTitle()

	protected function getTitle()
	{
		return ($this->newsletter->isScheduled()) ?
			Deliverance::_('Reschedule Newsletter') :
			Deliverance::_('Schedule Newsletter');
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
		$locale = SwatI18NLocale::get();

		ob_start();

		printf(
			'<p>%s</p><p>%s</p>',
			sprintf(
				Deliverance::ngettext(
					'The newsletter “%s” will be sent to one subscriber.',
					'The newsletter “%s” will be sent to %s subscribers.',
					$this->send_count
				),
				$this->newsletter->subject,
				$locale->formatNumber($this->send_count)
			),
			Deliverance::_(
				'Subscriber counts are estimates. Full statistics '.
				'will be available once the newsletter has been sent.'
			)
		);

		return ob_get_clean();
	}

	// }}}
}

?>
