<?php
defined('TYPO3_MODE') or die();
$ll = 'LLL:EXT:rlmp_language_detection/Resources/Private/Language/locallang_db.xml:';
$newSysDomainColumns = array (
                				'disable_language_detection' => array (
                					'exclude' => 1,
                					'label'   => $ll . ':sys_domain.disable_language_detection',
                					'config'  => array (
                						'type'    => 'check',
                						'default' => '1'
                					)
                				)
                            );


\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('sys_domain', $newSysDomainColumns);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('sys_domain', 'disable_language_detection');

?>