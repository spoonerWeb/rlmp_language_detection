<?php
defined('TYPO3_MODE') or die();

use \TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

$ll_path = 'LLL:EXT:rlmp_language_detection/Resources/Private/Language/locallang_db.xlf';
$temporaryColumns  = array (
                				'tx_rlmplanguagedetection_disabledetection' => array (
                					'exclude' => 1,
									'label'   => $ll_path . ':sys_domain.tx_rlmplanguagedetection_disabledetection',
                					'config'  => array (
                						'type'    => 'check',
                						'default' => '1'
                					)
                				)
                            );

ExtensionManagementUtility::addTCAcolumns(
  'sys_domain',
  $temporaryColumns 
);
ExtensionManagementUtility::addToAllTCAtypes(
  'sys_domain',
  'tx_rlmplanguagedetection_disabledetection'
);
?>