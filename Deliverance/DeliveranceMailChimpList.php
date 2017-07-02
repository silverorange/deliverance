<?php

/**
 * @package   Deliverance
 * @copyright 2009-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @todo      Handle addresses somehow magically, perhaps add type checking on
 *            merge vars, and allow zip to be passed into an address field by
 *            filling with placeholder data in the other address columns (as
 *            suggested by mailchimp).
 */
class DeliveranceMailChimpList extends DeliveranceList
{
	// {{{ class constants

	/**
	 * How many members to batch update at once.
	 *
	 * Must be kept low enough to not timeout. API docs say cap batch updates
	 * between 5k-10k.
	 *
	 * @var integer
	 */
	const BATCH_UPDATE_SIZE = 5000;

	/**
	 * Error code returned when attempting to subscribe an email address that
	 * has previously unsubscribed. We can't programatically resubscribe them,
	 * MailChimp requires them to resubscribe out of their own volition.
	 */
	const CONCURRENT_CONNECTION_ERROR_CODE = -50;

	/**
	 * Error code returned by MailChimp due to transient database errors on
	 * their systems. Requests that change subscriber info that get this
	 * exception should be queued, so consider this a
	 * DeliveranceAPIConnectionException.
	 */
	const BACKEND_DB_ERROR = -91;

	/**
	 * Error code returned when the connection has timed out.
	 */
	const CONNECTION_TIMEOUT_ERROR_CODE = -98;

	/**
	 * Error code returned when we could not connect to the API.
	 */
	const CONNECTION_ERROR_CODE = -99;

	/**
	 * Error code returned when account is under maintenance and unavailable.
	 */
	const ACCOUNT_MAINTENANCE_ERROR_CODE = 105;

	/**
	 * Error code by an invalid call. MailChimp is currently returning this for
	 * maintenance sometimes as well.
	 */
	const ACCOUNT_INVALID_ACTION_ERROR_CODE = 120;

	/**
	 * Error code returned when attempting to subscribe an email address that
	 * has previously unsubscribed. We can't programatically resubscribe them,
	 * MailChimp requires them to resubscribe out of their own volition.
	 */
	const PREVIOUSLY_UNSUBSCRIBED_ERROR_CODE = 212;

	/**
	 * Error code returned when attempting to subscribe an email address that
	 * has bounced in the past, so can't be resubscribed.
	 */
	const BOUNCED_ERROR_CODE = 213;

	/**
	 * Error code returned when attempting to unsubscribe an email address that
	 * is not a current member of the list.
	 */
	const NOT_SUBSCRIBED_ERROR_CODE = 215;

	/**
	 * Error code returned when attempting to unsubscribe an email address that
	 * was never a member of the list.
	 */
	const NOT_FOUND_ERROR_CODE = 232;

	/**
	 * Error code returned when attempting to subscribe an email address that
	 * has been banned by MailChimp. Banned addresses are per-list, although why
	 * MailChimp bans them is unknown.
	 */
	const BANNED_ADDRESS_ERROR_CODE = 220;

	/**
	 * Error code returned when attempting to subscribe an email address that
	 * is not a valid email address.
	 */
	const INVALID_ADDRESS_ERROR_CODE = 502;

	/**
	 * Error code returned when attempting to unschedule a campaign that hasn't
	 * yet been scheduled.
	 */
	const CAMPAIGN_NOT_SCHEDULED_ERROR = 313;

	/**
	 * Error code returned when attempting to delete a campaign that doesn't
	 * exist.
	 */
	const CAMPAIGN_DOES_NOT_EXIST = 300;

	/**
	 * Error code returned when campaign stats haven't been generated yet. This
	 * occurs if you try to get the stats before a mailing has finished being
	 * sent to all subscribers.
	 */
	const CAMPAIGN_STATS_NOT_AVAILABLE = 301;

	/**
	 * Error code returned when attempting to add an order that already has been
	 * added.
	 */
	const PREVIOUSLY_ADDED_ORDER_ERROR_CODE = 330;

	/**
	 * Email type preference value for html email.
	 */
	const EMAIL_TYPE_HTML = 'html';

	/**
	 * Email type preference value for text only email.
	 */
	const EMAIL_TYPE_TEXT = 'text';

	// }}}
	// {{{ public properties

	public $default_address = array(
		'addr1' => 'null',
		'city'  => 'null',
		'state' => 'null',
		'zip'   => 'null',
		);

	// }}}
	// {{{ protected properties

	protected $client;
	protected $list_merge_array_map = array();

	/**
	 * Whether or not to require double opt in on subscribe.
	 *
	 * @var boolean
	 */
	protected $double_opt_in = false;

	/**
	 * Whether or not to replace interests on subscribe.
	 *
	 * If true, existing interests are replaced by the interests passed in. If
	 * false, the new interests are merged with the existing ones.
	 *
	 * @var boolean
	 */
	protected $replace_interests = false;

	/**
	 * Whether or not to update existing members on subscribe.
	 *
	 * If true, the member is updated with the new information, if false, an
	 * error is thrown.
	 *
	 * @var boolean
	 */
	protected $update_existing = true;

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

	// }}}
	// {{{ private properties

