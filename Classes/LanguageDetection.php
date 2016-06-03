<?php
/**
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Rlmp\RlmpLanguageDetection;

use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Plugin\AbstractPlugin;

/**
 * Plugin 'Language Detection' for the 'rlmp_language_detection' extension.
 *
 * @author    robert lemke medienprojekte <rl@robertlemke.de>
 * @author    Mathias Bolt Lesniak, LiliO Design <mathias@lilio.com>
 * @author    Joachim Mathes, punkt.de GmbH <t3extensions@punkt.de>
 * @author    Thomas Löffler <loeffler@spooner-web.de>
 * @author    Markus Klein <klein.t3@reelworx.at>
 */
class LanguageDetection extends AbstractPlugin {

	/**
	 * @var string
	 */
	public $prefixId = 'tx_rlmplanguagedetection_pi1';

	/**
	 * @var string
	 */
	public $scriptRelPath = 'pi1/class.tx_rlmplanguagedetection_pi1.php';

	/**
	 * @var string
	 */
	public $extKey = 'rlmp_language_detection';

	/**
	 * @var array
	 */
	public $conf = array();

	/**
	 * @var int
	 */
	protected $cookieLifetime = 0;

	/**
	 * @var string
	 */
	protected $botPattern = '/bot|crawl|slurp|spider/i';

