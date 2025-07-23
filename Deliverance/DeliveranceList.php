<?php

/**
 * @package   Deliverance
 * @copyright 2009-2019 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class DeliveranceList
{


	/**
	 * Return Value when successfully subscribing or unsubscribing an email
	 * address from the list.
	 */
	const SUCCESS = 1;

	/**
	 * Return Value when unsuccessfully subscribing or unsubscribing an email
	 * address from the list and we have no further information.
	 */
	const FAILURE = 2;

	/**
	 * Return Value when unsuccessfully unsubscribing an email address from the
	 * list.
	 *
	 * This is returned if we know the address was never a member of the
	 * list, or when we have less information, and know the unsubscribe failed.
	 */
	const NOT_FOUND = 3;

	/**
	 * Return Value when unsuccessfully unsubscribing an email address from the
	 * list.
	 *
	 * This is returned if we know the address was a member that has already
	 * unsubscribed from the list.
	 */
	const NOT_SUBSCRIBED = 4;

	/**
	 * Return Value when unable to subscribed/unsubscribe an email address from
	 * the list, but we've been able to queue the request.
	 *
	 * This happens if isAvailable() returns false.
	 */
	const QUEUED = 5;

	/**
	 * Return Value when unable to subscribe an email address to the list.
	 *
	 * This is returned on invalid email addresses.
	 */
	const INVALID = 6;




	protected $app;
	protected $shortname;




	public function __construct(SiteApplication $app, $shortname = null)
	{
		$this->app       = $app;
		$this->shortname = $shortname;
	}




	abstract public function isAvailable();



	// subscriber methods


	abstract public function subscribe($address, array $info = array());




	abstract public function batchSubscribe(
		array $addresses
	);




	public function handleSubscribeResponse($response)
	{
		switch ($response) {
		case self::INVALID:
			$message = new SwatMessage(
				Deliverance::_(
					'Sorry, the email address you entered is not a valid '.
					'email address.'
				),
				'error'
			);
			break;

		case self::FAILURE:
			$message = new SwatMessage(
				Deliverance::_(
					'Sorry, there was an issue subscribing you to the list.'
				),
				'error'
			);

			$message->content_type = 'text/xml';
			$message->secondary_content = sprintf(
				Deliverance::_(
					'This can usually be resolved by trying again later. If '.
					'the issue persists please <a href="%s">contact us</a>.'
				),
				$this->getContactUsLink()
			);

			$message->content_type = 'txt/xhtml';
			break;

		default:
			$message = null;
		}

		return $message;
	}




	abstract public function unsubscribe($address);




	abstract public function batchUnsubscribe(array $addresses);




	public function handleUnsubscribeResponse($response)
	{
		switch ($response) {
		case self::NOT_FOUND:
			$message = new SwatMessage(
				Deliverance::_(
					'Thank you. Your email address was never subscribed to '.
					'our newsletter.'
				),
				'notice'
			);

			$message->secondary_content = Deliverance::_(
				'You will not receive any mailings to this address.'
			);

			break;

		case self::NOT_SUBSCRIBED:
			$message = new SwatMessage(
				Deliverance::_(
					'Thank you. Your email address has already been '.
					'unsubscribed from our newsletter.'
				),
				'notice'
			);

			$message->secondary_content = Deliverance::_(
				'You will not receive any mailings to this address.'
			);

			break;

		case self::FAILURE:
			$message = new SwatMessage(
				Deliverance::_(
					'Sorry, there was an issue unsubscribing from the list.'
				),
				'error'
			);

			$message->content_type = 'text/xml';
			$message->secondary_content = sprintf(
				Deliverance::_(
					'This can usually be resolved by trying again later. '.
					'If the issue persists, please '.
					'<a href="%s">contact us</a>.'
				),
				$this->getContactUsLink()
			);

			$message->content_type = 'txt/xhtml';
			break;

		default:
			$message = null;
		}

		return $message;
	}




	abstract public function isMember($address);




	protected function getContactUsLink()
	{
		return 'about/contact';
	}



	// interest methods


	abstract public function getDefaultSubscriberInfo();



	// queue methods


	/**
	 * Enqueues a subscribe request for this list
	 *
	 * If a duplicate address is added to the queue, the info field is updated
	 * instead of inserting a new row. This prevents the queue from growing
	 * exponentially if list subscribes are unavailable for a long time.
	 *
	 * @param string  $address
	 * @param array   $info
	 * @param boolean $send_welcome
	 *
	 * @return integer status code for a queued response.
	 */
	public function queueSubscribe($address, array $info, $send_welcome = false)
	{
		$transaction = new SwatDBTransaction($this->app->db);
		try {

			$sql = sprintf(
				'select count(1) from MailingListSubscribeQueue
				where email = %s and instance %s %s',
				$this->app->db->quote($address, 'text'),
				SwatDB::equalityOperator($this->app->getInstanceId()),
				$this->app->db->quote($this->app->getInstanceId(), 'integer')
			);

			if (SwatDB::queryOne($this->app->db, $sql) === 0) {
				$sql = sprintf(
					'insert into MailingListSubscribeQueue (
						email, info, send_welcome, instance
					) values (%s, %s, %s, %s)',
					$this->app->db->quote($address, 'text'),
					$this->app->db->quote(serialize($info), 'text'),
					$this->app->db->quote($send_welcome, 'boolean'),
					$this->app->db->quote(
						$this->app->getInstanceId(),
						'integer'
					)
				);
			} else {
				$sql = sprintf(
					'update MailingListSubscribeQueue set
						info = %s, send_welcome = %s
					where email = %s and instance %s %s',
					$this->app->db->quote(serialize($info), 'text'),
					$this->app->db->quote($send_welcome, 'boolean'),
					$this->app->db->quote($address, 'text'),
					SwatDB::equalityOperator($this->app->getInstanceId()),
					$this->app->db->quote(
						$this->app->getInstanceId(),
						'integer'
					)
				);
			}

			SwatDB::exec($this->app->db, $sql);

			$transaction->commit();
		} catch (SwatDBException $e) {
			$transaction->rollback();
			throw $e;
		}

		return self::QUEUED;
	}




	/**
	 * Enqueues an update subscription request for this list
	 *
	 * Duplicate rows are not added to the queue. This prevents the queue from
	 * growing exponentially if list updates are unavailable for a long time.
	 *
	 * @param string $address
	 * @param array  $info
	 *
	 * @return integer status code for a queued response.
	 */
	public function queueUpdate($address, array $info)
	{
		$info = serialize($info);

		$transaction = new SwatDBTransaction($this->app->db);
		try {

			$sql = sprintf(
				'select count(1) from MailingListUpdateQueue
				where email = %s and info = %s and instance %s %s',
				$this->app->db->quote($address, 'text'),
				$this->app->db->quote($info, 'text'),
				SwatDB::equalityOperator($this->app->getInstanceId()),
				$this->app->db->quote($this->app->getInstanceId(), 'integer')
			);

			if (SwatDB::queryOne($this->app->db, $sql) === 0) {
				$sql = sprintf(
					'insert into MailingListUpdateQueue (
						email, info, instance
					) values (%s, %s, %s)',
					$this->app->db->quote($address, 'text'),
					$this->app->db->quote($info, 'text'),
					$this->app->db->quote(
						$this->app->getInstanceId(),
						'integer'
					)
				);

				SwatDB::exec($this->app->db, $sql);
			}

			$transaction->commit();
		} catch (SwatDBException $e) {
			$transaction->rollback();
			throw $e;
		}

		return self::QUEUED;
	}




	/**
	 * Enqueues an unsubscribe request for this list
	 *
	 * Duplicate address are not added to the queue to prevent the queue from
	 * growing exponentially if list unsubscribes are unavailable for a long
	 * time.
	 *
	 * @param string $address
	 *
	 * @return integer status code for a queued response.
	 */
	public function queueUnsubscribe($address)
	{
		$transaction = new SwatDBTransaction($this->app->db);
		try {

			$sql = sprintf(
				'select count(1) from MailingListUnsubscribeQueue
				where email = %s and instance %s %s',
				$this->app->db->quote($address, 'text'),
				SwatDB::equalityOperator($this->app->getInstanceId()),
				$this->app->db->quote($this->app->getInstanceId(), 'integer')
			);

			if (SwatDB::queryOne($this->app->db, $sql) === 0) {
				$sql = sprintf(
					'insert into MailingListUnsubscribeQueue
					(email, instance) values (%s, %s)',
					$this->app->db->quote($address, 'text'),
					$this->app->db->quote(
						$this->app->getInstanceId(),
						'integer'
					)
				);
				SwatDB::exec($this->app->db, $sql);
			}

			$transaction->commit();
		} catch (SwatDBException $e) {
			$transaction->rollback();
			throw $e;
		}

		return self::QUEUED;
	}




	public function getShortname()
	{
		return $this->shortname;
	}


}

?>
