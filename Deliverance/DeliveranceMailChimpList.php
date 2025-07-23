<?php

use DrewM\MailChimp\MailChimp;
use DrewM\MailChimp\Batch as MailChimpBatch;

/**
 * @package   Deliverance
 * @copyright 2009-2019 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @todo      Handle addresses somehow magically, perhaps add type checking on
 *            merge vars, and allow zip to be passed into an address field by
 *            filling with placeholder data in the other address columns (as
 *            suggested by mailchimp).
 */
class DeliveranceMailChimpList extends DeliveranceList
{


	/**
	 * How many members to batch update at once.
	 *
	 * API docs say 500 pending requests. Not clear if that is 500
	 * in a batch or 500 batches. Go with the small amount.
	 *
	 * @see https://developer.mailchimp.com/documentation/mailchimp/guides/how-to-use-batch-operations/
	 *
	 * @var integer
	 */
	const BATCH_SIZE = 500;

	/**
	 * The amount of time we wait (in seconds) between checking the completeness
	 * of a batch request.
	 *
	 * @var integer
	 */
	const POLLING_INTERVAL = 10;

	/**
	 * Email type preference value for html email.
	 */
	const EMAIL_TYPE_HTML = 'html';

	/**
	 * Email type preference value for text only email.
	 */
	const EMAIL_TYPE_TEXT = 'text';




	public $default_address = array(
		'addr1' => 'null',
		'city'  => 'null',
		'state' => 'null',
		'zip'   => 'null',
		);




	protected $client;

	/**
	 * The timeout length for any MailChimp client request.
	 *
	 * @var integer
	 */
	protected $client_timeout;

	protected $list_merge_array_map = array();

	/**
	 * Email type subscribes wish to receive.
	 *
	 * Valid email types are class constants starting with EMAIL_TYPE_*
	 *
	 * @var string
	 */
	protected $email_type = self::EMAIL_TYPE_HTML;

	/**
	 * @var DeliveranceMailingListInterestWrapper
	 */
	protected $interests;




	public function __construct(SiteApplication $app, $shortname = null)
	{
		parent::__construct($app, $shortname);

		$this->client = new MailChimp($this->getApiKey());

		// by default if the connection takes longer than 1s timeout. This will
		// prevent users from waiting too long when MailChimp is down - requests
		// will just get queued. Without setting this, the default timeout is
		// 10 seconds
		$this->setTimeout($app->config->deliverance->list_connection_timeout);

		if ($this->shortname === null)
			$this->shortname = $app->config->mail_chimp->default_list;

		$this->initListMergeArrayMap();
	}




	public function setEmailType($email_type)
	{
		$this->email_type = $email_type;
	}




	public function setTimeout($timeout)
	{
		$this->client_timeout = intval($timeout);
	}




	/**
	 * Tests to make sure the service is available.
	 *
	 * Returns false if MailChimp returns an unexpected value or the
	 * MailChimpAPI throws an exception. Unexpected values from MailChimp
	 * get thrown in exceptions as well. Any exceptions thrown are not exited
	 * on, so that we can queue requests based on service availability.
	 *
	 * @return boolean whether or not the service is available.
	 */
	public function isAvailable()
	{
		$available = false;

		try {
			$result = $this->callClientMethod('GET', 'ping');

			// Endearing? Yes. But also annoying to have to check for a string.
			$available = ($result['health_status'] === "Everything's Chimpy!");
		} catch (DeliveranceMailChimpTimeoutException $e) {
			// exception is known, API is not available.
		}

		return $available;
	}




	protected function initListMergeArrayMap()
	{
		$this->list_merge_array_map = array(
			'email'      => 'EMAIL', // only used for batch subscribes
			'first_name' => 'FNAME',
			'last_name'  => 'LNAME',
			'user_ip'    => 'OPTIN_IP'
		);
	}



	// subscriber methods


