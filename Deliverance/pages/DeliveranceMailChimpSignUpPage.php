<?php

/**
 * @copyright 2009-2017 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class DeliveranceMailChimpSignUpPage extends DeliveranceSignUpPage
{
    // process phase

    protected function getSubscriberInfo(DeliveranceList $list)
    {
        $info = $list->getDefaultSubscriberInfo();

        // Send welcome is used to signify a new signup to the list. In that
        // case set correct site as the source.
        if ($this->app->config->mail_chimp->source != '') {
            $info['source'] = $this->app->config->mail_chimp->source;
        }

        return $info;
    }
}
