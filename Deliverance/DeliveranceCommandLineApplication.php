<?php

require_once 'SwatDB/SwatDB.php';
require_once 'Site/SiteCommandLineApplication.php';
require_once 'Site/SiteDatabaseModule.php';
require_once 'Site/SiteCommandLineConfigModule.php';
require_once 'Site/SiteMultipleInstanceModule.php';
require_once 'Deliverance/Deliverance.php';
require_once 'Deliverance/DeliveranceListFactory.php';

/**
 * Base class for Deliverance commmand line apps.
 *
 * @package   Deliverance
 * @copyright 2013-2015 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class DeliveranceCommandLineApplication
	extends SiteCommandLineApplication
{
	// {{{ protected properties

	protected $dry_run = false;

	// }}}
	// {{{ public function __construct()

	public function __construct($id, $filename, $title, $documentation)
	{
		parent::__construct($id, $filename, $title, $documentation);

		$instance = new SiteCommandLineArgument(array('-i', '--instance'),
			'setInstance', 'Optional. Sets the site instance for which to '.
			'run this application.');

		$instance->addParameter('string',
			'instance name must be specified.');

		$this->addCommandLineArgument($instance);

		$dry_run = new SiteCommandLineArgument(
			array('--dry-run'),
			'setDryRun',
			Deliverance::_('No data is actually modified.'));

		$this->addCommandLineArgument($dry_run);
	}

	// }}}
	// {{{ public function setInstance()

	public function setInstance($shortname)
	{
		putenv(sprintf('instance=%s', $shortname));
		$this->instance->init();
		$this->config->init();
	}

	// }}}
	// {{{ public function setDryRun()

	public function setDryRun($dry_run)
	{
		$this->dry_run = (boolean)$dry_run;
	}

	// }}}
	// {{{ public function run()

	public function run()
	{
		parent::run();

		$this->lock();
		$this->runInternal();
		$this->unlock();
	}

	// }}}
	// {{{ protected function runInternal()

	protected function runInternal()
	{
	}

	// }}}
	// {{{ protected function getList()

	protected function getList()
	{
		$list = DeliveranceListFactory::get($this, 'default');
		$list->setTimeout(
			$this->config->deliverance->list_script_connection_timeout
		);

		return $list;
	}

	// }}}

	// boilerplate
	// {{{ protected function addConfigDefinitions()

	protected function addConfigDefinitions(SiteConfigModule $config)
	{
		parent::addConfigDefinitions($config);
		$config->addDefinitions(Deliverance::getConfigDefinitions());
	}

	// }}}
	// {{{ protected function getDefaultModuleList()

	protected function getDefaultModuleList()
	{
		$list = parent::getDefaultModuleList();

		$list['config']   = 'SiteCommandLineConfigModule';
		$list['database'] = 'SiteDatabaseModule';
		$list['instance'] = 'SiteMultipleInstanceModule';

		return $list;
	}

	// }}}
}

?>