	public function subscribe($address, array $info = array())
	{
		$result = false;
		$queue_request = false;

		if ($this->isAvailable()) {
			$merges = $this->mergeInfo($info);
			$interests = $this->interestInfo($info);

			try {
				$result = $this->callClientMethod(
					'PUT',
					sprintf(
						'lists/%s/members/%s',
						$this->shortname,
						$this->client->subscriberHash($address)
					),
					[
						'email_address' => $address,
						'email_type' => $this->email_type,
						'status' => 'subscribed',
						'merge_fields' => $merges,
						'interests' => $interests
					]
				);
			} catch (DeliveranceMailChimpTimeoutException $e) {
				$queue_request = true;
			} catch (DeliveranceMailChimpServerException $e) {
				$queue_request = true;
			} catch (DeliveranceMailChimpClientException $e) {
				$e->processAndContinue();

				$result = DeliveranceList::FAILURE;
			} catch (Exception $e) {
				throw new DeliveranceException($e);
			}
		} else {
			$queue_request = true;
		}

		if ($queue_request && $this->app->hasModule('SiteDatabaseModule')) {
			$result = $this->queueSubscribe($address, $info);
		}

		if ($result === true) {
			$result = self::SUCCESS;
		} elseif ($result === false) {
			$result = self::FAILURE;
		}

		return $result;
	}




	public function batchSubscribe(array $addresses)
	{
		$success_ids = [];

		if ($this->isAvailable()) {
			$count = 0;

			$batch = new MailChimpBatch($this->client);
			foreach ($addresses as $info) {
				$count++;

				$merges = $this->mergeInfo($info);
				$interests = $this->interestInfo($info);

				$batch->put(
					strval($info['id']),
					sprintf(
						'lists/%s/members/%s',
						$this->shortname,
						$this->client->subscriberHash($info['email'])
					),
					[
						'email_address' => $info['email'],
						'email_type' => $this->email_type,
						'status' => 'subscribed',
						'merge_fields' => $merges,
						'interests' => $interests
					]
				);

				$full_batch = ($count % self::BATCH_SIZE === 0);
				$last_entry = ($count === count($addresses));

				if (($full_batch || $last_entry) && ($count > 0)) {
					try {
						$batch->execute();
						$this->handleClientErrors();

						do {
							sleep(self::POLLING_INTERVAL);

							$result = $batch->check_status();
							$this->handleClientErrors();
						} while ($result['status'] !== 'finished');

						foreach ($batch->get_operations() as $operation) {
							$success_ids[] = intval($operation['operation_id']);
						}

						$batch = new MailChimpBatch($this->client);
					} catch (DeliveranceMailChimpTimeoutException $e) {
						// If we catch an exception we process it and break from
						// the loop. Any sucessfully processed IDs will be returned
						// and the rest will stay in the queue.
						break;
					} catch (DeliveranceException $e) {
						$e->processAndContinue();
						break;
					}
				}
			}
		}

		return $success_ids;
	}




	public function update($address, array $info = array())
	{
		$result = false;
		$queue_request = false;

		if ($this->isAvailable()) {
			$merges = $this->mergeInfo($info);
			$interests = $this->interestInfo($info);

			try {
				$result = $this->callClientMethod(
					'PATCH',
					sprintf(
						'lists/%s/members/%s',
						$this->shortname,
						$this->client->subscriberHash($address)
					),
					[
						'email_address' => $address,
						'merge_fields' => $merges,
						'interests' => $interests
					]
				);
			} catch (DeliveranceMailChimpTimeoutException $e) {
				$queue_request = true;
			} catch (DeliveranceMailChimpServerException $e) {
				$queue_request = true;
			} catch (DeliveranceMailChimpClientException $e) {
				$result = DeliveranceList::INVALID;
			} catch (Exception $e) {
				throw new DeliveranceException($e);
			}
		} else {
			$queue_request = true;
		}

		if ($queue_request && $this->app->hasModule('SiteDatabaseModule')) {
			$result = $this->queueUpdate($address, $info);
		}

		if ($result === true) {
			$result = self::SUCCESS;
		} elseif ($result === false) {
			$result = self::FAILURE;
		}

		return $result;
	}




	public function batchUpdate(array $addresses)
	{
		$success_ids = [];

		if ($this->isAvailable()) {
			$count = 0;

			$batch = new MailChimpBatch($this->client);
			foreach ($addresses as $info) {
				$count++;

				$merges = $this->mergeInfo($info);
				$interests = $this->interestInfo($info);

				$batch->patch(
					strval($info['id']),
					sprintf(
						'lists/%s/members/%s',
						$this->shortname,
						$this->client->subscriberHash($info['email'])
					),
					[
						'email_address' => $info['email'],
						'merge_fields' => $merges,
						'interests' => $interests
					]
				);

				$full_batch = ($count % self::BATCH_SIZE === 0);
				$last_entry = ($count === count($addresses));

				if (($full_batch || $last_entry) && ($count > 0)) {
					try {
						$batch->execute();
						$this->handleClientErrors();

						do {
							sleep(self::POLLING_INTERVAL);

							$result = $batch->check_status();
							$this->handleClientErrors();
						} while ($result['status'] !== 'finished');

						foreach ($batch->get_operations() as $operation) {
							$success_ids[] = intval($operation['operation_id']);
						}

						$batch = new MailChimpBatch($this->client);
					} catch (DeliveranceMailChimpTimeoutException $e) {
						// If we catch an exception we process it and break from
						// the loop. Any sucessfully processed IDs will be returned
						// and the rest will stay in the queue.
						break;
					} catch (DeliveranceException $e) {
						$e->processAndContinue();
						break;
					}
				}
			}
		}

		return $success_ids;
	}




