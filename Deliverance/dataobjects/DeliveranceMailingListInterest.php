<?php

/**
 * @copyright 2014-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 *
 * @property ?SiteInstance $instance
 */
class DeliveranceMailingListInterest extends SwatDBDataObject
{
    /**
     * Unique identifier.
     *
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $shortname;

    /**
     * @var string
     */
    public $group_shortname;

    /**
     * User visible title.
     *
     * @var string
     */
    public $title;

    /**
     * Order of display.
     *
     * @var int
     */
    public $displayorder;

    /**
     * @var bool
     */
    public $visible;

    /**
     * Whether or not new subscribers should be added by default.
     *
     * @var bool
     */
    public $is_default;

    protected function init()
    {
        parent::init();

        $this->table = 'MailingListInterest';
        $this->id_field = 'integer:id';

        $this->registerInternalProperty(
            'instance',
            SwatDBClassMap::get(SiteInstance::class)
        );
    }
}
