<?php

require_once 'Deliverance/DeliveranceCampaignBuilder.php';
require_once 'Deliverance/DeliveranceMailChimpList.php';

/**
 * Builds campaigns from provided shortnames, and sets them up on MailChimp
 *
 * @package   Deliverance
 * @copyright 2010 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class DeliveranceMailChimpCampaignBuilder extends
	DeliveranceCampaignBuilder
{
	// {{{ protected properties

	/**
	 * @var boolean
	 */
	protected $timewarp = true;

	/**
	 * @var boolean
	 */
	protected $track_orders = true;

	// }}}
	// {{{ public funtcion __construct()

	public function __construct($id, $filename, $title, $documentation)
	{
		parent::__construct($id, $filename, $title, $documentation);

		$timewarp = new SiteCommandLineArgument(
			array('--no-timewarp'),
			'setNoTimewarp',
			'Tells the builder to turn timewarp off for the campaign.');

		$this->addCommandLineArgument($timewarp);

		$track_orders = new SiteCommandLineArgument(
			array('--no-order-tracking'),
			'setNoOrderTracking',
			'Tells the builder to turn order tracking off for the campaign.');

		$this->addCommandLineArgument($track_orders);
	}

	// }}}
	// {{{ public function setNoTimewarp()

	public function setNoTimewarp()
	{
		$this->timewarp = false;
	}

	// }}}
	// {{{ public function setNoOrderTracking()

	public function setNoOrderTracking()
	{
		$this->track_orders = false;
	}

	// }}}
	// {{{ protected function getList()

	protected function getList()
	{
		return new DeliveranceMailChimpList($this, null, 90000);
	}

	// }}}
	// {{{ protected function displayFinalOutput()

	protected function displayFinalOutput()
	{
		$this->debug(sprintf("\nView the generated campaign at:\n%s\n\n",
			DeliveranceMailChimpCampaign::getPreviewUrl($this->campaign->id)));
	}

	// }}}
}

?>