	/**
	 * The main function recognizes the browser's preferred languages and
	 * reloads the page accordingly. Exits if successful.
	 *
	 * @param string $content HTML content
	 * @param array $conf The mandatory configuration array
	 * @return string
	 */
	public function main($content, $conf) {
		$this->conf = $conf;
		$this->cookieLifetime = (int)$conf['cookieLifetime'];

		// Break out if a spider/search engine bot is visiting the website
		if ($this->isBot()) {
			return $content;
		}

		// Break out if language already selected
		if (!$this->conf['dontBreakIfLanguageIsAlreadySelected'] && GeneralUtility::_GP($this->conf['languageGPVar']) !== NULL) {
			if (TYPO3_DLOG) {
				GeneralUtility::devLog('Break out since language is already selected', $this->extKey);
			}
			return $content;
		}

		// Break out if the last page visited was also on our site:
		$referrer = (string)GeneralUtility::getIndpEnv('HTTP_REFERER');
		if (TYPO3_DLOG) {
			GeneralUtility::devLog('Referrer: ' . $referrer, $this->extKey);
		}
		if (!$this->conf['dontBreakIfLastPageWasOnSite'] && $referrer !== '' && (
				stripos($referrer, GeneralUtility::getIndpEnv('TYPO3_SITE_URL')) !== FALSE ||
				stripos($referrer, $this->getTSFE()->baseUrl) !== FALSE ||
				stripos($referrer . '/', GeneralUtility::getIndpEnv('TYPO3_SITE_URL')) !== FALSE ||
				stripos($referrer . '/', $this->getTSFE()->baseUrl) !== FALSE
			)
		) {
			return $content;
		}

		// Break out if the session tells us that the user has selected language
		if (!$this->conf['dontBreakIfLanguageIsAlreadySelected']) {
			if ($this->cookieLifetime) {
				// read from browser-cookie
				$languageSessionKey = $_COOKIE[$this->extKey . '_languageSelected'];
			} else {
				$languageSessionKey = $this->getTSFE()->fe_user->getKey(
					'ses',
					$this->extKey . '_languageSelected'
				);
			}

			// If session key exists but no language GP var -
			// we should redirect client to selected language
			if (isset($languageSessionKey)) {
				// Can redirect only in one tree method for now
				if ($this->conf['useOneTreeMethod'] && is_numeric($languageSessionKey)) {
					$this->doRedirect($languageSessionKey, $referrer);
					return '';
				}

				return $content;
			}
		}

		//GATHER DATA

		//Get available languages
		$availableLanguagesArr = $this->conf['useOneTreeMethod'] ? $this->getSysLanguages() : $this->getMultipleTreeLanguages();
		if (TYPO3_DLOG) {
			GeneralUtility::devLog('Detecting available languages in installation', $this->extKey, 0, $availableLanguagesArr);
		}

		//Collect language aliases
		$languageAliases = array();
		if ($this->conf['useLanguageAliases']) {
			$tmp = $conf['languageAliases.'];
			foreach ($tmp as $key => $languageAlias) {
				$languageAliases[strtolower($key)] = GeneralUtility::trimExplode(
					',', //Delimiter string to explode with
					strtolower($languageAlias), //The string to explode
					TRUE //If set, all empty values (='') will NOT be set in output
				);
			}
		}

		$testOrder = GeneralUtility::trimExplode(
			',', //Delimiter string to explode with
			$conf['testOrder'], //The string to explode
			TRUE //If set, all empty values (='') will NOT be set in output
		);
		$preferredLanguageOrPageUid = FALSE;
		for ($i = 0; $i < count($testOrder) && $preferredLanguageOrPageUid === FALSE; $i++) {
			switch ($testOrder[$i]) {
				//Browser information
				case 'browser':
					//Get Accepted Languages from Browser
					$acceptedLanguagesArr = $this->getAcceptedLanguages();

					if (TYPO3_DLOG) {
						GeneralUtility::devLog('Detecting user browser languages', $this->extKey, 0, $acceptedLanguagesArr);
					}

					//Break out if the default languange is already selected
					//Thanks to Stefan Mielke
					$first = substr(key($acceptedLanguagesArr), 0, 2);
					if ($first === $this->conf['defaultLang']) {
						$preferredLanguageOrPageUid = 0;
						break;
					}
					//Iterate through the user's accepted languages
					for ($j = 0; $j < count($acceptedLanguagesArr); $j++) {
						$currentLanguage = array_values($acceptedLanguagesArr);
						$currentLanguage = $currentLanguage[$j];
						if (TYPO3_DLOG) {
							GeneralUtility::devLog('Testing language: ' . $currentLanguage, $this->extKey);
						}
						//If the current language is available (full "US_en" type check)
						if (isset($availableLanguagesArr[$currentLanguage])) {
							$preferredLanguageOrPageUid = $availableLanguagesArr[$currentLanguage];
							if (TYPO3_DLOG) {
								GeneralUtility::devLog('Found: ' . $preferredLanguageOrPageUid . ' (full check)', $this->extKey);
							}
							break;
						} else {
							// If the available language is greater (e.g. "fr-ca") as the accepted language ("fr")
							foreach ($availableLanguagesArr as $short => $languageUid) {
								if (strlen($short) > 2) {
									$availableLanguageShort = substr($short, 0, 2);
									if ($currentLanguage === $availableLanguageShort) {
										$preferredLanguageOrPageUid = $languageUid;
										break 2;
									}
								}
							}
						}
						//Old-fashioned 2-char test ("en")
						if (strlen($currentLanguage) > 2 && $preferredLanguageOrPageUid === FALSE) {
							$currentLanguageShort = substr($currentLanguage, 0, 2);
							if (isset($availableLanguagesArr[$currentLanguageShort])) {
								$preferredLanguageOrPageUid = $availableLanguagesArr[$currentLanguageShort];
								if (TYPO3_DLOG) {
									GeneralUtility::devLog('Found: ' . $preferredLanguageOrPageUid . ' (normal check)', $this->extKey);
								}
								break;
							}
						}
						//If the user's language is in language aliases
						if ($this->conf['useLanguageAliases'] && array_key_exists($currentLanguage, $languageAliases) && $preferredLanguageOrPageUid === FALSE) {
							$values = $languageAliases[$currentLanguage];
							//Iterate through aliases and choose the first possible
							foreach ($values as $value) {
								if (isset($availableLanguagesArr[$value])) {
									$preferredLanguageOrPageUid = $availableLanguagesArr[$value];
									if (TYPO3_DLOG) {
										GeneralUtility::devLog('Found: ' . $preferredLanguageOrPageUid . ' (alias check)', $this->extKey);
									}
									break 2;
								}
							}
						}
					}
					break;
				//GeoIP
				case 'ip':
					$countryCode = '';

					if ($this->conf['pearDirectory']) {
						$pearDirectory = $this->conf['pearDirectory'];
					} else {
						$pearDirectory = PEAR_INSTALL_DIR;
					}

					if (file_exists($pearDirectory . '/Net/GeoIP.php')
						&& $this->conf['pathToDatabaseForGeoIPData']) {
						require_once $pearDirectory . '/Net/GeoIP.php';
						$pathToDatabase = GeneralUtility::getFileAbsFileName(
							$this->conf['pathToDatabaseForGeoIPData']
						);
						$geoIp = new \Net_GeoIP($pathToDatabase);
						// Get country code from geoip
						if (TYPO3_DLOG) {
							GeneralUtility::devLog('IP: ' . $this->getUserIP(), $this->extKey);
						}
						$countryCode = strtolower($geoIp->lookupCountryCode($this->getUserIP()));
						if (TYPO3_DLOG) {
							GeneralUtility::devLog('GeoIP Country Code: ' . $countryCode, $this->extKey);
						}
						unset($geoIp);
					}

					// PHP module geoip
					if (!$countryCode && function_exists('geoip_country_code_by_name')) {
						// Get country code from geoip
						if (TYPO3_DLOG) {
							GeneralUtility::devLog('IP: ' . $this->getUserIP(), $this->extKey);
						}
						$countryCode = strtolower(geoip_country_code_by_name($this->getUserIP()));
						if (TYPO3_DLOG) {
							GeneralUtility::devLog('GeoIP Country Code: ' . $countryCode, $this->extKey);
						}
					}

					if ($countryCode) {
						//Check for the country code in the configured list of country to languages
						if (array_key_exists($countryCode, $this->conf['countryCodeToLanguageCode.']) && array_key_exists($this->conf['countryCodeToLanguageCode.'][$countryCode], $availableLanguagesArr)) {
							if (TYPO3_DLOG) {
								GeneralUtility::devLog('Available language found in configured: ' . $countryCode, $this->extKey);
							}
							$preferredLanguageOrPageUid = $availableLanguagesArr[$this->conf['countryCodeToLanguageCode.'][$countryCode]];
							//Use the static_info_tables lg_collate_locale to attempt to find a country to language relation.
						} elseif (ExtensionManagementUtility::isLoaded('static_info_tables')) {
							if (TYPO3_DLOG) {
								GeneralUtility::devLog('Checking in static_info_tables.', $this->extKey);
							}
							//Get the language codes from lg_collate_locate
							$values = $this->getLanguageCodesForCountry($countryCode);
							foreach ($values as $value) {
								//If one of the languages exist
								if (array_key_exists($value, $availableLanguagesArr)) {
									if (TYPO3_DLOG) {
										GeneralUtility::devLog('Found in static_info_tables: ' . $value, $this->extKey);
									}
									$preferredLanguageOrPageUid = $availableLanguagesArr[$value];
									break;
								}
							}
						}
					}
					break;
				//Handle hooks
				default:
					//Hook for adding other language processing
					if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['rlmp_language_detection']['preferredLanguageHooks'])) {
						foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['rlmp_language_detection']['preferredLanguageHooks'] as $key => $_funcRef) {
							if ($key === $testOrder[$i]) {
								$preferredLanguageOrPageUid = GeneralUtility::callUserFunction($_funcRef, $availableLanguagesArr, $this);
								if ($preferredLanguageOrPageUid) {
									break;
								}
							}
						}
					}
					break;
			}
		}

		if (TYPO3_DLOG) {
			GeneralUtility::devLog('END result: Preferred=' . $preferredLanguageOrPageUid, $this->extKey);
		}

		if ($preferredLanguageOrPageUid !== FALSE) {
			$this->doRedirect($preferredLanguageOrPageUid, $referrer);
		}
		return '';
	}

	/**
	 * @param int $preferredLanguageOrPageUid
	 * @param string $referrer
	 * @return void
	 */
	protected function doRedirect($preferredLanguageOrPageUid, $referrer) {
		if ($this->conf['useOneTreeMethod']) {
			$page = $this->getTSFE()->page;
		} else {
			/** @var \TYPO3\CMS\Frontend\Page\PageRepository $sys_page */
			$sys_page = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Page\\PageRepository');
			$sys_page->init(0);
			$page = $sys_page->getPage($preferredLanguageOrPageUid);
		}
		$url = $this->cObj->typoLink_URL(array(
			'parameter' => $page['uid'],
			'addQueryString' => TRUE,
			'addQueryString.' => array(
				'exclude' => 'id'
			),
			'additionalParams' => '&' . $this->conf['languageGPVar'] . '=' . $preferredLanguageOrPageUid
		));

		// Prefer the base URL if available
		if (strlen($this->getTSFE()->baseUrl) > 1) {
			$locationURL = $this->getTSFE()->baseURLWrap($url);
		} else {
			$locationURL = $this->conf['dontAddSchemeToURL'] ? $url : GeneralUtility::locationHeaderUrl($url);
		}

		//Set session info
		//For one tree method store selected language
		if ($this->cookieLifetime) {
			setcookie(
				$this->extKey . '_languageSelected',
				$this->conf['useOneTreeMethod'] ? $preferredLanguageOrPageUid : TRUE,
				$this->cookieLifetime + time()
			);
		} else {
			$this->getTSFE()->fe_user->setKey(
				'ses',
				$this->extKey . '_languageSelected',
				$this->conf['useOneTreeMethod'] ? $preferredLanguageOrPageUid : TRUE
			);
			$this->getTSFE()->storeSessionData();
		}

		if (TYPO3_DLOG) {
			GeneralUtility::devLog('Location to redirect to: ' . $locationURL, $this->extKey);
		}
		if (!$this->conf['dieAtEnd'] && ($preferredLanguageOrPageUid || $this->conf['forceRedirect'])) {
			if (TYPO3_DLOG) {
				GeneralUtility::devLog('Perform redirect', $this->extKey);
			}
			header('Location: ' . $locationURL);
			header('Connection: close');
			header('X-Note: Redirect by rlmp_language_detection (' . $referrer . ')');
		}

		if ($preferredLanguageOrPageUid) {
			die();
		}
	}


	/**
	 * Returns the preferred languages ("accepted languages") from the visitor's
	 * browser settings.
	 *
	 * The accepted languages are described in RFC 2616.
	 * It's a list of language codes (e.g. 'en' for english), separated by
	 * comma (,). Each language may have a quality-value (e.g. 'q=0.7') which
	 * defines a priority. If no q-value is given, '1' is assumed. The q-value
	 * is separated from the language code by a semicolon (;) (e.g. 'de;q=0.7')
	 *
	 * @return array An array containing the accepted languages; key and value = iso code, sorted by quality
	 */
	protected function getAcceptedLanguages() {
		$languagesArr = array();
		$rawAcceptedLanguagesArr = GeneralUtility::trimExplode(
			',',
			GeneralUtility::getIndpEnv('HTTP_ACCEPT_LANGUAGE'),
			TRUE
		);
		$acceptedLanguagesArr = array();
		foreach ($rawAcceptedLanguagesArr as $languageAndQualityStr) {
			list($languageCode, $quality) = GeneralUtility::trimExplode(';', $languageAndQualityStr);
			$acceptedLanguagesArr[$languageCode] = $quality ? (float)substr($quality, 2) : (float)1;
		}

		// Now sort the accepted languages by their quality and create an array containing only the language codes in the correct order.
		if (is_array($acceptedLanguagesArr)) {
			arsort($acceptedLanguagesArr);
			$languageCodesArr = array_keys($acceptedLanguagesArr);
			if (is_array($languageCodesArr)) {
				foreach ($languageCodesArr as $languageCode) {
					$languagesArr[$languageCode] = $languageCode;
				}
			}
		}
		return $languagesArr;
	}

	/**
	 * Returns an array of sys_language records containing the ISO code as the key and the record's uid as the value
	 *
	 * @return array sys_language records: ISO code => uid of sys_language record
	 */
	protected function getSysLanguages() {
		$availableLanguages = array();

		if (strlen($this->conf['defaultLang'])) {
			$availableLanguages[trim(strtolower($this->conf['defaultLang']))] = 0;
		}

		$res = $this->getDB()->exec_SELECTquery(
			'sys_language.uid, static_languages.lg_iso_2, static_languages.lg_country_iso_2',
			'sys_language JOIN static_languages ON sys_language.static_lang_isocode = static_languages.uid',
			'1=1' . $this->cObj->enableFields('sys_language') . $this->cObj->enableFields('static_languages')
		);

		while ($row = $this->getDB()->sql_fetch_assoc($res)) {
			if (TYPO3_DLOG && !$row['isocode']) {
				GeneralUtility::devLog('No ISO-code given for language with UID ' . $row['uid'], $this->extKey);
			}
			if(!empty($row['lg_country_iso_2'])) {
				$availableLanguages[trim(strtolower($row['lg_iso_2'].'-'.$row['lg_country_iso_2']))] = (int)$row['uid'];
			} else {
				$availableLanguages[trim(strtolower($row['lg_iso_2']))] = (int)$row['uid'];
			}

		}

		//Remove all languages except limitToLanguages
		if ($this->conf['limitToLanguages'] !== '') {
			$limitToLanguages = GeneralUtility::trimExplode(
				',', //Delimiter string to explode with
				strtolower($this->conf['limitToLanguages']), //The string to explode
				TRUE //If set, all empty values (='') will NOT be set in output
			);
			$tmp = array();
			foreach ($availableLanguages as $key => $value) {
				//Only add allowed languages
				if (in_array($key, $limitToLanguages)) {
					$tmp[$key] = $value;
				}
			}
			$availableLanguages = $tmp;
		}

		//Remove all languages in the exclude list
		if ($this->conf['excludeLanguages'] !== '') {
			$excludeLanguages = array();
			if ($this->conf['excludeLanguages'] !== '') {
				$excludeLanguages = GeneralUtility::trimExplode(
					',', //Delimiter string to explode with
					strtolower($this->conf['excludeLanguages']), //The string to explode
					TRUE //If set, all empty values (='') will NOT be set in output
				);
			}
			foreach ($excludeLanguages as $excludeLanguage) {
				unset($availableLanguages[$excludeLanguage]);
			}
		}

		return $availableLanguages;
	}

	/**
	 * Returns an array of available languages defined in the TypoScript configuration for this plugin.
	 * Acts as an alternative for getSysLanguages ()
	 *
	 * @return array Available languages: ISO code => Page ID of languages' root page
	 */
	protected function getMultipleTreeLanguages() {
		$availableLanguages = array();
		foreach ($this->conf['multipleTreesRootPages.'] as $isoCode => $uid) {
			$availableLanguages[trim(strtolower($isoCode))] = (int)$uid;
		}
		return $availableLanguages;
	}

	/**
	 * @param string $countryCode
	 * @return array
	 */
	protected function getLanguageCodesForCountry($countryCode) {
		/** @var \SJBR\StaticInfoTables\PiBaseApi $staticInfoObj */
		$staticInfoObj = GeneralUtility::makeInstance('SJBR\\StaticInfoTables\\PiBaseApi');
		if ($staticInfoObj->needsInit()) {
			$staticInfoObj->init();
		}
		$languages = $staticInfoObj->initLanguages(' lg_collate_locale LIKE \'%_' . $this->getDB()->quoteStr(
				strtoupper($countryCode), //Input string
				'static_languages' //Table name for which to quote string. Just enter the table that the field-value is selected from (and any DBAL will look up which handler to use and then how to quote the string!).
			) . '\' ');

		return array_map('strtolower', array_keys($languages));
	}

	/**
	 * Returns the user's IP
	 *
	 * @return string IP address
	 */
	protected function getUserIP() {
		return GeneralUtility::getIndpEnv('REMOTE_ADDR');
	}

	/**
	 * @return TypoScriptFrontendController
	 */
	protected function getTSFE() {
		return $GLOBALS['TSFE'];
	}

	/**
	 * @return DatabaseConnection
	 */
	protected function getDB() {
		return $GLOBALS['TYPO3_DB'];
	}

	/**
	 * @return boolean
	 */
	protected function isBot() {
		$userAgent = GeneralUtility::getIndpEnv('HTTP_USER_AGENT');

		return isset($userAgent) && preg_match($this->botPattern, $userAgent);
	}

	/**
	 * Test function for preferredLanguageHooks
	 * Prints arguments and dies.
	 *
	 * @param array $availableLanguagesArr Associative array containing available languages. Key is ISO 639-1 language code. Value is TYPO3 Website Language UID.
	 * @param LanguageDetection $parentObject Reference to the calling object.
	 *
	 * @return void
	 */
	public function testPreferredLanguageHooks($availableLanguagesArr, LanguageDetection $parentObject) {
		debug($availableLanguagesArr);
		debug($parentObject);
		die();
	}

}
