<?php

/**
 * @copyright 2009-2019 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class DeliveranceUnsubscribePage extends SiteEditPage
{
    protected function getUiXml()
    {
        return __DIR__ . '/unsubscribe.xml';
    }

    // process phase

    protected function save(SwatForm $form)
    {
        $this->unsubscribe($this->getList());
    }

    protected function getList()
    {
        return DeliveranceListFactory::get($this->app, 'default');
    }

    protected function unsubscribe(DeliveranceList $list)
    {
        $this->handleUnsubscribeResponse(
            $list,
            $list->unsubscribe($this->getEmail())
        );
    }

    protected function getEmail()
    {
        return $this->ui->getWidget('email')->value;
    }

    protected function handleUnsubscribeResponse(
        DeliveranceList $list,
        $response
    ) {
        $this->handleMessage($list->handleUnsubscribeResponse($response));
    }

    protected function handleMessage(?SwatMessage $message = null)
    {
        if ($message instanceof SwatMessage) {
            $this->ui->getWidget('message_display')->add($message);
        }
    }

    protected function relocate(SwatForm $form)
    {
        if ($this->canRelocate($form)) {
            $this->addUnsubscribeMessage();
            $this->app->relocate(
                $this->getRelocateUri($form, $this->source . '/thankyou')
            );
        }
    }

    protected function canRelocate(SwatForm $form)
    {
        return $this->ui->getWidget('message_display')->getMessageCount() ==
            0;
    }

    protected function addUnsubscribeMessage()
    {
        // TODO - add interest update messages.
    }

    protected function getRelocateUri(SwatForm $form, $default_relocate)
    {
        return $this->source . '/thankyou';
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
