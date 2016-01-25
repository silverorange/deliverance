<?php

require_once 'Deliverance/DeliveranceCommandLineApplication.php';

/**
 * Cron job application to update local segment count caches.
 *
 * @package   Deliverance
 * @copyright 2009-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class DeliveranceListCampaignSegmentCacheUpdater
	extends DeliveranceCommandLineApplication
{
	// {{{ protected properties

	protected $force_all = false;

	// }}}
	// {{{ public function __construct()

	public function __construct($id, $filename, $title, $documentation)
	{
		parent::__construct($id, $filename, $title, $documentation);

		$force_all = new SiteCommandLineArgument(
			array('--force-all'),
			'setForceAll',
			Deliverance::_(
				'Force cache updates on all segments, ignoring enabled field.'
			)
		);

		$this->addCommandLineArgument($force_all);
	}

	// }}}
	// {{{ public function setInstance()

	public function setForceAll()
	{
		$this->force_all = true;
	}

	// }}}
	// {{{ protected function runInternal()

	protected function runInternal()
	{
		parent::runInternal();

		$list     = $this->getList();
		$segments = $this->getSegments();

		if (count($segments)) {
			$this->debug(
				Deliverance::_('Updating Segment Counts')."\n\n",
				true
			);

			foreach ($segments as $segment) {
				$this->updateSegment($list, $segment);
			}
		} else {
			$this->debug(Deliverance::_('No segments found. '), true);
		}

		$this->debug(Deliverance::_('All Done.')."\n", true);
	}

	// }}}
	// {{{ protected function getSegments()

	protected function getSegments()
	{
		$sql = 'select * from MailingListCampaignSegment
			where %s and instance %s %s';

		$sql = sprintf(
			$sql,
			($this->force_all)
				? '1 = 1'
				: sprintf(
					'enabled = %s',
					$this->db->quote(true, 'boolean')
				),
			SwatDB::equalityOperator($this->getInstanceId()),
			$this->db->quote($this->getInstanceId(), 'integer')
		);

		return SwatDB::query(
			$this->db,
			$sql,
			SwatDBClassMap::get('DeliveranceCampaignSegmentWrapper')
		);
	}

	// }}}
	// {{{ abstract protected function updateSegment()

	abstract protected function updateSegment(DeliveranceList $list,
		DeliveranceCampaignSegment $segment);

	// }}}
}

?>