	public function unsubscribe($address)
	{
		$result = false;
		$queue_request = false;

		if ($this->isAvailable()) {
			try {
				$result = $this->callClientMethod(
					'PATCH',
					sprintf(
						'lists/%s/members/%s',
						$this->shortname,
						$this->client->subscriberHash($address)
					),
					[
						'email_address' => $address,
						'status' => 'unsubscribed',
					]
				);
			} catch (DeliveranceMailChimpTimeoutException $e) {
				$queue_request = true;
			} catch (DeliveranceMailChimpClientException $e) {
				// gracefully handle exceptions that we can provide nice
				// feedback about.
				switch ($e->getCode()) {
				case 404:
					$result = DeliveranceList::NOT_FOUND;
					break;

				default:
					throw $e;
				}
			} catch (Exception $e) {
				throw new DeliveranceException($e);
			}
		} else {
			$queue_request = true;
		}

		if ($queue_request && $this->app->hasModule('SiteDatabaseModule')) {
			$result = $this->queueUnsubscribe($address);
		}

		if ($result === true) {
			$result = self::SUCCESS;
		} elseif ($result === false) {
			$result = self::FAILURE;
		}

		return $result;
	}




	public function batchUnsubscribe(array $addresses)
	{
		$success_ids = [];

		if ($this->isAvailable()) {
			$count = 0;

			$batch = new MailChimpBatch($this->client);
			foreach ($addresses as $id => $email) {
				$count++;

				$batch->patch(
					strval($id),
					sprintf(
						'lists/%s/members/%s',
						$this->shortname,
						$this->client->subscriberHash($email)
					),
					[
						'email_address' => $email,
						'status' => 'unsubscribed',
					]
				);

				$full_batch = ($count % self::BATCH_SIZE === 0);
				$last_entry = ($count === count($addresses));

				if (($full_batch || $last_entry) && ($count > 0)) {
					try {
						$batch->execute();
						$this->handleClientErrors();

						do {
							sleep(self::POLLING_INTERVAL);

							$result = $batch->check_status();
							$this->handleClientErrors();
						} while ($result['status'] !== 'finished');

						foreach ($batch->get_operations() as $operation) {
							$success_ids[] = intval($operation['operation_id']);
						}

						$batch = new MailChimpBatch($this->client);
					} catch (DeliveranceMailChimpTimeoutException $e) {
						// If we catch an exception we process it and break from
						// the loop. Any sucessfully processed IDs will be returned
						// and the rest will stay in the queue.
						break;
					} catch (DeliveranceException $e) {
						$e->processAndContinue();
						break;
					}
				}
			}
		}

		return $success_ids;
	}




	public function isMember($address)
	{
		// Status of subscribed is the only way we can validate a current member
		return $this->isSubscribedMember($this->getMemberInfo($address));
	}




	public function wasMember($address)
	{
		return $this->isUnsubscribedMember($this->getMemberInfo($address));
	}




	public function hasEverBeenMember($address)
	{
		$info = $this->getMemberInfo($address);

		return (
			$this->isSubscribedMember($info) ||
			$this->isUnsubscribedMember($info)
		);
	}




	public function getMemberInfo($address)
	{
		$member_info = null;

		if ($this->isAvailable()) {
			try {
				$member_info = $this->callClientMethod(
					'GET',
					sprintf(
						'lists/%s/members/%s',
						$this->shortname,
						$this->client->subscriberHash($address)
					)
				);

			} catch (DeliveranceMailChimpTimeoutException $e) {
				// Ignore timeouts
			} catch (DeliveranceMailChimpClientException $e) {
				// Ignore 400 level server exceptions
			}
		}

		return $member_info;
	}




	protected function mergeInfo(array $info)
	{
		$array_map = $this->list_merge_array_map;

		$merges = new stdClass();
		foreach ($info as $id => $value) {
			if (array_key_exists($id, $array_map) && $value != null) {
				$merges->{$array_map[$id]} = $value;
			}
		}

		return $merges;
	}




