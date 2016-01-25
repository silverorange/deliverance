<?php

require_once 'Site/SiteAmazonCdnModule.php';
require_once 'Deliverance/DeliveranceCampaign.php';
require_once 'Deliverance/DeliveranceCommandLineApplication.php';

/**
 * Builds a campaign from a provided shortname
 *
 * @package   Deliverance
 * @copyright 2010-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class DeliveranceCampaignBuilder
	extends DeliveranceCommandLineApplication
{
	// {{{ protected properties

	/**
	 * @var DeliveranceList
	 */
	protected $list;

	/**
	 * @var DeliveranceCampaign
	 */
	protected $campaign;

	/**
	 * @var string
	 */
	protected $campaign_shortname;

	/**
	 * @var string
	 */
	protected $campaign_dir;

	/**
	 * @var boolean
	 */
	protected $draft = false;

	/**
	 * @var boolean
	 */
	protected $schedule = false;

	// }}}
	// {{{ public funtcion __construct()

	public function __construct($id, $filename, $title, $documentation)
	{
		parent::__construct($id, $filename, $title, $documentation);

		$this->verbosity = self::VERBOSITY_ALL;

		$campaign = new SiteCommandLineArgument(array('-c', '--campaign'),
			'setCampaignShortname', 'Sets the campaign shortname of '.
			'the campaign to build.');

		$campaign->addParameter('string',
			'--campaign expects a single paramater.');

		$this->addCommandLineArgument($campaign);
	}

	// }}}
	// {{{ public function setCampaignShortname()

	public function setCampaignShortname($campaign_shortname)
	{
		$this->campaign_shortname = (string)$campaign_shortname;

		$parts = explode('-', $this->campaign_shortname);
		if (count($parts) == 1) {
			$this->campaign_dir = $parts[0];
		} else {
			$this->campaign_dir = sprintf('%s/%s-%s',
				$parts[0],
				$parts[1],
				$parts[2]);
		}
	}

	// }}}
	// {{{ public function run()

	public function run()
	{
		$this->initModules();
		$this->parseCommandLineArguments();

		if ($this->campaign_shortname === null) {
			$this->terminate("Campaign shortname must be specified using ".
			"--campaign. Use --help for help.\n");
		}

		$this->build();
	}

	// }}}
	// {{{ abstract protected function displayFinalOutput()

	abstract protected function displayFinalOutput();

	// }}}
	// {{{ protected function getSalutations()

	protected function getSalutations()
	{
		$salutations = array(
			'Warning',
		);

		return $salutations;
	}

	// }}}

	// Path Methods
	// {{{ public function getSourceDirectory()

	public function getSourceDirectory()
	{
		return sprintf('./%s%s',
			$this->getInstanceDirectory(),
			$this->campaign_dir);
	}

	// }}}
	// {{{ public function getCommonDirectory()

	public function getCommonDirectory()
	{
		return sprintf('./%s%s',
			$this->getInstanceDirectory(),
			'common');
	}

	// }}}
	// {{{ protected function getBuildDirectory()

	protected function getBuildDirectory()
	{
		return sprintf('campaign/%s%s',
			$this->getInstanceDirectory(),
			$this->campaign_dir);
	}

	// }}}
	// {{{ protected function getInstanceDirectory()

	protected function getInstanceDirectory()
	{
		$instance_directory = ($this->instance === null) ?
			null :
			$this->instance->getInstance()->shortname.'/';

		return $instance_directory;
	}

	// }}}

	// boilerplate
	// {{{ protected function configure()

	protected function configure(SiteConfigModule $config)
	{
		parent::configure($config);

		$this->database->dsn = $config->database->dsn;

		$this->cdn->bucket            = $config->amazon->bucket;
		$this->cdn->access_key_id     = $config->amazon->access_key_id;
		$this->cdn->access_key_secret = $config->amazon->access_key_secret;
	}

	// }}}
	// {{{ protected function getDefaultModuleList()

	protected function getDefaultModuleList()
	{
		$list = parent::getDefaultModuleList();

		$list['cdn'] = 'SiteMultipleInstanceModule';

		return $list;
	}

	// }}}
	// {{{ protected function addConfigDefinitions()

	/**
	 * Adds configuration definitions to the config module of this application
	 *
	 * @param SiteConfigModule $config the config module of this application to
	 *                                  which to add the config definitions.
	 */
	protected function addConfigDefinitions(SiteConfigModule $config)
	{
		parent::addConfigDefinitions($config);
		$config->addDefinitions(Deliverance::getConfigDefinitions());
	}

	// }}}
}

?>
