<?php

require_once 'Deliverance/DeliveranceCommandLineApplication.php';

/**
 * Cron job application to update local segment count caches.
 *
 * @package   Deliverance
 * @copyright 2009-2013 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class DeliveranceListCampaignSegmentCacheUpdater
	extends DeliveranceCommandLineApplication
{
	// {{{ public function run()

	public function run()
	{
		parent::run();

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
		$sql = 'select * from MailingListCampaignSegment where instance %s %s';

		$sql = sprintf(
			$sql,
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
