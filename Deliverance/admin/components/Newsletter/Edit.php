<?php

require_once 'Admin/pages/AdminDBEdit.php';
require_once 'Admin/exceptions/AdminNotFoundException.php';
require_once 'SwatDB/SwatDB.php';
require_once 'Swat/SwatMessage.php';
require_once 'Deliverance/DeliveranceListFactory.php';
require_once 'Deliverance/dataobjects/DeliveranceNewsletter.php';
require_once 'Deliverance/dataobjects/DeliveranceCampaignSegmentWrapper.php';
require_once 'Deliverance/exceptions/DeliveranceException.php';

/**
 * Edit page for episodes
 *
 * @package   Deliverance
 * @copyright 2011-2014 silverorange
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

	/**
	 * @var DeliveranceCampaignSegmentWrapper
	 */
	protected $segments;

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
		// select all segments that are visible, plus the current newsletters
		// segment (which can occasionally be disabled for one-off campaigns).
		$sql = 'select MailingListCampaignSegment.*,
				Instance.title as instance_title
			from MailingListCampaignSegment
			left outer join Instance
				on MailingListCampaignSegment.instance = Instance.id
			where (
					MailingListCampaignSegment.enabled = %s
					or MailingListCampaignSegment.id in (
						select campaign_segment from Newsletter where id = %s
					)
				)
				and %s
			order by instance_title nulls first,
				MailingListCampaignSegment.displayorder,
				MailingListCampaignSegment.title';

		$sql = sprintf(
			$sql,
			$this->app->db->quote(true, 'boolean'),
			$this->app->db->quote($this->newsletter->id, 'integer'),
			($this->app->getInstanceId() === null) ?
				'1 = 1' :
				$this->app->db->quote($instance_id, 'integer')
		);

		$this->segments = SwatDB::query(
			$this->app->db,
			$sql,
			SwatDBClassMap::get('DeliveranceCampaignSegmentWrapper')
		);
	}

	// }}}

	// process phase
	// {{{ protected function saveDBData()

	protected function saveDBData()
	{
		$relocate = true;
		$save     = true;
		$message  = null;

		try {
			if ($this->app->isMultipleInstanceAdmin() &&
				$this->newsletter->id !== null) {
				// List, campaign_type, old_instance and old_campaign all have
				// to happen before we modify the newsletter dataobject so they
				// correctly use the old values.
				$list = DeliveranceListFactory::get(
					$this->app,
					'default',
					DeliveranceNewsletter::getDefaultList(
						$this->app,
						$this->newsletter->instance
					)
				);

				$campaign_type =
					($this->newsletter->instance instanceof SiteInstance) ?
						$this->newsletter->instance->shortname :
						null;

				$old_instance = $this->newsletter->getInternalValue('instance');
				$old_campaign = $this->newsletter->getCampaign(
					$this->app,
					$campaign_type
				);
			}

			$this->updateNewsletter();

			// if instance has changed, delete the old campaign details.
			if ($this->app->isMultipleInstanceAdmin() &&
				$this->newsletter->id !== null &&
				$old_instance !=
					$this->newsletter->getInternalValue('instance')) {

				// If not a draft, remove the resources. Don't delete draft
				// newsletter resources as they are shared across all drafts.
				if ($this->newsletter->isScheduled()) {
					DeliveranceCampaign::removeResources(
						$this->app,
						$old_campaign
					);
				}

				$list->deleteCampaign($old_campaign);
				$this->newsletter->campaign_id = null;
			}

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
				sprintf(
					Deliverance::_(
						'“%s” has been saved locally so that your work is not '.
						'lost. You must edit the newsletter again before '.
						'sending to have your changes reflected in the sent '.
						'newsletter.'
					),
					$this->newsletter->subject
				),
				Deliverance::_(
					'Connection issues are typically short-lived '.
					'and editing the newsletter again after a delay will '.
					'usually be successful.'
				)
			);
		} catch (Exception $e) {
			$relocate = false;
			$save     = false;

			$e = new DeliveranceException($e);
			$e->processAndContinue();

			$message = new SwatMessage(
				Deliverance::_(
					'An error has occurred. The newsletter has not been saved.'
				),
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
		$values = $this->ui->getValues(
			array(
				'subject',
				'google_campaign',
				'preheader',
				'campaign_segment',
				'html_content',
				'text_content',
			)
		);

		// look up the segment and its instance from the segement wrapper
		// loaded in init otherwise they don't get saved correctly.
		$segment = $this->segments->getByIndex($values['campaign_segment']);
		$this->newsletter->campaign_segment = $segment;
		$this->newsletter->instance = $segment->instance;

		$this->newsletter->subject          = $values['subject'];
		$this->newsletter->google_campaign  = $values['google_campaign'];
		$this->newsletter->preheader        = $values['preheader'];
		$this->newsletter->campaign_segment = $values['campaign_segment'];
		$this->newsletter->html_content     = $values['html_content'];
		$this->newsletter->text_content     = $values['text_content'];
	}

	// }}}
	// {{{ protected function saveMailChimpCampaign()

	protected function saveMailChimpCampaign()
	{
		$list = DeliveranceListFactory::get(
			$this->app,
			'default',
			DeliveranceNewsletter::getDefaultList(
				$this->app,
				$this->newsletter->instance
			)
		);

		// Set a long timeout on mailchimp calls as we're in the admin & patient
		$list->setTimeout(
			$this->app->config->deliverance->list_admin_connection_timeout
		);

		$lookup_id_by_title = false;
		if ($this->newsletter->id !== null &&
			$this->newsletter->campaign_id === null) {
			// if the newsletter exists in the db, and doesn't have a campaign
			// id set try to look it up when saving.
			$lookup_id_by_title = true;
		}

		$campaign_type = ($this->newsletter->instance instanceof SiteInstance) ?
			$this->newsletter->instance->shortname : null;

		$campaign = $this->newsletter->getCampaign(
			$this->app,
			$campaign_type
		);

		$campaign_id = $list->saveCampaign($campaign, $lookup_id_by_title);

		// save/update campaign resources.
		DeliveranceCampaign::uploadResources($this->app, $campaign);

		return $campaign_id;
	}

	// }}}
	// {{{ protected function relocate()

	protected function relocate()
	{
		$this->app->relocate(
			sprintf(
				'Newsletter/Details?id=%s',
				$this->newsletter->id
			)
		);
	}

	// }}}

	// build phase
	// {{{ protected function buildInternal()

	protected function buildInternal()
	{
		parent::buildInternal();

		$this->buildSegments();
	}

	// }}}
	// {{{ protected function buildSegments()

	protected function buildSegments()
	{
		if (count($this->segments) > 0) {
			$segment_widget = $this->ui->getWidget('campaign_segment');
			$segment_widget->parent->visible = true;
			$locale = SwatI18NLocale::get();

			$last_instance_title = null;
			foreach ($this->segments as $segment) {
				if ($this->app->isMultipleInstanceAdmin() &&
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
					$subscribers = sprintf(
						Deliverance::ngettext(
							'One subscriber',
							'%s subscribers',
							$segment->cached_segment_size
						),
						$locale->formatNumber($segment->cached_segment_size)
					);
				} else {
					$subscribers = Deliverance::_('No subscribers');
				}

				$title = sprintf(
					'%s <span class="swat-note">(%s)</span>',
					$segment->title,
					$subscribers
				);

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
			'packages/deliverance/admin/styles/deliverance-newsletter-edit.css'
		);
	}

	// }}}
}

?>
