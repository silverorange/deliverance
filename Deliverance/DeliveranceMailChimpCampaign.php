<?php

/**
 * @package   Deliverance
 * @copyright 2009-2016 silverorange
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

	public $type;
	public $timewarp = false;
	public $track_orders = false;

	// }}}
	// {{{ public function __construct()

	public function __construct(
		SiteApplication $app,
		$shortname = null,
		$directory = null
	) {
		parent::__construct($app, $shortname, $directory);
		$this->type = self::CAMPAIGN_TYPE_REGULAR;
	}

	// }}}
	// {{{ public function getToName()

	public function getToName()
	{
		return '*|FNAME|* *|LNAME|*';
	}

	// }}}
	// {{{ public function setTimewarp()

	public function setTimewarp($timewarp = false)
	{
		$this->timewarp = (bool)$timewarp;
	}

	// }}}
	// {{{ public function setTrackOrders()

	public function setTrackOrders($track_orders = false)
	{
		$this->track_orders = (bool)$track_orders;
	}

	// }}}
	// {{{ public static function getPreviewUrl()

	public static function getPreviewUrl(SiteApplication $app, $campaign_id)
	{
		return sprintf(
			$app->config->mail_chimp->preview_url,
			DeliveranceMailChimpList::getDataCenter(
				$app->config->mail_chimp->api_key
			),
			$app->config->mail_chimp->user_id,
			$campaign_id
		);
	}

	// }}}
	// {{{ protected function getDOMDocument()

	protected function getDOMDocument($xhtml)
	{
		$document = parent::getDOMDocument($xhtml);

		// MailChimp alters the head for its archive page, and breaks when there
		// isn't whitespace between the head elements. Use formatOutput to
		// ensure whitespace between the head elements.
		$document->formatOutput = true;

		return $document;
	}

	// }}}
	// {{{ protected function appendAnalyticsToUri()

	protected function appendAnalyticsToUri($uri)
	{
		if ($this->isMailChimpUri($uri) === false) {
			$uri = parent::appendAnalyticsToUri($uri);
		}

		return $uri;
	}

	// }}}
	// {{{ protected function isMailChimpUri()

	protected function isMailChimpUri($uri)
	{
		return (substr($uri, 0, 2) == '*|');
	}

	// }}}
}

?>
