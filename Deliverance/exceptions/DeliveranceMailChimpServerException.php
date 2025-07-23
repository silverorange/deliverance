<?php

/**
 * Exception caused by a 500 level HTTP error from MailChimp.
 *
 * See {@link https://developer.mailchimp.com/documentation/mailchimp/guides/error-glossary/}.
 *
 * @copyright 2019 silverorange
 */
class DeliveranceMailChimpServerException extends DeliveranceException {}
