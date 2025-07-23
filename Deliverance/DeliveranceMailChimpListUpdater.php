<?php

/**
 * MailChimp specific application to update mailing list with new and queued
 * subscriber requests.
 *
 * @package   Deliverance
 * @copyright 2009-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class DeliveranceMailChimpListUpdater extends DeliveranceListUpdater
{


	protected function handleResult($result, $success_message)
	{
		$clear_queued = parent::handleResult($result, $success_message);

		if (is_array($result)) {
			$clear_queued = true;

			$this->debug(sprintf($success_message,
				$result['success_count']));

			// add count doesn't always exist.
			if (isset($result['add_count']) && $result['add_count']) {
				$this->debug(
					sprintf(
						Deliverance::_('%s addresses added.')."\n",
						$result['add_count']
					)
				);
			}

			// update count doesn't always exist.
			if (isset($result['update_count']) && $result['update_count']) {
				$this->debug(
					sprintf(
						Deliverance::_('%s addresses updated.')."\n",
						$result['update_count']
					)
				);
			}

			// Queued requests can exist in errors or in the result message
			// depending on the request type.
			$queued_count = 0;
			if (isset($result['queued_count']) && $result['queued_count']) {
				$queued_count = $result['queued_count'];
			}

			if ($result['error_count']) {
				$errors = array();
				$not_found_count = 0;
				$bounced_count = 0;
				$previously_unsubscribed_count = 0;
				$invalid_count = 0;

				// don't throw errors for codes we know can be ignored.
				foreach ($result['errors'] as $error) {
					switch ($error['code']) {
					case DeliveranceMailChimpList::NOT_FOUND_ERROR_CODE:
					case DeliveranceMailChimpList::NOT_SUBSCRIBED_ERROR_CODE:
						$not_found_count++;
						break;

					case DeliveranceMailChimpList::PREVIOUSLY_UNSUBSCRIBED_ERROR_CODE:
						$previously_unsubscribed_count++;
						break;

					case DeliveranceMailChimpList::BOUNCED_ERROR_CODE:
						$bounced_count++;
						break;

					case DeliveranceMailChimpList::INVALID_ADDRESS_ERROR_CODE:
						$invalid_count++;
						break;

					case DeliveranceList::QUEUED:
						$queued_count++;
						break;

					default:
						$error_message = sprintf(
							Deliverance::_('code: %s - message: %s.'),
							$error['code'],
							$error['message']);

						$errors[]  = $error_message;
						$execption = new SiteException($error_message);
						// don't exit on returned errors
						$execption->processAndContinue();
					}
				}

				if ($not_found_count > 0) {
					$this->debug(
						sprintf(
							Deliverance::_('%s addresses not found.')."\n",
							$not_found_count
						)
					);
				}

				if ($previously_unsubscribed_count > 0) {
					$this->debug(
						sprintf(
							Deliverance::_(
								'%s addresses have previously subscribed, '.
								'and cannot be resubscribed.'
							)."\n",
							$previously_unsubscribed_count
						)
					);
				}

				if ($bounced_count > 0) {
					$this->debug(
						sprintf(
							Deliverance::_(
								'%s addresses have bounced, and cannot be '.
								'resubscribed.'
							)."\n",
							$bounced_count
						)
					);
				}

				if ($invalid_count > 0) {
					$this->debug(
						sprintf(
							Deliverance::_('%s invalid addresses.')."\n",
							$invalid_count
						)
					);
				}

				if (count($errors)) {
					$this->debug(
						sprintf(
							Deliverance::_('%s errors:')."\n",
							count($errors)
						)
					);

					foreach ($errors as $error) {
						$this->debug($error."\n");
					}
				}
			}

			if ($queued_count > 0) {
				$clear_queued = false;
				$this->debug(
					sprintf(
						Deliverance::_('%s addresses queued.')."\n",
						$queued_count
					)
				);
			}
		}

		return $clear_queued;
	}


}

?>
