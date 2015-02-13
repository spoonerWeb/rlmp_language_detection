<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "rlmp_language_detection".
 *
 * Auto generated 28-12-2012 23:24
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Language Detection',
	'description' => 'This plugin detects the visitor\'s preferred language and sets the local configuration for TYPO3\'s language engine accordingly. Both, one-tree and multiple tree concepts, are supported. It can also select from a list of similar languages if the user\'s preferred language does not exist.',
	'category' => 'misc',
	'shy' => 0,
	'version' => '3.0.1',
	'state' => 'stable',
	'uploadfolder' => 0,
	'clearcacheonload' => 0,
	'author' => 'Thomas Löffler',
	'author_email' => 'thomas.loeffler@typo3.org',
	'author_company' => '',
	'CGLcompliance' => '',
	'CGLcompliance_note' => '',
	'constraints' => array(
		'depends' => array(
			'typo3' => '6.2.0-6.2.99',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'_md5_values_when_last_written' => 'a:5:{s:12:"ext_icon.gif";s:4:"184c";s:17:"ext_localconf.php";s:4:"bddb";s:24:"ext_typoscript_setup.txt";s:4:"935c";s:14:"doc/manual.sxw";s:4:"1a0f";s:42:"pi1/class.tx_rlmplanguagedetection_pi1.php";s:4:"62e0";}',
);

?>