	protected function interestInfo(array $info)
	{
		$interests = new stdClass();

		$selected_interests = array_key_exists('interests', $info) ?
			$info['interests'] : [];

		$deactivated_interests = array_key_exists('deactivated_interests', $info) ?
			$info['deactivated_interests'] : [];

		foreach ($deactivated_interests as $deactivated_interest) {
			$interests->{$deactivated_interest} = false;
		}

		foreach ($selected_interests as $interest) {
			$interests->{$interest} = true;
		}

		return $interests;
	}




	public function getDefaultAddress()
	{
		// TODO: do this better somehow
		return $this->default_address;
	}




	protected function isSubscribedMember($member_info)
	{
		return (
			is_array($member_info) &&
			isset($member_info['status']) &&
			$member_info['status'] === 'subscribed'
		);
	}




	protected function isUnsubscribedMember($member_info)
	{
		return (
			is_array($member_info) &&
			isset($member_info['status']) &&
			$member_info['status'] === 'unsubscribed'
		);
	}



	// interest methods


	public function getDefaultSubscriberInfo()
	{
		$info = array('user_ip' => $this->app->getRemoteIP());

		$interests = $this->getInterests()->getDefaultShortnames();
		if (count($interests) > 0) {
			$info['interests'] = $interests;
		}

		return $info;
	}




	public function getInterests()
	{
		$class_name = SwatDBClassMap::get(
			'DeliveranceMailingListInterestWrapper'
		);

		if ($this->app->hasModule('SiteDatabaseModule') &&
			!($this->interests instanceof $class_name)) {

			$instance_id = $this->app->getInstanceId();

			$this->interests = SwatDB::query(
				$this->app->db,
				sprintf(
					'select * from MailingListInterest
					where instance %s %s order by displayorder',
					SwatDB::equalityOperator($instance_id),
					$this->app->db->quote($instance_id, 'integer')
				),
				$class_name
			);
		}

		return $this->interests;
	}



	// list setup helper methods


	public function getApiKey()
	{
		return $this->app->config->mail_chimp->api_key;
	}



	// exception throwing and handling


	private function callClientMethod($verb, $method, array $args = array())
	{
		switch ($verb) {
		case 'DELETE':
			$result = $this->client->delete(
				$method,
				$args,
				$this->client_timeout
			);

			break;
		case 'GET':
			$result = $this->client->get(
				$method,
				$args,
				$this->client_timeout
			);

			break;
		case 'PATCH':
			$result = $this->client->patch(
				$method,
				$args,
				$this->client_timeout
			);

			break;
		case 'POST':
			$result = $this->client->post(
				$method,
				$args,
				$this->client_timeout
			);

			break;
		case 'PUT':
			$result = $this->client->put(
				$method,
				$args,
				$this->client_timeout
			);

			break;
		default:
			throw new DeliveranceException(
				sprintf('Unknown HTTP verb ‘%s’ used.', $verb)
			);
		}

		$this->handleClientErrors();

		return $result;
	}




	private function handleClientErrors()
	{
		if (!$this->client->success()) {
			$last_response = $this->client->getLastResponse();

			if ($last_response['headers']['total_time'] > $this->client_timeout) {
				throw new DeliveranceMailChimpTimeoutException(
					sprintf(
						'The connection to the MailChimp '.
						'API timed out after ‘%s’ seconds.',
						$this->client_timeout
					)
				);
			}

			$error = json_decode($last_response['body']);
			if ($error === null) {
				throw new DeliveranceException(
					sprintf(
						'Unable to decode JSON received from MailChimp. '.
						'See the following response for more details: %s',
						print_r($last_response, true)
					)
				);
			}

			// Server exceptions
			if ($last_response['headers']['http_code'] >= 500) {
				throw new DeliveranceMailChimpServerException(
					sprintf('%s: %s', $error->title, $error->detail),
					$error->status
				);
			}

			// Client exceptions
			if ($last_response['headers']['http_code'] >= 400) {
				throw new DeliveranceMailChimpClientException(
					sprintf('%s: %s', $error->title, $error->detail),
					$error->status
				);
			}

			// Unknown exception - provide as much detail as possible.
			throw new DeliveranceException(
				sprintf(
					'An unknown error occured when connecting to MailChimp. '.
					'See the following response for more details: %s',
					print_r($last_response, true)
				)
			);
		}
	}


}

?>
