<?php

require_once 'SwatDB/SwatDBDataObject.php';
require_once 'Deliverance/DeliveranceList.php';
require_once 'Deliverance/DeliveranceCampaign.php';

/**
 * @package   Deliverance
 * @copyright 2011-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class DeliveranceNewsletter extends SwatDBDataObject
{
	// {{{ public properties

	/**
	 * @var integer
	 */
	public $id;

	/**
	 * @var SwatDate
	 */
	public $subject;

	/**
	 * @var string
	 */
	public $html_content;

	/**
	 * @var string
	 */
	public $text_content;

	/**
	 * @var SwatDate
	 */
	public $send_date;

	/**
	 * @var SwatDate
	 */
	public $createdate;

	// }}}
	// {{{ public static function getCampaign()

	public static function getMailingLIst(SiteApplication $app)
	{
		return new DeliveranceList($app);
	}

	// }}}

	// {{{ public static function getCampaign()

	public static function getCampaignClass(SiteApplication $app, $shortname)
	{
		return new DeliveranceCampaign($app, $shortname);
	}

	// }}}
	// {{{ public function isSent()

	public function isSent()
	{
		$sent = false;

		if ($this->send_date instanceof SwatDate) {
			$send_date = clone $this->send_date;
			$send_date->toUTC();

			$now = new SwatDate();
			$now->toUTC();

			$sent = ($now->after($this->send_date));
		}

		return $sent;
	}

	// }}}
	// {{{ public function isScheduled()

	public function isScheduled()
	{
		return ($this->send_date instanceof SwatDate);
	}

	// }}}
	// {{{ public function getCampaign()

	public function getCampaign(SiteApplication $app)
	{
		$campaign = self::getCampaignClass($app, $this->getCampaignShortname());

		$campaign->setId($this->getCampaignId());
		$campaign->setSubject($this->subject);
		$campaign->setNewsletterType($this->newsletter_type);
		$campaign->setSegmentOptions($this->getSegmentOptions());
		$campaign->setHtmlContent($this->html_content);
		$campaign->setTextContent($this->text_content);
		$campaign->setTitle($this->getCampaignTitle());

		if ($this->send_date instanceof SwatDate) {
			$campaign->setSendDate($this->send_date);
		}

		return $campaign;
	}

	// }}}
	// {{{ protected function getCampaignId()

	protected function getCampaignId()
	{
		return null;
	}

	// }}}
	// {{{ public function getCampaignStatus()

	public function getCampaignStatus(SiteApplication $app)
	{
		$status = null;

		if ($this->send_date === null) {
			$status = Deliverance::_('Draft');
		} else {
			if ($this->isSent()) {
				$status = Deliverance::_('Sent on: %s');
			} else {
				$status = Deliverance::_('Scheduled for: %s');
			}

			$date = clone $this->send_date;
			$date->convertTZ($app->default_time_zone);

			$status = sprintf($status,
				$date->formatLikeIntl(SwatDate::DF_DATE_TIME));
		}

		return $status;
	}

	// }}}
	// {{{ public function getCampaignShortname()

	public function getCampaignShortname()
	{
		if ($this->send_date === null) {
			$shortname = Deliverance::_('DRAFT');
		} else {
			$shortname = $this->send_date->formatLikeIntl('yyyy-MM-dd');
		}

		return $shortname;
	}

	// }}}
	// {{{ public function getCampaignTitle()

	public function getCampaignTitle()
	{
		$title = sprintf('%s: %s',
			$this->getCampaignShortname(),
			$this->subject);

		if ($this->newsletter_type != null) {
			$title.= ' - '.$this->newsletter_type;
		}

		return $title;
	}

	// }}}
	// {{{ protected function getSegmentOptions()

	protected function getSegmentOptions()
	{
		$sql = 'select segment_options from MailingListCampaignSegment
			where shortname = %s';

		$sql = sprintf($sql,
			$this->db->quote($this->newsletter_type, 'text'));

		return json_decode(SwatDB::queryOne($this->db, $sql), true);
	}

	// }}}
	// {{{ protected function init()

	protected function init()
	{
		parent::init();

		$this->table = 'Newsletter';
		$this->id_field = 'integer:id';

		$this->registerDateProperty('send_date');
		$this->registerDateProperty('createdate');
	}

	// }}}
}

?>
