<?php

require_once 'Swat/SwatString.php';
require_once 'Site/SiteLayoutData.php';
require_once 'Deliverance/DeliveranceList.php';

/**
 * @package   Deliverance
 * @copyright 2009-2014 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class DeliveranceCampaign
{
	// {{{ class constants

	/**
	 * Output formats
	 */
	const FORMAT_XHTML = 1;
	const FORMAT_TEXT  = 2;

	// }}}
	// {{{ public properties

	public $id;
	public $shortname;

	// }}}
	// {{{ protected properties

	protected $app;
	protected $directory;

	/**
	 * @var SiteLayoutData
	 */
	protected $data;

	protected $xhtml_template_filename = 'template-html.php';
	protected $text_template_filename  = 'template-text.php';

	/**
	 * @var string
	 */
	protected $subject;

	/**
	 * @var string
	 */
	protected $title;

	/**
	 * @var string
	 */
	protected $google_campaign;

	/**
	 * @var string
	 */
	protected $preheader;

	/**
	 * @var string
	 */
	protected $text_content;

	/**
	 * @var string
	 */
	protected $html_content;

	/**
	 * @var DeliveranceCampaignSegment
	 */
	protected $campaign_segment;

	/**
	 * @var SwatDate
	 */
	protected $send_date;

	/**
	 * @var SiteInstance
	 */
	protected $instance;

	/**
	 * @var string
	 */
	protected $template;

	/**
	 * @var array
	 */
	protected $segment_options;

	/**
	 * @var array
	 */
	protected $segment_include_addresses = array();

	// }}}
	// {{{ public function __construct()

	/**
	 * Creates a new deliverance campaign
	 *
	 * @param SiteApplication $app
	 * @param string $shortname optional shortname of the campaign. Deprecated.
	 * @param directory $shortname optional directory for the campaign
	 *                                       resources. Deprecated.
	 */
	public function __construct(SiteApplication $app, $shortname = null,
		$directory = null)
	{
		$this->app  = $app;
		$this->data = new SiteLayoutData();

		$this->setShortname($shortname);
		$this->setDirectory($directory);
		$this->setSegmentIncludeAddresses(
			$app->config->deliverance->segment_include_addresses
		);
	}

	// }}}
	// {{{ public static function uploadResources()

	public static function uploadResources(SiteApplication $app,
		Campaign $campaign)
	{
		$resource_files = $campaign->getResources();

		/*
		 * Set a "never-expire" policy with a far future max age (10 years) as
		 * suggested http://developer.yahoo.com/performance/rules.html#expires.
		 * As well, set Cache-Control to public, as this allows some browsers to
		 * cache the images to disk while on https, which is a good win.
		 */
		$http_headers =  array(
			'Cache-Control' => 'public, max-age=315360000',
		);

		// copy them to s3
		foreach ($resource_files as $destination => $source) {
			$app->cdn->copyFile($destination, $source, $http_headers, 'public');
		}
	}

	// }}}
	// {{{ public static function removeResources()

	public static function removeResources(SiteApplication $app,
		Campaign $campaign)
	{
		$resource_files = $campaign->getResources();

		// remove them from s3
		foreach ($resource_files as $destination => $source) {
			$app->cdn->removeFile($destination);
		}

		// also remove the parent directory
		$app->cdn->removeFile($campaign->getResourcesDestinationDirectory());
	}

	// }}}
	// {{{ public function setSegmentIncludeAddresses()

	public function setSegmentIncludeAddresses($addresses)
	{
		if (!is_array($addresses)) {
			$addresses = ($addresses != '') ?
				explode(';', $addresses) :
				array();
		}

		if (count($addresses)) {
			$this->segment_include_addresses = array_merge(
				$this->segment_include_addresses,
				$addresses
			);
		}
	}

	// }}}
	// {{{ public function setInstance()

	public function setInstance(SiteInstance $instance = null)
	{
		$this->instance = $instance;
	}

	// }}}
	// {{{ public function setTemplate()

	public function setTemplate($template)
	{
		if ($template === null) {
			$template = 'default';
		}

		$this->template = $template;
	}

	// }}}
	// {{{ public function getResources()

	public function getResources()
	{
		$resource_files        = array();
		$source_directory      = $this->getSourceDirectory();
		$destination_directory = $this->getResourcesDestinationDirectory();

		// grab only png, jpg, and gif files, and ignore any OS X ._
		// resource fork files left behind by the evil Finder
		$expression = '/^([^\._]).+(\.png|\.jpg|\.gif)$/';

		$dir = new DirectoryIterator($source_directory);
		foreach ($dir as $entry) {
			$filename = $entry->getFilename();
			if (preg_match($expression, $filename) == 1) {
				$destination = $destination_directory.'/'.$filename;
				$source      = $source_directory.'/'.$filename;
				$resource_files[$destination] =
					$source;
			}
		}

		return $resource_files;
	}

	// }}}
	// {{{ protected function getResourcesDestinationDirectory()

	protected function getResourcesDestinationDirectory()
	{
		return sprintf(
			'newsletter/resources/%s%s',
			($this->instance instanceof SiteInstance) ?
				$this->instance->shortname.'/':
				null,
			$this->shortname
		);

		return $dir;
	}

	// }}}
	// {{{ protected function getResourceUri()

	protected function getResourceUri()
	{
		// once the send_date is set, use the final cdn urls. Until then, use
		// the direct s3 urls.
		if ($this->send_date === null) {
			$base_uri = sprintf('http://%s.s3.amazonaws.com/',
				$this->app->config->amazon->bucket);
		} else {
			$base_uri =
				($this->app->config->deliverance->campaign_cdn_base !== null) ?
				$this->app->config->deliverance->campaign_cdn_base :
				$this->app->config->uri->cdn_base;
		}

		$uri = $base_uri.$this->getResourcesDestinationDirectory().'/';

		return $uri;
	}

	// }}}
	// {{{ public function setShortname()

	public function setShortname($shortname)
	{
		$this->shortname = $shortname;
	}

	// }}}
	// {{{ public function setDirectory()

	public function setDirectory($directory)
	{
		$this->directory = $directory;
	}

	// }}}
	// {{{ public function setId()

	public function setId($id)
	{
		$this->id = $id;
	}

	// }}}
	// {{{ public function setTitle()

	public function setTitle($title)
	{
		$this->title = $title;
	}

	// }}}
	// {{{ public function setPreheader()

	public function setPreheader($preheader)
	{
		$this->preheader = $preheader;
	}

	// }}}
	// {{{ public function setSubject()

	public function setSubject($subject)
	{
		$this->subject = $subject;
	}

	// }}}
	// {{{ public function setGoogleCampaign()

	public function setGoogleCampaign($google_campaign)
	{
		$this->google_campaign = $google_campaign;
	}

	// }}}
	// {{{ public function setHtmlContent()

	public function setHtmlContent($html_content)
	{
		$this->html_content = $html_content;
	}

	// }}}
	// {{{ public function setTextContent()

	public function setTextContent($text_content)
	{
		$this->text_content = $text_content;
	}

	// }}}
	// {{{ public function setCampaignSegement()

	public function setCampaignSegment(
		DeliveranceCampaignSegment $campaign_segment = null)
	{
		$this->campaign_segment = $campaign_segment;
		if ($campaign_segment !== null) {
			$this->setSegmentOptions(
				$this->campaign_segment->getSegmentOptions());
		}
	}

	// }}}
	// {{{ public function setSendDate()

	public function setSendDate(SwatDate $send_date)
	{
		$this->send_date = $send_date;
	}

	// }}}
	// {{{ public function setSegmentOptions()

	public function setSegmentOptions(array $segment_options = null)
	{
		$this->segment_options = $segment_options;
	}

	// }}}
	// {{{ public function getTitle()

	public function getTitle()
	{
		return $this->title;
	}

	// }}}
	// {{{ public function getAnalyticsKey()

	public function getAnalyticsKey()
	{
		// TODO - if more than one campaign is sent in a day, and the shortname
		// is the campaign date, this will not work.
		$key = $this->shortname;

		if ($this->campaign_segment !== null) {
			$key.=sprintf('_%s',
				$this->campaign_segment->shortname);
		}

		return $key;
	}

	// }}}
	// {{{ public function getFromAddress()

	public function getFromAddress()
	{
		return $this->app->config->deliverance->campaign_from_address;
	}

	// }}}
	// {{{ public function getFromName()

	public function getFromName()
	{
		return $this->app->config->deliverance->campaign_from_name;
	}

	// }}}
	// {{{ public function getSubject()

	public function getSubject()
	{
		return $this->subject;
	}

	// }}}
	// {{{ public function getSegmentOptions()

	public function getSegmentOptions()
	{
		$segment_options = null;

		if ($this->segment_options !== null) {
			$segment_options = $this->segment_options;

			// always include these addresses so that we get a copy of all
			// emails, even if we don't belong in the segment.
			if (count($this->segment_include_addresses)) {
				foreach ($this->segment_include_addresses as $email) {
					$email = trim($email);
					if ($email != '') {
						$segment_options['conditions'][] = array(
							'field' => 'EMAIL',
							'op'    => 'eq',
							'value' => $email,
						);
					}
				}
			}
		}

		return $segment_options;
	}

	// }}}
	// {{{ public function getSendDate()

	public function getSendDate()
	{
		return $this->send_date;
	}

	// }}}
	// {{{ public final function getContent()

	/**
	 * Gets the content of this mailing
	 *
	 * @param string $filename the filename of the template to use.
	 * @param integer $format integer contstant of the output format to use.
	 *
	 * @return string the content.
	 */
	public final function getContent($format = self::FORMAT_XHTML)
	{
		$filename = $this->getTemplateFilename($format);
		$this->build($format);

		ob_start();
		$this->data->display($filename);
		$content = ob_get_clean();
		$content = $this->replaceMarkers($content, $format);
		$content = $this->transform($content, $format);

		return $content;
	}

	// }}}
	// {{{ protected function build()

	/**
	 * Builds data properties before they are substituted into the layout
	 */
	protected function build($format)
	{
	}

	// }}}
	// {{{ protected function transform()

	protected function transform($content, $format) {
		switch ($format) {
		case self::FORMAT_XHTML:
			$document = $this->getDOMDocument($content);
			$this->transformXhtml($document);
			$content = $document->saveXML(
				$document->documentElement,
				LIBXML_NOXMLDECL
			);

			break;

		case self::FORMAT_TEXT:
			$content = $this->transformText($content);
			break;
		}

		return $content;
	}

	// }}}
	// {{{ protected function transformXhtml()

	protected function transformXhtml($document)
	{
		$head_tags = $document->documentElement->getElementsByTagName('head');
		$head = $head_tags->item(0);

		// add meta Content-Type element to head for UTF-8 encoding
		$encoding = $document->createElement('meta');
		$encoding->setAttribute('http-equiv', 'Content-Type');
		$encoding->setAttribute('content', 'text/html; charset=utf-8');
		$head->insertBefore($encoding, $head->firstChild);

		// add base element to head
		$base = $document->createElement('base');
		$base->setAttribute('href', $this->getBaseHref());
		$head->insertBefore($base, $head->firstChild);

		$style_sheet = $this->getStyleSheet();
		if (file_exists($style_sheet)) {
			// add style element to head
			$style = $document->createElement('style');
			$style->setAttribute('type', 'text/css');
			$style->setAttribute('media', 'all');

			$style->appendChild(
				$document->createTextNode(
					file_get_contents(
						$style_sheet
					)
				)
			);

			$head->appendChild($style);
		}

		// prepend img srcs with newsletter dir and resource base href
		$images = $document->documentElement->getElementsByTagName('img');
		foreach ($images as $image) {
			$src = $this->getResourceUri().$image->getAttribute('src');
			$image->setAttribute('src', $src);
		}

		// add analytics uri vars to all anchors in the rendered document
		$anchors = $document->documentElement->getElementsByTagName('a');
		foreach ($anchors as $anchor) {
			$href = $anchor->getAttribute('href');
			$href = $this->appendAnalyticsToUri($href);
			$anchor->setAttribute('href', $href);
		}
	}

	// }}}
	// {{{ protected function getStyleSheet()

	protected function getStyleSheet()
	{
		return $this->getSourceDirectory().'/newsletter.css';
	}

	// }}}
	// {{{ protected function transformText()

	/**
	 * Mangles links to have ad tracking vars
	 */
	protected function transformText($text)
	{
		// prepend uris with base href
		$text = preg_replace(
			'/:uri:(.*?)(\s)/',
			$this->getBaseHref().'\1\2',
			$text
		);

		if (mb_detect_encoding($text, 'UTF-8', true) !== 'UTF-8') {
			throw new SiteException('Text output is not valid UTF-8');
		}

		$text = SwatString::stripXHTMLTags($text);
		$text = html_entity_decode($text);

		return $text;
	}

	// }}}

	// {{{ protected function getBaseHref()

	protected function getBaseHref()
	{
		return $this->app->getConfigSetting(
			'uri.absolute_base',
			$this->instance
		);
	}

	// }}}
	// {{{ protected function getDOMDocument()

	protected function getDOMDocument($xhtml)
	{
		$internal_errors = libxml_use_internal_errors(true);

		$document = new DOMDocument();
		if (!$document->loadHTML($xhtml)) {
			$xml_errors = libxml_get_errors();
			$message = '';
			foreach ($xml_errors as $error)
				$message.= sprintf(
					"%s in %s, line %d\n",
					trim($error->message),
					$error->file,
					$error->line
			);

			libxml_clear_errors();
			libxml_use_internal_errors($internal_errors);

			$e = new Exception(
				"Generated XHTML is not valid:\n".
				$message
			);

			throw $e;
		}

		libxml_use_internal_errors($internal_errors);

		return $document;
	}

	// }}}
	// {{{ protected function getCustomAnalyticsUriVars()

	protected function getCustomAnalyticsUriVars()
	{
		$vars = array();

		$config = $this->app->config->deliverance;

		// Always require a utm_source as well as no automatic tagging to allow
		// custom analytics tracking.
		if (!$config->automatic_analytics_tagging &&
			$config->analytics_utm_source != '') {
			$vars['utm_source'] = $config->analytics_utm_source;

			if ($config->analytics_utm_source != '') {
				$vars['utm_medium'] = $config->analytics_utm_medium;
			}

			$vars['utm_campaign'] = $this->getCustomGoogleCampaign();
		}

		return $vars;
	}

	// }}}
	// {{{ protected function getCustomGoogleCampaign()

	protected function getCustomGoogleCampaign()
	{
		$utm_campaign = $this->google_campaign;

		// If no campaign exists, use a shortened version of the subject line.
		if ($utm_campaign == '') {
			$utm_campaing = SwatString::ellipsizeRight($this->subject, 10, '');
		}

		$utm_campaign = sprintf(
			$this->app->config->deliverance->analytics_utm_campaign,
			rawurlencode($utm_campaign),
			$this->shortname
		);

		return $utm_campaign;
	}

	// }}}
	// {{{ protected function appendAnalyticsToUri()

	protected function appendAnalyticsToUri($uri)
	{
		$vars = array();

		foreach ($this->getCustomAnalyticsUriVars() as $name => $value)
			$vars[] = sprintf('%s=%s', urlencode($name), urlencode($value));

		if (count($vars)) {
			$var_string = implode('&', $vars);

			if (strpos($uri, '?') === false) {
				$uri = $uri.'?'.$var_string;
			} else {
				$uri = $uri.'&'.$var_string;
			}
		}

		return $uri;
	}

	// }}}
	// {{{ protected function getSourceDirectory()

	protected function getSourceDirectory()
	{
		return sprintf(
			'%s/../newsletter/%s%s',
			$this->getSourceBaseDirectory(),
			($this->instance instanceof SiteInstance) ?
				$this->instance->shortname.'/':
				'',
			$this->template
		);
	}

	// }}}
	// {{{ protected function getSourceBaseDirectory()

	protected function getSourceBaseDirectory()
	{
		// use a reflector so that subclassed objects can look up their
		// own source directory.
		$reflector = new ReflectionClass(get_class($this));

		return dirname($reflector->getFileName());
	}

	// }}}
	// {{{ protected function getTemplateFilename()

	protected function getTemplateFilename($format)
	{
		$filename = $this->getSourceDirectory().'/';

		switch($format) {
		case DeliveranceCampaign::FORMAT_XHTML:
			$filename.= $this->xhtml_template_filename;
			break;

		case DeliveranceCampaign::FORMAT_TEXT:
			$filename.= $this->text_template_filename;
			break;
		}

		return $filename;
	}

	// }}}
	// {{{ protected function getReplacementMarkerText()

	/**
	 * Gets replacement text for a specfied replacement marker identifier
	 *
	 * @param string $marker_id the id of the marker found in the campaign
	 *                           content.
	 *
	 * @return string the replacement text for the given marker id.
	 */
	protected function getReplacementMarkerText($marker_id, $format)
	{
		// by default, always return a blank string as replacement text
		return '';
	}

	// }}}
	// {{{ protected final function replaceMarkers()

	/**
	 * Replaces markers in campaign with dynamic content
	 *
	 * @param string $text the content of the campaign.
	 * @param string $format the current format of the content.
	 *
	 * @return string the campaign content with markers replaced by dynamic
	 *                 content.
	 */
	protected final function replaceMarkers($text, $format)
	{
		$marker_pattern = '/<!-- \[(.*?)\] -->/u';

		$callback_function = ($format == self::FORMAT_XHTML) ?
			'getXhtmlReplacementMarkerTextByMatches' :
			'getTextReplacementMarkerTextByMatches';

		$callback = array($this, $callback_function);

		return preg_replace_callback($marker_pattern, $callback, $text);
	}

	// }}}
	// {{{ private final function getReplacementMarkerTextByMatches()

	private final function getXhtmlReplacementMarkerTextByMatches($matches)
	{
		return $this->getReplacementMarkerTextByMatches(
			$matches,
			self::FORMAT_XHTML
		);
	}

	// }}}
	// {{{ private final function getTextReplacementMarkerTextByMatches()

	private final function getTextReplacementMarkerTextByMatches($matches)
	{
		return $this->getReplacementMarkerTextByMatches(
			$matches,
			self::FORMAT_TEXT
		);
	}

	// }}}
	// {{{ private final function getReplacementMarkerTextByMatches()

	/**
	 * Gets replacement text for a replacement marker from within a matches
	 * array returned from a PERL regular expression
	 *
	 * @param array $matches the PERL regular expression matches array.
	 * @param string $format the current format of the content.
	 *
	 * @return string the replacement text for the first parenthesized
	 *                 subpattern of the <i>$matches</i> array.
	 */
	private final function getReplacementMarkerTextByMatches($matches, $format)
	{
		if (isset($matches[1])) {
			return $this->getReplacementMarkerText($matches[1], $format);
		}

		return '';
	}

	// }}}
}

?>