	/**
	 * Error codes returned by the api related to connection issues.
	 *
	 * This is used to create and filter exceptions we know are safe to ignore.
	 * Note: ACCOUNT_INVALID_ACTION_ERROR_CODE is only in this list due to an
	 * open API issue on MailChimp's end where that code is erroneously returned
	 * for the ACCOUNT_MAINTENANCE_ERROR_CODE error. Remove as soon as the API
	 * issue is fixed.
	 *
	 * @var array
	 */
	private $connection_errors = array(
		self::CONNECTION_ERROR_CODE,
		self::CONNECTION_TIMEOUT_ERROR_CODE,
		self::CONCURRENT_CONNECTION_ERROR_CODE,
		self::ACCOUNT_MAINTENANCE_ERROR_CODE,
		self::ACCOUNT_INVALID_ACTION_ERROR_CODE,
		self::BACKEND_DB_ERROR,
		);

	// }}}
	// {{{ public static function getDataCenter()

	public static function getDataCenter($api_key)
	{
		$api_key_parts = explode('-', $api_key, 2);

		// if datacenter isn't set as part of the key, default to us1
		return (isset($api_key_parts[1])) ?
			$api_key_parts[1] :
			'us1';
	}

	// }}}
	// {{{ public function __construct()

	public function __construct(SiteApplication $app, $shortname = null)
	{
		parent::__construct($app, $shortname);

		$this->client = new MailChimpAPI($this->getApiKey());

		$this->client->useSecure(true);

		// by default if the connection takes longer than 1s timeout. This will
		// prevent users from waiting too long when MailChimp is down - requests
		// will just get queued. Without setting this, the default timeout is
		// 300 seconds
		$this->client->setTimeout(
			$app->config->deliverance->list_connection_timeout);

		if ($this->shortname === null)
			$this->shortname = $app->config->mail_chimp->default_list;

		$this->initListMergeArrayMap();

		// default double_opt_in to the config var.
		$this->double_opt_in = $this->app->config->mail_chimp->double_opt_in;
	}

	// }}}
	// {{{ public function setDoubleOptIn()

	public function setDoubleOptIn($double_opt_in)
	{
		$this->double_opt_in = $double_opt_in;
	}

	// }}}
	// {{{ public function setReplaceInterests()

	public function setReplaceInterests($replace_interests)
	{
		$this->replace_interests = $replace_interests;
	}

	// }}}
	// {{{ public function setUpdateExisting()

	public function setUpdateExisting($update_existing)
	{
		$this->update_existing = $update_existing;
	}

	// }}}
	// {{{ public function setEmailType()

	public function setEmailType($email_type)
	{
		$this->email_type = $email_type;
	}

	// }}}
	// {{{ public function setTimeout()

	public function setTimeout($timeout)
	{
		$timeout = intval($timeout);
		$this->client->setTimeout($timeout);
	}

	// }}}
	// {{{ public function isAvailable()

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
			$result = $this->callClientMethod('ping');

