<?php

require_once 'Admin/pages/AdminDBEdit.php';
require_once 'Admin/exceptions/AdminNotFoundException.php';
require_once 'SwatDB/SwatDB.php';
require_once 'Swat/SwatMessage.php';
require_once 'Deliverance/DeliveranceListFactory.php';
require_once 'Deliverance/dataobjects/DeliveranceNewsletter.php';
require_once 'Deliverance/dataobjects/DeliveranceCampaignSegmentWrapper.php';

/**
 * Edit page for episodes
 *
 * @package   Deliverance
 * @copyright 2011-2013 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @todo      Better enforcing of instance.
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

		$this->ui->loadFromXML($this->getUiXml());

		$this->initNewsletter();
		$this->initCampaignSegments();
	}

	// }}}
	// {{{ protected function getUiXml()

	protected function getUiXml()
	{
		return 'Deliverance/admin/components/Newsletter/edit.xml';
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
		$sql = 'select * from MailingListCampaignSegment
			where %s
			order by instance, displayorder';

		$sql = sprintf(
			$sql,
			($this->app->getInstanceId() === null) ?
				'1 = 1' :
				$this->app->db->quote($instance_id, 'integer')
		);

		$segments = SwatDB::query(
			$this->app->db,
			$sql,
			SwatDBClassMap::get('DeliveranceCampaignSegmentWrapper')
		);

		if (count($segments)) {
			$segment_widget = $this->ui->getWidget('campaign_segment');
			$segment_widget->parent->visible = true;
			$locale = SwatI18NLocale::get();

			$last_instance_title = null;
			foreach ($segments as $segment) {
				if ($this->app->hasModule('SiteMultipleInstanceModule') &&
					$this->app->getInstance() === null &&
					$segment->instance instanceof SiteInstance &&
					$last_instance_title != $segment->instance->title) {
					$last_instance_title = $segment->instance->title;

					$segment_widget->addDivider(
						sprintf(
							'<span class="instance-header">%s</span>',
							$last_instance_title
						),
						'text/xml'
					);
				}

				if ($segment->cached_segment_size > 0) {
					$subscribers = sprintf(Deliverance::ngettext(
						'One subscriber',
						'%s subscribers',
						$segment->cached_segment_size),
						$locale->formatNumber($segment->cached_segment_size));
				} else {
					$subscribers = Deliverance::_('No subscribers');
				}

				$title = sprintf('%s <span class="swat-note">(%s)</span>',
					$segment->title,
					$subscribers);

				if ($segment->cached_segment_size > 0) {
					$segment_widget->addOption($segment->id, $title,
						'text/xml');
				} else {
					// TODO, use a real option and disable it.
					$segment_widget->addDivider($title, 'text/xml');
				}
			}
		}
	}

	// }}}

	// process phase
	// {{{ protected function saveDBData()

	protected function saveDBData()
	{
		$relocate = true;
		$save     = true;
		$message  = null;

		$this->updateNewsletter();

		try {
			// save/update on MailChimp.
			$campaign_id = $this->saveMailChimpCampaign();
		} catch (DeliveranceAPIConnectionException $e) {
			$relocate = true;
			$save     = true;

			// log api connection exceptions in the admin to keep a track of how
			// frequent they are.
			$e->processAndContinue();

			$message = new SwatMessage(
				Deliverance::_('There was an issue connecting to the email '.
					'service provider.'),
				'error'
			);

			// Note: the text about having to re-save before sending isn't true
			// as the schedule/send tools re-save the newsletter to the mailing
			// list before they send. But it is a good situation to instill
			// THE FEAR in users.
			$message->content_type = 'text/xml';
			$message->secondary_content = sprintf(
				'<strong>%s</strong><br />%s',
				sprintf(Deliverance::_(
					'“%s” has been saved locally so that your work is not '.
					'lost. You must edit the newsletter again before sending '.
					'to have your changes reflected in the sent newsletter.'),
					$this->newsletter->subject),
				Deliverance::_('Connection issues are typically short-lived '.
					'and editing the newsletter again after a delay will '.
					'usually be successful.')
				);
		} catch (Exception $e) {
			$relocate = false;
			$save     = false;

			$e = new DeliveranceException($e);
			$e->processAndContinue();

			$message = new SwatMessage(
				Deliverance::_('An error has occurred. The newsletter has not '.
					'been saved.'),
				'system-error'
			);
		}

		if ($save) {
			if ($this->newsletter->campaign_id === null) {
				$this->newsletter->campaign_id = $campaign_id;
			}

			if ($this->newsletter->id === null) {
				$this->newsletter->createdate  = new SwatDate();
				$this->newsletter->createdate->toUTC();
			}

			$this->newsletter->save();

			// if we don't already have a message, do a normal saved message
			if ($message == null) {
				$message = new SwatMessage(sprintf(
					Deliverance::_('“%s” has been saved.'),
					$this->newsletter->getCampaignTitle()));
			}
		}

		if ($message !== null) {
			$this->app->messages->add($message);
		}

		return $relocate;
	}

	// }}}
	// {{{ protected function updateNewsletter()

	protected function updateNewsletter()
	{
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
	}

	// }}}
	// {{{ protected function saveMailChimpCampaign()

	protected function saveMailChimpCampaign()
	{
		// Set a long timeout on mailchimp calls as we're in the admin & patient
		$list = DeliveranceListFactory::get($this->app, 'default');
		$list->setTimeout(
			$this->app->config->deliverance->list_admin_connection_timeout);

		$lookup_id_by_title = false;
		if ($this->newsletter->id !== null &&
			$this->newsletter->campaign_id === null) {
			// if the newsletter exists in the db, and doesn't have a campaign
			// id set try to look it up when saving.
			$lookup_id_by_title = true;
		}

		$campaign = $this->newsletter->getCampaign($this->app);
		$campaign_id = $list->saveCampaign($campaign, $lookup_id_by_title);

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

			$title = $this->newsletter->getCampaignTitle();
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
		$this->ui->getWidget('campaign_segment')->value =
			$this->newsletter->getInternalValue('campaign_segment');
	}

	// }}}

	// finalize phase
	// {{{ public function finalize()

	public function finalize()
	{
		parent::finalize();

		$this->layout->addHtmlHeadEntry(
			new SwatStyleSheetHtmlHeadEntry(
				'packages/deliverance/admin/styles/'.
					'deliverance-newsletter-details.css',
				Deliverance::PACKAGE_ID
			)
		);
	}

	// }}}
}

?>
