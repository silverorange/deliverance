<?php

require_once 'Deliverance/exceptions/DeliveranceException.php';

/**
 * Exception caused by Deliverance subscribe calls where the email address
 * subscribing is banned.
 *
 * @package   Deliverance
 * @copyright 2012-2015 silverorange
 */
class DeliveranceBannedAddressException extends DeliveranceException
{
}

?>
