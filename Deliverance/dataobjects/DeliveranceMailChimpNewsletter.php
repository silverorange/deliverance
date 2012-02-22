<?php

require_once 'Deliverance/dataobjects/DeliveranceNewsletter.php';
require_once 'Deliverance/DeliveranceMailChimpList.php';
require_once 'Deliverance/DeliveranceMailChimpCampaign.php';

/**
 * @package   Deliverance
 * @copyright 2011-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class DeliveranceMailChimpNewsletter extends DeliveranceNewsletter
{
	// {{{ public properties

	/**
	 * @var string
	 */
	public $mailchimp_campaign_id;

	/**
	 * @var string
	 */
	public $mailchimp_report_url;

	// }}}
	// {{{ public static function getCampaign()

	public static function getMailingLIst(SiteApplication $app)
	{
		return new DeliveranceMailChimpList($app);
	}

	// }}}
	// {{{ public static function getCampaign()

	public static function getCampaignClass(SiteApplication $app, $shortname)
	{
		return new DeliveranceMailChimpCampaign($app, $shortname);
	}

	// }}}
	// {{{ protected function getCampaignId()

	protected function getCampaignId()
	{
		return $this->mailchimp_campaign_id;
	}

	// }}}
}

?>
