<?php

require_once 'Swat/exceptions/SwatException.php';

/**
 * Exception caused by Deliverance Campaign API calls that have connection
 * issues.
 *
 * Example exception causes are API endpoint being unavailable or return invald
 * results.
 *
 * @package   Deliverance
 * @copyright 2011 silverorange
 */
class DeliveranceAPIConnectionException extends SwatException
{
}

?>
