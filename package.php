<?php

require_once 'PEAR/PackageFileManager2.php';

$version = '0.1.8';
$notes = <<<EOT
No release notes for you!
EOT;

$description =<<<EOT
Package build on Site used to manage mailing lists.
EOT;

$package = new PEAR_PackageFileManager2();
PEAR::setErrorHandling(PEAR_ERROR_DIE);

$result = $package->setOptions(
	array(
		'filelistgenerator' => 'svn',
		'simpleoutput'      => true,
		'baseinstalldir'    => '/',
		'packagedirectory'  => './',
		'dir_roles'         => array(
			'Deliverance'   => 'php',
			'/'             => 'data',
		),
	)
);

$package->setPackage('Deliverance');
$package->setSummary('Library for managing mailing lists built on the '.
	'Site framework.');

$package->setDescription($description);
$package->setChannel('pear.silverorange.com');
$package->setPackageType('php');
$package->setLicense('LGPL', 'http://www.gnu.org/copyleft/lesser.html');

$package->setReleaseVersion($version);
$package->setReleaseStability('stable');
$package->setAPIVersion('0.1.0');
$package->setAPIStability('stable');
$package->setNotes($notes);

$package->addIgnore('package.php');

$package->addMaintainer(
	'lead',
	'gauthierm',
	'Mike Gauthier',
	'mike@silverorange.com');

$package->addMaintainer(
	'lead',
	'gauthierm',
	'Nathan Fredrickson',
	'nathan@silverorange.com');


$package->addReplacement(
	'Delverance/Deliverance.php',
	'pear-config',
	'@DATA-DIR@',
	'data_dir');

$package->setPhpDep('5.1.5');
$package->setPearinstallerDep('1.4.0');
$package->addPackageDepWithChannel(
	'required',
	'Site',
	'pear.silverorange.com',
	'1.5.8');

$package->addPackageDepWithChannel(
	'required',
	'Swat',
	'pear.silverorange.com',
	'1.4.62');

$package->addPackageDepWithChannel('required', 'Mail', 'pear.php.net', '1.1.10');
$package->addPackageDepWithChannel('required', 'Mail_Mime', 'pear.silverorange.com', '1.5.2so3');
$package->addPackageDepWithChannel('required', 'Net_SMTP', 'pear.php.net', '1.2.8');
$package->addPackageDepWithChannel('optional', 'XML_RPC2', 'pear.silverorange.com', '1.0.3so5');
$package->generateContents();

if (isset($_GET['make']) || (isset($_SERVER['argv']) && @$_SERVER['argv'][1] == 'make')) {
	$package->writePackageFile();
} else {
	$package->debugPackageFile();
}

?>
