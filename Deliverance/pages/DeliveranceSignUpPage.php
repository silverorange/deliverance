<?php

/**
 * @copyright 2009-2017 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class DeliveranceSignUpPage extends SiteEditPage
{
    protected function getUiXml()
    {
        return __DIR__ . '/signup.xml';
    }

    // process phase

    protected function save(SwatForm $form)
    {
        $this->subscribe($this->getList());
    }

    protected function getList()
    {
        return DeliveranceListFactory::get($this->app, 'default');
    }

    protected function subscribe(DeliveranceList $list)
    {
        $default_info = $list->getDefaultSubscriberInfo();

        // Check to see if the email address is already a member before doing
        // anything else. This allows the welcome flag to be set correctly,
        // and for subscriber info to be based on whether it's a new member or
        // not.
        $email = $this->getEmail();
        $this->checkMember($list, $email);

        $info = $this->getSubscriberInfo($list);

        $response = $list->subscribe($email, $info);

        $this->handleSubscribeResponse($list, $response);
    }

    protected function handleSubscribeResponse(DeliveranceList $list, $response)
    {
        $message = $list->handleSubscribeResponse($response);
        $message_display = $this->getMessageDisplay();

        if ($message_display instanceof SwatMessageDisplay
            && $message instanceof SwatMessage) {
            $message_display->add($message);
        }
    }

    protected function getEmail()
    {
        return $this->ui->getWidget('email')->value;
    }

    abstract protected function getSubscriberInfo(DeliveranceList $list);

    protected function checkMember(DeliveranceList $list, $email)
    {
        if ($list->isMember($email)) {
            $message = $this->getExistingMemberMessage($list, $email);
            if ($message != null) {
                $this->addAppMessage($message);
            }
        }
    }

    protected function getExistingMemberMessage(DeliveranceList $list, $email)
    {
        // TODO: rewrite.
        $message = new SwatMessage(
            Deliverance::_(
                'Thank you. Your email address was already subscribed to ' .
                'our newsletter.'
            ),
            'notice'
        );

        $message->secondary_content = Deliverance::_(
            'Your subscriber information has been updated, and you will ' .
            'continue to receive mailings at this address.'
        );

        return $message;
    }

    protected function relocate(SwatForm $form)
    {
        if ($this->canRelocate($form)) {
            $this->app->relocate($this->source . '/thankyou');
        }
    }

    protected function canRelocate(SwatForm $form)
    {
        $can_relocate = true;

        $message_display = $this->getMessageDisplay();
        if ($message_display instanceof SwatMessageDisplay
            && $message_display->getMessageCount() > 0) {
            $can_relocate = false;
        }

        return $can_relocate;
    }

    protected function getMessageDisplay(?SwatForm $form = null)
    {
        return $this->ui->getRoot()->getFirstDescendant(
            'SwatMessageDisplay'
        );
    }

    protected function addAppMessage(SwatMessage $message)
    {
        $this->app->messages->add($message);
    }

    // build phase

    protected function buildForm(SwatForm $form)
    {
        parent::buildForm($form);

        $email = SiteApplication::initVar('email');
        if ($email != '') {
            $this->ui->getWidget('email')->value = $email;
        } elseif (!$form->isProcessed() && $this->app->session->isLoggedIn()) {
            $this->ui->getWidget('email')->value =
                $this->app->session->account->email;
        }
    }
}
