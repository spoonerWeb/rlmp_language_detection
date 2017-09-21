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

$EM_CONF[$_EXTKEY] = [
    'title' => 'Language Detection',
    'description' => 'This plugin detects the visitor\'s preferred language and sets the local configuration for TYPO3\'s language engine accordingly. Both, one-tree and multiple tree concepts, are supported. It can also select from a list of similar languages if the user\'s preferred language does not exist.',
    'category' => 'misc',
    'shy' => 0,
    'version' => '8.0.0',
    'state' => 'stable',
    'uploadfolder' => 0,
    'clearcacheonload' => 0,
    'author' => 'Thomas Löffler',
    'author_email' => 'thomas.loeffler@typo3.org',
    'author_company' => '',
    'CGLcompliance' => '',
    'CGLcompliance_note' => '',
    'constraints' => [
        'depends' => [
            'php' => '7.0.0',
            'typo3' => '7.6.22-8.99.99',
            'static_info_tables' => '6.4.0'
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
