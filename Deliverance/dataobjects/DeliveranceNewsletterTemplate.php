<?php

require_once 'SwatDB/SwatDBDataObject.php';
require_once 'Site/dataobjects/SiteInstance.php';

/**
 * A dataobject representing a template to use for a Newsletter
 *
 * @package   Deliverance
 * @copyright 2015-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class DeliveranceNewsletterTemplate extends SwatDBDataObject
{
	// {{{ public properties

	/**
	 * @var integer
	 */
	public $id;

	/**
	 * @var string
	 */
	public $shortname;

	/**
	 * @var string
	 */
	public $title;

	/**
	 * @var int
	 */
	public $displayorder;

	/**
	 * Whether or not to show as an option when building new newsletters.
	 *
	 * @var boolean
	 */
	public $enabled;

	// }}}
	// {{{ protected function init()

	protected function init()
	{
		parent::init();

		$this->registerInternalProperty(
			'instance',
			SwatDBClassMap::get('SiteInstance')
		);

		$this->table = 'NewsletterTemplate';
		$this->id_field = 'integer:id';
	}

	// }}}
}

?>
