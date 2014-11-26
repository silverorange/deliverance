<?php

/**
 * Container for package wide static methods
 *
 * @package   Deliverance
 * @copyright 2010-2014 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class Deliverance
{
	// {{{ constants

	/**
	 * The gettext domain for Deliverance
	 *
	 * This is used to support multiple locales.
	 */
	const GETTEXT_DOMAIN = 'deliverance';

	// }}}
	// {{{ public static function _()

	/**
	 * Translates a phrase
	 *
	 * This is an alias for {@link Deliverance::gettext()}.
	 *
	 * @param string $message the phrase to be translated.
	 *
	 * @return string the translated phrase.
	 */
	public static function _($message)
	{
		return Deliverance::gettext($message);
	}

	// }}}
	// {{{ public static function gettext()

	/**
	 * Translates a phrase
	 *
	 * This method relies on the php gettext extension and uses dgettext()
	 * internally.
	 *
	 * @param string $message the phrase to be translated.
	 *
	 * @return string the translated phrase.
	 */
	public static function gettext($message)
	{
		return dgettext(Deliverance::GETTEXT_DOMAIN, $message);
	}

	// }}}
	// {{{ public static function ngettext()

	/**
	 * Translates a plural phrase
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
	 *                                  phrase depends on is one.
	 * @param string $plural_message the message to use when the number the
	 *                                phrase depends on is more than one.
	 * @param integer $number the number the phrase depends on.
	 *
	 * @return string the translated phrase.
	 */
	public static function ngettext($singular_message, $plural_message, $number)
	{
		return dngettext(Deliverance::GETTEXT_DOMAIN,
			$singular_message, $plural_message, $number);
	}

	// }}}
	// {{{ public static function setupGettext()

	public static function setupGettext()
	{
		$path = '@DATA-DIR@/Deliverance/locale';

		if (substr($path, 0 ,1) === '@') {
			$path = dirname(__FILE__).'/../locale';
		}

		bindtextdomain(Deliverance::GETTEXT_DOMAIN, $path);
		bind_textdomain_codeset(Deliverance::GETTEXT_DOMAIN, 'UTF-8');
	}

	// }}}
	// {{{ public static function getConfigDefinitions()

	/**
	 * Gets configuration definitions used by the Deliverance package
	 *
	 * Applications should add these definitions to their config module before
	 * loading the application configuration.
	 *
	 * @return array the configuration definitions used by the Deliverance
	 *               package.
	 *
	 * @see SiteConfigModule::addDefinitions()
	 */
	public static function getConfigDefinitions()
	{
		return array(
			// Deliverance Lists
			// Timeouts, in seconds.
			'deliverance.list_connection_timeout'        => 1,
			'deliverance.list_admin_connection_timeout'  => 5,
			'deliverance.list_script_connection_timeout' => 300,

			// Deliverance Campaigns
			'deliverance.campaign_from_name'    => null,
			'deliverance.campaign_from_address' => null,
			'deliverance.segment_include_addresses' => null,

			// allow setting a custom cdn base for campaigns. As campaigns are
			// cannot be edited once set, ideally use a CNAME for the CDN base
			// to prevent breakage of older campaigns if the CDN store changes
			// or moves. As campaigns are only served via http there is no
			// drawback to using a CNAME.
			'deliverance.campaign_cdn_base'     => null,

			// mailchimp specific
			'mail_chimp.double_opt_in'  => false,
			'mail_chimp.api_key'        => null,
			'mail_chimp.user_id'        => null,
			'mail_chimp.default_list'   => null,
			'mail_chimp.default_folder' => null,
			'mail_chimp.preview_url'    => 'http://%s.campaign-archive.com/?u=%s&id=%s',
			'mail_chimp.automatic_analytics_tagging' => false,
			'mail_chimp.analytics_utm_source'   => null,
			'mail_chimp.analytics_utm_medium'   => 'email',
			'mail_chimp.analytics_utm_campaign' => '%s',
		);
	}

	// }}}
}

Deliverance::setupGettext();
SwatUI::mapClassPrefixToPath('Deliverance', 'Deliverance');
SwatDBClassMap::addPath('Deliverance/dataobjects');

?>
