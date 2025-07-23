<?php

/**
 * Base class for Deliverance commmand line apps.
 *
 * @copyright 2013-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class DeliveranceCommandLineApplication extends SiteCommandLineApplication
{
    protected $dry_run = false;

    public function __construct($id, $filename, $title, $documentation)
    {
        parent::__construct($id, $filename, $title, $documentation);

        $instance = new SiteCommandLineArgument(
            ['-i', '--instance'],
            'setInstance',
            'Optional. Sets the site instance for which to ' .
            'run this application.'
        );

        $instance->addParameter(
            'string',
            'instance name must be specified.'
        );

        $this->addCommandLineArgument($instance);

        $dry_run = new SiteCommandLineArgument(
            ['--dry-run'],
            'setDryRun',
            Deliverance::_('No data is actually modified.')
        );

        $this->addCommandLineArgument($dry_run);
    }

    public function setInstance($shortname)
    {
        putenv(sprintf('instance=%s', $shortname));
        $this->instance->init();
        $this->config->init();
    }

    public function setDryRun($dry_run)
    {
        $this->dry_run = (bool) $dry_run;
    }

    public function run()
    {
        parent::run();

        $this->lock();
        $this->runInternal();
        $this->unlock();
    }

    protected function runInternal()
    {
        // There are command-line applications that extend
        // DeliveranceCommandLineApplication and don't have a run() method
        // defined, so runInternal() cannot be abstract.
    }

    protected function getList()
    {
        $list = DeliveranceListFactory::get($this, 'default');
        $list->setTimeout(
            $this->config->deliverance->list_script_connection_timeout
        );

        return $list;
    }

    // boilerplate

    protected function addConfigDefinitions(SiteConfigModule $config)
    {
        parent::addConfigDefinitions($config);
        $config->addDefinitions(Deliverance::getConfigDefinitions());
    }

    protected function getDefaultModuleList()
    {
        return array_merge(
            parent::getDefaultModuleList(),
            [
                'config'   => SiteCommandLineConfigModule::class,
                'database' => SiteDatabaseModule::class,
                'instance' => SiteMultipleInstanceModule::class,
            ]
        );
    }
}
