<?php

require_once 'Deliverance/DeliveranceCampaign.php';

/**
 * @package   Deliverance
 * @copyright 2009-2011 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class DeliveranceMailChimpCampaign extends DeliveranceCampaign
{
	// {{{ class constants

	/**
	 * Campaign Types
	 */
	const CAMPAIGN_TYPE_REGULAR       = 'regular';
	const CAMPAIGN_TYPE_PLAINTEXT     = 'plaintext';
	const CAMPAIGN_TYPE_ABSPLIT       = 'absplit';
	const CAMPAIGN_TYPE_RSS           = 'rss';
	const CAMPAIGN_TYPE_TRANSACTIONAL = 'trans';

	// }}}
	// {{{ public properties

	public $id;
	public $type;
	public $timewarp = false;
	public $track_orders = false;

	// }}}
	// {{{ public function __construct()

	public function __construct(SiteApplication $app, $shortname,
		$directory = null)
	{
		parent::__construct($app, $shortname, $directory);
		$this->type = self::CAMPAIGN_TYPE_REGULAR;
	}

	// }}}
	// {{{ public function getSegmentOptions()

	public function getSegmentOptions()
	{
		return null;
	}

	// }}}
	// {{{ public function getSendDate()

	public function getSendDate()
	{
		return null;
	}

	// }}}
	// {{{ public function setTimewarp()

	public function setTimewarp($timewarp = false)
	{
		$this->timewarp = (bool) $timewarp;
	}

	// }}}
	// {{{ public function setTrackOrders()

	public function setTrackOrders($track_orders = false)
	{
		$this->track_orders = (bool) $track_orders;
	}

	// }}}
	// {{{ public static function getPreviewUrl()

	public static function getPreviewUrl(SiteApplication $app, $campaign_id)
	{
		return sprintf($app->config->mail_chimp->preview_url,
			$app->config->mail_chimp->datacenter,
			$app->config->mail_chimp->user_id,
			$campaign_id);
	}

	// }}}
}

?>
