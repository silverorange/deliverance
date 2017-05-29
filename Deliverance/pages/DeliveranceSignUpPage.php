<?php

require_once 'Swat/SwatMessage.php';
require_once 'Site/pages/SiteEditPage.php';
require_once 'Deliverance/DeliveranceListFactory.php';

/**
 * @package   Deliverance
 * @copyright 2009-2017 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class DeliveranceSignUpPage extends SiteEditPage
{
	// {{{ protected properties

	/**
	 * @var boolean
	 */
	protected $send_welcome = true;

	// }}}
	// {{{ protected function getUiXml()

	protected function getUiXml()
	{
		return 'Deliverance/pages/signup.xml';
	}

	// }}}

	// process phase
	// {{{ protected function save()

	protected function save(SwatForm $form)
	{
		$list = $this->getList();
		$this->subscribe($list);
	}

	// }}}
	// {{{ protected function getList()

	protected function getList()
	{
		return DeliveranceListFactory::get($this->app, 'default');
	}

	// }}}
	// {{{ protected function subscribe()

	protected function subscribe(DeliveranceList $list)
	{
		$default_info = $list->getDefaultSubscriberInfo();

		// Check to see if the email address is already a member before doing
		// anything else. This allows the welcome flag to be set correctly,
		// and for subscriber info to be based on whether it's a new member or
		// not.
		$email = $this->getEmail();
		$this->checkMember($list, $email);

		$info      = $this->getSubscriberInfo($list);
		$array_map = $this->getArrayMap();

		$response = $list->subscribe(
			$email,
			$info,
			$this->send_welcome,
			$array_map
		);

		$this->handleSubscribeResponse($list, $response);

		if ($response === DeliveranceList::SUCCESS ||
			$response === DeliveranceList::QUEUED) {
			$this->sendNotification($list);
		}
	}

	// }}}
	// {{{ protected function handleSubscribeResponse()

	protected function handleSubscribeResponse(DeliveranceList $list, $response)
	{
		$message = $list->handleSubscribeResponse($response);
		$message_display = $this->getMessageDisplay();

		if ($message_display instanceof SwatMessageDisplay &&
			$message instanceof SwatMessage) {
			$message_display->add($message);
		}
	}

	// }}}
	// {{{ protected function getEmail()

	protected function getEmail()
	{
		return $this->ui->getWidget('email')->value;
	}

	// }}}
	// {{{ abstract protected function getSubscriberInfo();

	abstract protected function getSubscriberInfo(DeliveranceList $list);

	// }}}
	// {{{ protected function getArrayMap()

	protected function getArrayMap()
	{
		return array();
	}

	// }}}
	// {{{ protected function checkMember()

	protected function checkMember(DeliveranceList $list, $email)
	{
		if ($list->isMember($email)) {
			$this->send_welcome = false;
			$message = $this->getExistingMemberMessage($list, $email);
			if ($message != null) {
				$this->addAppMessage($message);
			}
		}
	}

	// }}}
	// {{{ protected function getExistingMemberMessage()

	protected function getExistingMemberMessage(DeliveranceList $list, $email)
	{
		// TODO: rewrite.
		$message = new SwatMessage(
			Deliverance::_(
				'Thank you. Your email address was already subscribed to '.
				'our newsletter.'
			),
			'notice'
		);

		$message->secondary_content = Deliverance::_(
			'Your subscriber information has been updated, and you will '.
			'continue to receive mailings at this address.'
		);

		return $message;
	}

	// }}}
	// {{{ protected function relocate()

	protected function relocate(SwatForm $form)
	{
		if ($this->canRelocate($form)) {
			$this->app->relocate($this->source.'/thankyou');
		}
	}

	// }}}
	// {{{ protected function canRelocate()

	protected function canRelocate(SwatForm $form)
	{
		$can_relocate = true;

		$message_display = $this->getMessageDisplay();
		if ($message_display instanceof SwatMessageDisplay &&
			$message_display->getMessageCount() > 0) {
			$can_relocate = false;
		}

		return $can_relocate;
	}

	// }}}
	// {{{ protected function getMessageDisplay()

	protected function getMessageDisplay()
	{
		return $this->ui->getRoot()->getFirstDescendant(
			'SwatMessageDisplay'
		);
	}

	// }}}
	// {{{ protected function addAppMessage()

	protected function addAppMessage(SwatMessage $message)
	{
		$this->app->messages->add($message);
	}

	// }}}
	// {{{ protected function sendNotification()

	protected function sendNotification(DeliveranceList $list)
	{
		if (isset($this->app->notifier)) {
			$info = $this->getSubscriberInfo($list);

			$this->app->notifier->send(
				'newsletter_signup',
				array(
					'site'      => $this->app->config->notifier->site,
					'list'      => $list->getShortname(),
					'interests' =>
						(isset($info['interests']))
							? $info['interests']
							: array(),
				)
			);

		}
	}

	// }}}

	// build phase
	// {{{ protected function buildForm()

	protected function buildForm(SwatForm $form)
	{
		parent::buildForm($form);

		$email = SiteApplication::initVar('email');
		if (strlen($email) > 0) {
			$this->ui->getWidget('email')->value = $email;
		} elseif (!$form->isProcessed() && $this->app->session->isLoggedIn()) {
			$this->ui->getWidget('email')->value =
				$this->app->session->account->email;
		}
	}

	// }}}
}

?>
