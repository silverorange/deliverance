<?php

namespace Silverorange\Autoloader;

$package = new Package('silverorange/deliverance');

$package->addRule(
	new Rule(
		'dataobjects',
		'Deliverance',
		array(
			'CampaignSegment',
			'MailingListInterest',
			'Newsletter',
			'NewsletterTemplate',
			'Wrapper'
		)
	)
);
$package->addRule(new Rule('exceptions', 'Deliverance', 'Exception'));
$package->addRule(new Rule('pages', 'Deliverance', array('Page', 'Server')));
$package->addRule(new Rule('', 'Deliverance'));

Autoloader::addPackage($package);

?>
