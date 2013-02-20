<?php

require_once 'Deliverance/DeliveranceListCampaignSegmentCacheUpdater.php';

/**
 * MailChimp specific application to update segment counts.
 *
 * @package   Deliverance
 * @copyright 2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class DeliveranceMailChimpListCampaignSegmentCacheUpdater
	extends DeliveranceListCampaignSegmentCacheUpdater
{
	// {{{ protected function updateSegment()

	protected function updateSegment(DeliveranceList $list,
		DeliveranceCampaignSegment $segment)
	{
		if ($list->isAvailable()) {
			$this->debug(sprintf(Deliverance::_('Updating ‘%s’... '),
				$segment->title));

			try {
				$size = $list->getSegmentSize($segment->getSegmentOptions());
				$segment->cached_segment_size = $size;
				$segment->save();

				$locale = SwatI18NLocale::get();
				$this->debug(sprintf(
					Deliverance::_('found %s subscribers.')."\n",
					$locale->formatNumber($size)));
			} catch (DeliveranceApiConnectionException $e) {
				// ignore these exceptions. segment sizes will be updated next
				// time around.
				$this->debug(sprintf(
					Deliverance::_(
						'list unavailable. Segment ‘%s’ was not updated.'
					)."\n",
					$segment->title));
			} catch (Exception $e) {
				$e = new DeliveranceException($e);
				$e->processAndContinue();

				$this->debug(sprintf(
					Deliverance::_(
						'Update error. Segment ‘%s’ was not updated.'
					)."\n",
					$segment->title));
			}
		} else {
			$this->debug(sprintf(
				Deliverance::_(
					'Mailing list unavailable. Segment ‘%s’ was not updated.'
				)."\n",
				$segment->title
			));
		}
	}

	// }}}
}

?>
