<?php

/**
 * Container for package wide static methods.
 *
 * @copyright 2010-2017 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class Deliverance
{
    /**
     * The gettext domain for Deliverance.
     *
     * This is used to support multiple locales.
     */
    public const GETTEXT_DOMAIN = 'deliverance';

    /**
     * Whether or not this package is initialized.
     *
     * @var bool
     */
    private static $is_initialized = false;

    /**
     * Translates a phrase.
     *
     * This is an alias for {@link self::gettext()}.
     *
     * @param string $message the phrase to be translated
     *
     * @return string the translated phrase
     */
    public static function _($message)
    {
        return self::gettext($message);
    }

    /**
     * Translates a phrase.
     *
     * This method relies on the php gettext extension and uses dgettext()
     * internally.
     *
     * @param string $message the phrase to be translated
     *
     * @return string the translated phrase
     */
    public static function gettext($message)
    {
        return dgettext(self::GETTEXT_DOMAIN, $message);
    }

    /**
     * Translates a plural phrase.
     *
     * This method should be used when a phrase depends on a number. For
     * example, use ngettext when translating a dynamic phrase like:
     *
     * - "There is 1 new item" for 1 item and
     * - "There are 2 new items" for 2 or more items.
     *
     * This method relies on the php gettext extension and uses dngettext()
     * internally.
     *
     * @param string $singular_message the message to use when the number the
     *                                 phrase depends on is one
     * @param string $plural_message   the message to use when the number the
     *                                 phrase depends on is more than one
     * @param int    $number           the number the phrase depends on
     *
     * @return string the translated phrase
     */
    public static function ngettext($singular_message, $plural_message, $number)
    {
        return dngettext(
            self::GETTEXT_DOMAIN,
            $singular_message,
            $plural_message,
            $number
        );
    }

    public static function setupGettext()
    {
        bindtextdomain(self::GETTEXT_DOMAIN, __DIR__ . '/../locale');
        bind_textdomain_codeset(self::GETTEXT_DOMAIN, 'UTF-8');
    }

    /**
     * Gets configuration definitions used by the Deliverance package.
     *
     * Applications should add these definitions to their config module before
     * loading the application configuration.
     *
     * @return array the configuration definitions used by the Deliverance
     *               package
     *
     * @see SiteConfigModule::addDefinitions()
     */
    public static function getConfigDefinitions()
    {
        return [
            // Deliverance Lists
            // Timeouts, in seconds.
            'deliverance.list_connection_timeout'        => 1,
            'deliverance.list_script_connection_timeout' => 300,

            // mailchimp specific
            'mail_chimp.api_key'      => null,
            'mail_chimp.default_list' => null,
            'mail_chimp.list_title'   => null,
            'mail_chimp.source'       => null,
        ];
    }

    public static function init()
    {
        if (self::$is_initialized) {
            return;
        }

        Swat::init();
        Site::init();

        self::setupGettext();

        SwatUI::mapClassPrefixToPath('Deliverance', 'Deliverance');

        self::$is_initialized = true;
    }

    /**
     * Prevent instantiation of this static class.
     */
    private function __construct() {}
}