			// Endearing? Yes. But also annoying to have to check for a string.
			if ($result === "Everything's Chimpy!") {
				$available = true;
			}
		} catch (DeliveranceAPIConnectionException $e) {
			// exception is known, API is not available.
		} catch (Exception $e) {
			// If its not a known exception type, log it but still return
			// unavailable
			$e = new DeliveranceException($e);
			$e->processAndContinue();
		}

		return $available;
	}

	// }}}
	// {{{ protected function initListMergeArrayMap()

	protected function initListMergeArrayMap()
	{
		$this->list_merge_array_map = array(
			'email'      => 'EMAIL', // only used for batch subscribes
			'first_name' => 'FNAME',
			'last_name'  => 'LNAME',
			'user_ip'    => 'OPTIN_IP',
			'interests'  => 'INTERESTS',
		);
	}

	// }}}

	// subscriber methods
	// {{{ public function subscribe()

	public function subscribe(
		$address,
		array $info = array(),
		$send_welcome = true,
		array $array_map = array()
	) {
		$result = false;
		$queue_request = false;

		if ($this->isAvailable()) {
			$merges     = $this->mergeInfo($info, $array_map);
			$parameters = array(
				$this->shortname,
				$address,
				$merges,
				$this->email_type,
				$this->double_opt_in,
				$this->update_existing,
				$this->replace_interests,
				$send_welcome,
				);

			try {
				$result = $this->callClientMethod(
					'listSubscribe',
					$parameters);
			} catch (DeliveranceAPIConnectionException $e) {
				// exception is known, API is not available.
				$queue_request = true;
			} catch (DeliveranceException $e) {
				// gracefully handle exceptions that we can provide nice
				// feedback about.
				switch ($e->getCode()) {
				case self::INVALID_ADDRESS_ERROR_CODE:
					$result = DeliveranceList::INVALID;
					break;

				case self::BANNED_ADDRESS_ERROR_CODE:
					// log these to keep track of how frequent they are. If they
					// become frequent we should build a better message for the
					// user.
					$e = new DeliveranceBannedAddressException($e);
					$e->processAndContinue();

					$result = DeliveranceList::INVALID;
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

		if ($queue_request === true &&
			$this->app->hasModule('SiteDatabaseModule')) {
			$result = $this->queueSubscribe($address, $info, $send_welcome);
		}

		if ($result === true) {
			$result = self::SUCCESS;
		} elseif ($result === false) {
			$result = self::FAILURE;
		}

		return $result;
	}

	// }}}
	// {{{ public function batchSubscribe()

	public function batchSubscribe(
		array $addresses,
		$send_welcome = false,
		array $array_map = array()
	) {
		$result = false;
		$queue_request = false;

		if ($this->isAvailable()) {
			// Match MailChimp's return array structure plus added array keys
			// for when we have to queue only part of the batch subscribe. If
			// the entire request is queued, the method returns the QUEUED
			// constant instead of this array.
			$result = array(
				'success_count' => 0,
				'add_count'     => 0,
				'update_count'  => 0,
				'error_count'   => 0,
				'errors'        => array(),
				'queued_count'  => 0,
				'queued'        => array(),
				);

			// MailChimp doesn't allow welcomes to be sent on batch subscribes.
			// So if we need to send them, do individual subscribes instead, and
			// update the result queue to match what a batchSubscribe would
			// return. Any queuing on individual subscribes is handled by the
			// subscribe() method.
			if ($send_welcome === true) {
				foreach ($addresses as $info) {
					$is_member      = $this->isMember($info['email']);
					$current_result = $this->subscribe($info['email'], $info,
						$send_welcome, $array_map);

					switch ($current_result) {
					case self::SUCCESS:
						$result['success_count']++;
						if ($is_member) {
							$result['update_count']++;
						} else {
							$result['add_count']++;
						}
						break;

					default:
						// Match MailChimp's batch subscribe error structure.
						$result['error_count']++;
						$result['errors'][] = array(
							'email'   => $info['email'],
							'code'    => $current_result,
							'message' => sprintf('Error subscribing %s',
								$info['email']),
						);
					}
				}
			} else {
				$addresses_chunk  = array();
				$queued_addresses = array();
				$address_count    = count($addresses);
				$current_count    = 0;

				foreach ($addresses as $info) {
					$current_count++;

					$merges = $this->mergeInfo($info, $array_map);
					if (count($merges)) {
						$addresses_chunk[] = $merges;
					}

					if (count($addresses_chunk) === self::BATCH_UPDATE_SIZE ||
						$current_count == $address_count) {
						$queue_current_request = false;
						$parameters = array(
							$this->shortname,
							$addresses_chunk,
							$this->double_opt_in,
							$this->update_existing,
							$this->replace_interests,
							);

						// subscribe the current chunk
						try {
							$current_result = $this->callClientMethod(
								'listBatchSubscribe',
								$parameters);
						} catch (DeliveranceAPIConnectionException $e) {
							$queue_current_request = true;
							$queued_addresses = array_merge(
								$queued_addresses,
								$addresses_chunk);
						} catch (Exception $e) {
							throw new DeliveranceException($e);
						}

						if ($queue_current_request === false) {
							$result['success_count'] +=
								$current_result['add_count'] +
								$current_result['update_count'];

							$result['add_count'] +=
								$current_result['add_count'];

							$result['update_count'] +=
								$current_result['update_count'];

							$result['error_count'] +=
								$current_result['error_count'];

							$result['errors'] = array_merge(
								$result['errors'],
								$current_result['errors']);
						}

						$addresses_chunk = array();
					}
				}

				// If all batch requests have timed out, queue the entire
				// request. Otherwise, just queue and report on the timed out
				// batches.
				$queued_address_count = count($queued_addresses);
				if ($queued_address_count == $address_count) {
					$queue_request = true;
				} elseif ($queued_address_count &&
					$this->app->hasModule('SiteDatabaseModule')) {
					$this->queueBatchSubscribe($queued_addresses,
						$send_welcome);

					// treat the queueing as a special case.
					$result['queued_count']     = $queued_address_count;
					$result['queued_addresses'] = $queued_addresses;
				}
			}
		} else {
			$queue_request = true;
		}

		if ($queue_request === true &&
			$this->app->hasModule('SiteDatabaseModule')) {
			$result = $this->queueBatchSubscribe($addresses, $send_welcome);
		}

		return $result;
	}

	// }}}
	// {{{ public function unsubscribe()

	public function unsubscribe($address)
	{
		$result = false;
		$queue_request = false;

		if ($this->isAvailable()) {
			$parameters = array(
				$this->shortname,
				$address,
				false, // delete_member
				false, // send_goodbye
				);

			try {
				$result = $this->callClientMethod(
					'listUnsubscribe',
					$parameters);
			} catch (DeliveranceAPIConnectionException $e) {
				$queue_request = true;
			} catch (DeliveranceException $e) {
				// gracefully handle exceptions that we can provide nice
				// feedback about.
				switch ($e->getCode()) {
				case self::NOT_FOUND_ERROR_CODE:
					$result = DeliveranceList::NOT_FOUND;
					break;

				case self::NOT_SUBSCRIBED_ERROR_CODE:
					$result = DeliveranceList::NOT_SUBSCRIBED;
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

		if ($queue_request === true &&
			$this->app->hasModule('SiteDatabaseModule')) {
			$result = $this->queueUnsubscribe($address);
		}

		if ($result === true) {
			$result = self::SUCCESS;
		} elseif ($result === false) {
			$result = self::FAILURE;
		}

		return $result;
	}

	// }}}
	// {{{ public function batchUnsubscribe()

	public function batchUnsubscribe(array $addresses)
	{
		$result = false;
		$queue_request = false;

		if ($this->isAvailable()) {
			$addresses_chunk  = array();
			$address_count    = count($addresses);
			$current_count    = 0;
			$queued_addresses = array();

			// Match MailChimp's return array structure plus added array keys
			// for when we have to queue only part of the batch. If the entire
			// request is queued, the method returns the QUEUED constant instead
			// of this array.
			$result = array(
				'success_count' => 0,
				'error_count'   => 0,
				'errors'        => array(),
				'queued_count'  => 0,
				'queued'        => array(),
				);

			foreach ($addresses as $email) {
				$current_count++;
				$addresses_chunk[] = $email;

				if (count($addresses_chunk) === self::BATCH_UPDATE_SIZE ||
					$current_count == $address_count) {
					$queue_current_request = false;
					$parameters = array(
						$this->shortname,
						$addresses_chunk,
						false, // delete_member
						false, // send_goodbye
						);

					// unsubscribe the current chunk
					try {
						$current_result = $this->callClientMethod(
							'listBatchUnsubscribe',
							$parameters);
					} catch (DeliveranceAPIConnectionException $e) {
						$queue_current_request = true;
						$queued_addresses = array_merge(
							$queued_addresses,
							$addresses_chunk);
					} catch (Exception $e) {
						throw new DeliveranceException($e);
					}

					if ($queue_current_request === false) {
						$result['success_count'] +=
							$current_result['success_count'];

						$result['error_count'] +=
							$current_result['error_count'];

						$result['errors'] = array_merge(
							$result['errors'],
							$current_result['errors']);
					}

					$addresses_chunk = array();
				}

				// If all batch requests have timed out, queue the entire
				// request. Otherwise, just queue and report on the timed out
				// batches.
				$queued_address_count = count($queued_addresses);
				if ($queued_address_count == $address_count) {
					$queue_request = true;
				} elseif ($queued_address_count &&
					$this->app->hasModule('SiteDatabaseModule')) {
					$this->queueBatchSubscribe($queued_addresses);

					// treat the queueing as a special case.
					$result['queued_count']     = $queued_address_count;
					$result['queued_addresses'] = $queued_addresses;
				}
			}
		} else {
			$queue_request = true;
		}

		if ($queue_request === true &&
			$this->app->hasModule('SiteDatabaseModule')) {
			$result = $this->queueBatchUnsubscribe($addresses);
		}

		return $result;
	}

	// }}}
	// {{{ public function update()

	public function update($address, array $info, array $array_map = array())
	{
		$result = false;
		$queue_request = false;

		if ($this->isAvailable()) {
			$merges = $this->mergeInfo($info, $array_map);
			$parameters = array(
				$this->shortname,
				$address,
				$merges,
				'', // email_type, left blank to keep existing preference.
				$this->replace_interests,
				);

			try {
				$result = $this->callClientMethod(
					'listUpdateMember',
					$parameters);
			} catch (DeliveranceAPIConnectionException $e) {
				$queue_request = true;
			} catch (DeliveranceException $e) {
				// gracefully handle exceptions that we can provide nice
				// feedback about.
				switch ($e->getCode()) {
				case self::NOT_FOUND_ERROR_CODE:
					$result = DeliveranceList::NOT_FOUND;
					break;

				case self::NOT_SUBSCRIBED_ERROR_CODE:
					$result = DeliveranceList::NOT_SUBSCRIBED;
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

		if ($queue_request === true &&
			$this->app->hasModule('SiteDatabaseModule')) {
			$result = $this->queueUpdate($address, $info);
		}

		if ($result === true) {
			$result = self::SUCCESS;
		} elseif ($result === false) {
			$result = self::FAILURE;
		}

		return $result;
	}

	// }}}
	// {{{ public function updateMemberEmailAddress()

	public function updateMemberEmailAddress($old_email, $new_email)
	{
		// if we have member info on both address we can't simply update one as
		// MailChimp returns an error. Member info exists on addresses that have
		// previously unsubscribed, so we can do an isMember() check. If they do
		// both exist, unsubscribe the old one and subscribe the new one.
		if ($this->getMemberInfo($new_email) !== null &&
			$this->getMemberInfo($old_email) !== null) {
			$this->unsubscribe($old_email);
			// don't send a welcome message.
			$this->subscribe($new_email, array(), false);
		} else {
			$info = array(
				'email' => $new_email,
			);

			$this->update($old_email, $info);
		}

	}

	// }}}
	// {{{ public function batchUpdate()

	public function batchUpdate(array $addresses, array $array_map = array())
	{
		if ($this->isAvailable()) {
			$result = $this->batchSubscribe($addresses, false, $array_map);
		} elseif ($this->app->hasModule('SiteDatabaseModule')) {
			$result = $this->queueBatchUpdate($addresses);
		}

		return $result;
	}

	// }}}
	// {{{ public function isMember()

	public function isMember($address)
	{
		// Status of subscribed is the only way we can validate a current member
		return $this->isSubscribedMember($this->getMemberInfo($address));
	}

	// }}}
	// {{{ public function wasMember()

	public function wasMember($address)
	{
		return $this->isUnsubscribedMember($this->getMemberInfo($address));
	}

	// }}}
	// {{{ public function hasEverBeenMember()

	public function hasEverBeenMember($address)
	{
		$info = $this->getMemberInfo($address);

		return (
			$this->isSubscribedMember($info) ||
			$this->isUnsubscribedMember($info)
		);
	}

	// }}}
	// {{{ public function getMembers()

	public function getMembers(array $segment_options = array(), $since = '')
	{
		// Export API isn't support in MailChimpAPI, so build the call as part
		// of the method until we can find or make a wrapper for the export api.
		$members = null;
		$url     = sprintf('https://%s.api.mailchimp.com/export/1.0/list/',
			self::getDataCenter($this->getApiKey()));

		$url.= sprintf('?apikey=%s&id=%s&status=%s',
				urlencode($this->getApiKey()),
				urlencode($this->shortname),
				urlencode('subscribed'));

		if (count($segment_options) > 0 &&
			array_key_exists('match', $segment_options) &&
			array_key_exists('conditions', $segment_options)) {
			// build the segment array according to the description in the
			// MailChimp documentation here
			// http://www.mailchimp.com/api/how-to/#ex3
			$url.= sprintf('&segment[%s]=%s',
				'match',
				$segment_options['match']);

			$count = 0;
			foreach ($segment_options['conditions'] as $condition) {
				foreach ($condition as $key => $value) {
					$url.= sprintf('&segment[%s][%s][%s]=%s',
						'conditions',
						$count,
						$key,
						urlencode($value));
				}
				$count++;
			}
		}

		if (strlen($since) > 0) {
			$url.= sprintf('&since=%s',
				urlencode($since));
		}

		// TODO: better error handling.
		try {
			$members = file_get_contents($url);
		} catch (Exception $e) {
			throw new DeliveranceException($e);
		}

		$first = true;
		$members_out = array();
		$members = explode("\n", $members);
		foreach ($members as $member) {
			if ($first === true) {
				// first row is the headers of the list, grab it as the keys,
				// and then reindex the rest of the results so they are usable
				// by key. TODO: when possible use $this->list_merge_array_map
				// to define the keys instead of the columns passed by MailChimp
				// so that its consistent.
				$columns = json_decode($member);
				$first   = false;
			} else {
				// check to make sure the exploded line has a length, as the
				// results return a blank line at the end that we can ignore.
				if (strlen($member) > 0) {
					$member_array = json_decode($member, true);
					$member_out_array = array();
					foreach ($member_array as $key => $value) {
						$member_out_array[$columns[$key]] = $value;
					}

					if (count($member_out_array)) {
						$members_out[] = $member_out_array;
					}
				}
			}
		}

		return $members_out;
	}

	// }}}
	// {{{ public function getMemberInfo()

	public function getMemberInfo($address)
	{
		$member_info = null;

		if ($this->isAvailable()) {
			$parameters = array(
				$this->shortname,
				$address,
				);

			try {
				$result = $this->callClientMethod(
					'listMemberInfo',
					$parameters);

				// Since we're only checking a single address, success count
				// should be one, and the first array in data should have a
				// member info for the email address
				if ($result['success'] == 1) {
					$member_info = $result['data'][0];
				}
			} catch (DeliveranceAPIConnectionException $e) {
				// consider the address not subscribed.
			} catch (DeliveranceException $e) {
				// if it fails for any reason, just consider the address as not
				// subscribed. Log for now out of curiosity.
				$e->processAndContinue();
			} catch (Exception $e) {
				// log these for the time being to see if anything outside of
				// expected DeliveranceException's crop up.
				$e = new DeliveranceException();
				$e->processAndContinue();
			}
		}

		return $member_info;
	}

	// }}}
	// {{{ protected function mergeInfo()

	protected function mergeInfo(array $info, array $array_map = array())
	{
		// passed in array_map is second so that it can override any of the
		// list_merge_array_map values
		$array_map = array_merge($this->list_merge_array_map, $array_map);

		$merges = array();
		foreach ($info as $id => $value) {
			if (array_key_exists($id, $array_map) && $value != null) {
				$merge_var = $array_map[$id];
				// TODO: use new-style interest groups.
				// interests can be passed in as an array, but MailChimp
				// expects a comma delimited list.
				if ($merge_var == 'INTERESTS' && is_array($value)) {
					$value = implode(',', $value);
				}

				$merges[$merge_var] = $value;
			}
		}

		return $merges;
	}

	// }}}
	// {{{ public function getDefaultAddress()

	public function getDefaultAddress()
	{
		// TODO: do this better somehow
		return $this->default_address;
	}

	// }}}
	// {{{ protected function isSubscribedMember()

	protected function isSubscribedMember($member_info)
	{
		return (
			is_array($member_info) &&
			isset($member_info['status']) &&
			$member_info['status'] === 'subscribed'
		);
	}

	// }}}
	// {{{ protected function isUnsubscribedMember()

	protected function isUnsubscribedMember($member_info)
	{
		return (
			is_array($member_info) &&
			isset($member_info['status']) &&
			$member_info['status'] === 'unsubscribed'
		);
	}

	// }}}

	// interest methods
	// {{{ public function getDefaultSubscriberInfo()

	public function getDefaultSubscriberInfo()
	{
		$info = array('user_ip' => $this->app->getRemoteIP());

		$interests = $this->getInterests()->getDefaultShortnames();
		if (count($interests) > 0) {
			$info['interests'] = $interests;
		}

		return $info;
	}

	// }}}
	// {{{ public function getInterests()

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

	// }}}

	// campaign methods
	// {{{ public function saveCampaign()

	public function saveCampaign(
		DeliveranceCampaign $campaign,
		$lookup_campaign_id = true
	) {
		// if the id is already set, don't bother looking it up.
		if ($campaign->id == null && $lookup_campaign_id) {
			$campaign->id = $this->getCampaignId($campaign);
		}

		if ($campaign->id != null) {
			$this->updateCampaign($campaign);
		} else {
			$this->createCampaign($campaign);
		}

		return $campaign->id;
	}

	// }}}
	// {{{ public function deleteCampaign()

	public function deleteCampaign(DeliveranceCampaign $campaign)
	{
		$result = false;

		// if the id is already set, don't bother looking it up.
		if ($campaign->id == null) {
			$campaign->id = $this->getCampaignId($campaign);
		}

		if ($campaign->id != null) {
			$parameters = array(
				$campaign->id,
				);

			try {
				$result = $this->callClientMethod(
					'campaignDelete',
					$parameters);
			} catch (DeliveranceException $e) {
				// consider a campaign that already doesn't exist a successful
				// deletion.
				if ($e->getCode() == self::CAMPAIGN_DOES_NOT_EXIST) {
					$result = true;
				} else {
					throw new DeliveranceCampaignException($e);
				}
			} catch (Exception $e) {
				throw new DeliveranceCampaignException($e);
			}
		}

		return $result;
	}

	// }}}
	// {{{ public function sendCampaign()

	public function sendCampaign(DeliveranceCampaign $campaign)
	{
		try {
			// you can't send a campaign immediately if its been scheduled. So
			// unschedule first.
			$this->unscheduleCampaign($campaign);

			$parameters = array(
				$campaign->id,
				);

			$this->callClientMethod(
				'campaignSendNow',
				$parameters);
		} catch (Exception $e) {
			throw new DeliveranceCampaignException($e);
		}
	}

	// }}}
	// {{{ public function scheduleCampaign()

	public function scheduleCampaign(DeliveranceCampaign $campaign)
	{
		$send_date = clone $campaign->getSendDate();
		if ($send_date instanceof SwatDate) {
			// Campaigns have to be unscheduled to set a new send time. Only
			// unschedule if we're rescheduling so that we don't accidentally
			// unschedule a manually scheduled campaign.
			$this->unscheduleCampaign($campaign);

			$send_date->setTZ($this->app->default_time_zone);
			$send_date->toUTC();

			try {
				// TZ intentionally omitted, API call expects date in UTC with
				// no timezone information.
				$parameters = array(
					$campaign->id,
					$send_date->getDate(),
					);

				$this->callClientMethod(
					'campaignSchedule',
					$parameters);
			} catch (Exception $e) {
				throw new DeliveranceCampaignException($e);
			}
		}
	}

	// }}}
	// {{{ public function unscheduleCampaign()

	public function unscheduleCampaign(DeliveranceCampaign $campaign)
	{
		try {
			$parameters = array(
				$campaign->id,
				);

			$this->callClientMethod(
				'campaignUnschedule',
				$parameters);
		} catch (DeliveranceException $e) {
			// ignore errors caused by trying to unschedule a campaign that
			// isn't scheduled yet. These are safe to ignore.
			if ($e->getCode() != self::CAMPAIGN_NOT_SCHEDULED_ERROR) {
				throw new DeliveranceCampaignException($e);
			}
		} catch (Exception $e) {
			throw new DeliveranceCampaignException($e);
		}
	}

	// }}}
	// {{{ public function getCampaignId()

	public function getCampaignId(DeliveranceCampaign $campaign)
	{
		$campaign_id = null;
		$parameters  = array(
			'filters' => array(
				'list_id' => $this->shortname,
				'title'   => $campaign->getTitle(),
				'exact'   => true,
			),
		);

		try {
			$campaigns = $this->callClientMethod(
				'campaigns',
				$parameters);
		} catch (Exception $e) {
			throw new DeliveranceCampaignException($e);
		}

		if ($campaigns['total'] > 1) {
			throw new DeliveranceCampaignException(sprintf(
				'Multiple campaigns exist with a title of ‘%s’',
				$campaign->getTitle()));
		} elseif ($campaigns['total'] == 1) {
			$campaign_id = $campaigns['data'][0]['id'];
		}

		return $campaign_id;
	}

	// }}}
	// {{{ public function getCampaigns()

	public function getCampaigns(array $filters = array())
	{
		$campaigns  = array();
		$offset     = 0;
		$chunk_size = 50;
		$chunk      = $this->getCampaignsChunk($filters, $offset, $chunk_size);

		while ($chunk['total'] > count($campaigns) &&
			count($chunk['data']) > 0) {
			$campaigns = array_merge($campaigns, $chunk['data']);
			$offset++;
			$chunk = $this->getCampaignsChunk($filters, $offset, $chunk_size);
		}

		return $campaigns;
	}

	// }}}
	// {{{ public function getCampaignReportUrl()

	public function getCampaignReportUrl($campaign_id)
	{
		$url = null;
		$parameters = array(
			$campaign_id,
			);

		try {
			$report = $this->callClientMethod(
				'campaignShareReport',
				$parameters);

			$url = $report['secure_url'];
		} catch (DeliveranceException $e) {
			if ($e->getCode() !== self::CAMPAIGN_STATS_NOT_AVAILABLE) {
				throw $e;
			}
		} catch (Exception $e) {
			throw new DeliveranceCampaignException($e);
		}

		return $url;
	}

	// }}}
	// {{{ public function getCampaignStats()

	public function getCampaignStats($campaign_id)
	{
		$stats = array();
		$parameters = array(
			$campaign_id,
			);

		try {
			$stats = $this->callClientMethod('campaignStats',
				$parameters);
		} catch (Exception $e) {
			throw new DeliveranceCampaignException($e);
		}

		// This is the stat we most often want to report back. Standardize here
		// so that if it changes, we don't have to update everywhere.
		$stats['successful_sent'] = $stats['emails_sent'] -
			$stats['hard_bounces'] - $stats['soft_bounces'];

		return $stats;
	}

	// }}}
	// {{{ public function getCampaignClickStats()

	public function getCampaignClickStats($campaign_id)
	{
		$stats = array();
		$parameters = array(
			$campaign_id,
			);

		try {
			$stats = $this->callClientMethod(
				'campaignClickStats',
				$parameters);
		} catch (Exception $e) {
			throw new DeliveranceCampaignException($e);
		}

		return $stats;
	}

	// }}}
	// {{{ public function sendCampaignTest()

	public function sendCampaignTest(
		DeliveranceMailChimpCampaign $campaign,
		array $test_emails
	) {
		$parameters = array(
			$campaign->id,
			$test_emails,
			);

		try {
			$this->callClientMethod(
				'campaignSendTest',
				$parameters);
		} catch (Exception $e) {
			throw new DeliveranceCampaignException($e);
		}
	}

	// }}}
	// {{{ public function getSegmentSize()

	public function getSegmentSize(array $segment_options = null)
	{
		// if no options are passed for the segment, consider it the entire list
		if ($segment_options === null)
			return $this->getMemberCount();

		$segment_size = 0;
		$parameters = array(
			$this->shortname,
			$segment_options,
			);

		try {
			$segment_size = $this->callClientMethod(
				'campaignSegmentTest',
				$parameters);
		} catch (Exception $e) {
			throw new DeliveranceCampaignException($e);
		}

		return $segment_size;
	}

	// }}}
	// {{{ protected function createCampaign()

	protected function createCampaign(DeliveranceCampaign $campaign)
	{
		$options = $this->getCampaignOptions($campaign);
		$content = $this->getCampaignContent($campaign);
		$parameters = array(
			$campaign->type,
			$options,
			$content,
			);

		try {
			$campaign_id = $this->callClientMethod(
				'campaignCreate',
				$parameters);
		} catch (Exception $e) {
			throw new DeliveranceCampaignException($e);
		}

		$campaign->id = $campaign_id;
		// call this separately because XML/RPC can't pass nulls, and it's often
		// null. And other values are type checked by MailChimp
		// TODO: do this better to cut down on mailchimp calls.
		$this->updateCampaignSegmentOptions($campaign);

		return $campaign_id;
	}

	// }}}
	// {{{ protected function updateCampaign()

	protected function updateCampaign(DeliveranceCampaign $campaign)
	{
		$options = $this->getCampaignOptions($campaign);
		$content = $this->getCampaignContent($campaign);
		$parameters = array(
			$campaign->id,
			'content',
			$content,
			);

		try {
			$this->callClientMethod(
				'campaignUpdate',
				$parameters);

			// options can only be updated one at a time.
			// TODO: double check this is still true with 1.3
			foreach ($options as $title => $value) {
				$parameters = array(
					$campaign->id,
					$title,
					$value,
					);

				$this->callClientMethod(
					'campaignUpdate',
					$parameters);
			}

			$this->updateCampaignSegmentOptions($campaign);
		} catch (Exception $e) {
			throw new DeliveranceCampaignException($e);
		}
	}

	// }}}
	// {{{ protected function updateCampaignSegmentOptions()

	protected function updateCampaignSegmentOptions(
		DeliveranceCampaign $campaign
	) {
		$segment_options = $this->getCampaignSegmentOptions($campaign);
		if ($segment_options !== null) {
			$parameters = array(
				$campaign->id,
				'segment_opts',
				$segment_options
				);

			try {
				$this->callClientMethod(
					'campaignUpdate',
					$parameters);
			} catch (Exception $e) {
				throw new DeliveranceCampaignException($e);
			}
		}
	}

	// }}}
	// {{{ protected function getCampaignOptions()

	protected function getCampaignOptions(
		DeliveranceMailChimpCampaign $campaign
	) {
		$title = $campaign->getTitle();
		if ($title == null) {
			throw new DeliveranceCampaignException(
				'Campaign “Title” is null');
		}

		$subject = $campaign->getSubject();
		if ($subject == null) {
			throw new DeliveranceCampaignException(
				'Campaign “Subject” is null');
		}

		$from_address = $campaign->getFromAddress();
		if ($from_address == null) {
			throw new DeliveranceCampaignException(
				'Campaign “From Address” is null');
		}

		$from_name = $campaign->getFromName();
		if ($from_name == null) {
			throw new DeliveranceCampaignException(
				'Campaign “From Name” is null');
		}

		$to_name = $campaign->getToName();

		$options = array(
			'list_id'      => $this->shortname,
			'title'        => $title,
			'subject'      => $subject,
			'from_email'   => $from_address,
			'from_name'    => $from_name,
			'to_name'      => $to_name,
			'authenticate' => 'true',
			'inline_css'   => true,
			'timewarp'     => $campaign->timewarp,
			'ecomm360'     => $campaign->track_orders,
		);

		if ($this->app->config->deliverance->automatic_analytics_tagging) {
			$key = $campaign->getAnalyticsKey();
			if ($key != '') {
				$options['analytics'] = array('google' => $key);
			}
		}

		if ($this->app->config->mail_chimp->default_folder != null) {
			$options['folder_id'] =
				$this->app->config->mail_chimp->default_folder;
		}

		return $options;
	}

	// }}}
	// {{{ protected function getCampaignSegmentOptions()

	protected function getCampaignSegmentOptions(
		DeliveranceMailChimpCampaign $campaign
	) {
		$segment_options = $campaign->getSegmentOptions();

		if ($segment_options != null) {
			if ($this->getSegmentSize($segment_options) == 0) {
				throw new DeliveranceCampaignException(
					'Campaign Segment Options return no members');
			}
		}

		return $segment_options;
	}

	// }}}
	// {{{ protected function getCampaignContent()

	protected function getCampaignContent(
		DeliveranceMailChimpCampaign $campaign
	) {
		$content = array(
			'html' => $campaign->getContent(DeliveranceCampaign::FORMAT_XHTML),
			'text' => $campaign->getContent(DeliveranceCampaign::FORMAT_TEXT),
		);

		return $content;
	}

	// }}}
	// {{{ protected function getCampaignsChunk()

	protected function getCampaignsChunk(
		array $filters = array(),
		$offset = 0,
		$chunk_size = 0
	) {
		if ($chunk_size > 1000) {
			throw new DeliveranceException(
				'Campaign chunk size exceeds API limit');
		}

		$campaigns = array();
		// add the list id to the set of passed in filters
		$filters['list_id'] = $this->shortname;

		$parameters = array(
			$filters,
			$offset,
			$chunk_size,
			);

		try {
			$campaigns = $this->callClientMethod(
				'campaigns',
				$parameters);
		} catch (Exception $e) {
			throw new DeliveranceException($e);
		}

		return $campaigns;
	}

	// }}}

	// ecomm360 methods
	// {{{ public function addOrder()

	public function addOrder(array $order_info)
	{
		$success = false;

		try {
			$parameters = array(
				$order_info,
				);

			// attach to a campaign if it exists in the order_info array
			$method = (isset($order_info['campaign_id'])) ?
				'campaignEcommOrderAdd' :
				'ecommOrderAdd';

			$success = $this->callClientMethod(
				$method,
				$parameters);
		} catch (DeliveranceAPIConnectionException $e) {
			$success = false;
			// do nothing, but treat as unsuccessful.
		} catch (DeliveranceException $e) {
			// 330 means order has already been submitted, we can safely
			// throw these away
			if ($e->getCode() == self::PREVIOUSLY_ADDED_ORDER_ERROR_CODE) {
				$success = true;
			} else {
				throw $e;
			}
		} catch (Execption $e) {
			throw new DeliveranceException($e);
		}

		return $success;
	}

	// }}}

	// list methods
	// {{{ public function getMemberCount()

	public function getMemberCount()
	{
		$member_count = null;

		try {
			$lists = $this->callClientMethod('lists');

			foreach ($lists['data'] as $list) {
				if ($list['id'] == $this->shortname) {
					$member_count = $list['stats']['member_count'];
					break;
				}
			}
		} catch (Exception $e) {
			throw new DeliveranceException($e);
		}

		return $member_count;
	}

	// }}}
	// {{{ public function getMergeVars()

	public function getMergeVars()
	{
		$merge_vars = null;
		$parameters = array(
			$this->shortname,
			);

		try {
			$merge_vars = $this->callClientMethod(
				'listMergeVars',
				$parameters);
		} catch (Exception $e) {
			throw new DeliveranceException($e);
		}

		return $merge_vars;
	}

	// }}}
	// {{{ public function getInterestGroupings()

	public function getInterestGroupings()
	{
		$interest_groups = null;
		$parameters = array(
			$this->shortname,
			);

		try {
			$interest_groups = $this->callClientMethod(
				'listInterestGroupings',
				$parameters);
		} catch (Exception $e) {
			throw new DeliveranceException($e);
		}

		return $interest_groups;
	}

	// }}}

	// list setup helper methods
	// {{{ public function getAllLists()

	public function getAllLists()
	{
		$lists = null;

		try {
			$lists = $this->callClientMethod('lists');
		} catch (Exception $e) {
			throw new DeliveranceException($e);
		}

		return $lists;
	}

	// }}}
	// {{{ public function getFolders()

	public function getFolders()
	{
		$folders = null;

		try {
			$folders = $this->callClientMethod('campaignFolders');
		} catch (Exception $e) {
			throw new DeliveranceException($e);
		}

		return $folders;
	}

	// }}}
	// {{{ public function getApiKey()

	public function getApiKey()
	{
		return $this->app->config->mail_chimp->api_key;
	}

	// }}}

	// exception throwing and handling
	// {{{ private function callClientMethod()

	private function callClientMethod($method, array $parameters = array())
	{
		$handler = set_error_handler(
			array(__CLASS__, '_handleClientWarning'),
			E_NOTICE | E_WARNING);

		try {
			$result = call_user_func_array(
				array($this->client, $method),
				$parameters);

			$this->handleClientErrors();
		} catch (Exception $e) {
			restore_error_handler();
			throw $e;
		}

		restore_error_handler();

		return $result;
	}

	// }}}
	// {{{ private function handleClientErrors()

	private function handleClientErrors()
	{
		// errorCode is initialized to a blank string in the client at the start
		// of each call and if it gets set to anything else, throw an exception.
		if ($this->client->errorCode !== '') {
			// if the error code is a connection error code, throw a specific
			// exception class.
			if (array_search($this->client->errorCode,
				$this->connection_errors) === false) {
				$class_name = 'DeliveranceException';
			} else {
				$class_name = 'DeliveranceAPIConnectionException';
			}

			throw new $class_name(
				$this->client->errorMessage,
				$this->client->errorCode);
		}
	}

	// }}}
	// {{{ _handleClientWarning()

	/**
	 * Handles notices and warnings generated by MailChimpAPI
	 *
	 * @param integer $errno  the error level of the error.
	 * @param string  $errstr the error message.
	 *
	 * @return void
	 */
	public static function _handleClientWarning($errno, $errstr)
	{
		// The warnings in MailChimpAPI are generated by fsocketopen() and
		// unserialize(). If either fail, it is the same as the connection
		// failing, so throw that exception type.
		throw new DeliveranceAPIConnectionException($errstr, $errno);
	}

	// }}}
}

?>
