<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2003-2006 robert lemke medienprojekte (rl@robertlemke.de)
 *  (c) 2008 Mathias Bolt Lesniak, LiliO Design (mathias@lilio.com)
 *  (c) 2010 Joachim Mathes, punkt.de GmbH (t3extensions@punkt.de)
 *  All rights reserved
 *
 *  This script is part of the Typo3 project. The Typo3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Plugin 'Language Detection' for the 'rlmp_language_detection' extension.
 *
 * @author    robert lemke medienprojekte <rl@robertlemke.de>
 * @author    Mathias Bolt Lesniak, LiliO Design <mathias@lilio.com>
 * @author    Joachim Mathes, punkt.de GmbH <t3extensions@punkt.de>
 */
class tx_rlmplanguagedetection_pi1 extends tslib_pibase {
	var $prefixId = 'tx_rlmplanguagedetection_pi1'; // Same as class name
	var $scriptRelPath = 'pi1/class.tx_rlmplanguagedetection_pi1.php'; // Path to this script relative to the extension dir.
	var $extKey = 'rlmp_language_detection'; // The extension key.
	var $conf = array();

	/**
	 * The main function recognizes the browser's preferred languages and
	 * reloads the page accordingly. Exits if successful.
	 *
	 * @param    string $content : HTML content
	 * @param    array  $conf    : The mandatory configuration array
	 *
	 * @return    string
	 */
	function main($content, $conf) {
		$this->conf = $conf;
		$this->cookieLifetime = intval($conf['cookieLifetime']);

		// Break out if language already selected
		if (!$this->conf['dontBreakIfLanguageIsAlreadySelected'] && \TYPO3\CMS\Core\Utility\GeneralUtility::_GP($this->conf['languageGPVar']) !== NULL) {
			return $content;
		}

		// Break out if the last page visited was also on our site:
		$referrer = \TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('HTTP_REFERER');
		if (TYPO3_DLOG) {
			\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('Referer: ' . $referrer, $this->extKey);
		}
		if (!$this->conf['dontBreakIfLastPageWasOnSite'] && strlen($referrer) && (
				stripos($referrer, \TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('TYPO3_SITE_URL')) !== FALSE ||
				stripos($referrer, $GLOBALS['TSFE']->baseUrl) !== FALSE ||
				stripos($referrer . '/', \TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('TYPO3_SITE_URL')) !== FALSE ||
				stripos($referrer . '/', $GLOBALS['TSFE']->baseUrl) !== FALSE
			)
		) {
			return $content;
		}

		// Break out if the session tells us that the user has selected language
		if (!$this->conf['dontBreakIfLanguageIsAlreadySelected']) {
			if ($this->cookieLifetime) {
				// read from browser-cookie
				$langSessKey = $_COOKIE[$this->extKey . '_languageSelected'];
			} else {
				$langSessKey = $GLOBALS["TSFE"]->fe_user->getKey(
					'ses',
					$this->extKey . '_languageSelected'
				);
			}

			// If session key exists but no language GP var -
			// we should redirect client to selected language
			if (isset($langSessKey)) {
				// Can redirect only in one tree method for now
				if ($this->conf['useOneTreeMethod'] && is_numeric($langSessKey)) {
					$this->doRedirect($langSessKey, $referrer);
					return;
				}

				return $content;
			}
		}

		//GATHER DATA

		//Get available languages
		$availableLanguagesArr = $this->conf['useOneTreeMethod'] ? $this->getSysLanguages() : $this->getMultipleTreeLanguages();

		//Collect language aliases
		$languageAliases = array();
		if ($this->conf['useLanguageAliases']) {
			$tmp = $conf['languageAliases.'];
			foreach ($tmp as $key => $languageAlias) {
				$languageAliases[strtolower($key)] = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(
					',', //Delimiter string to explode with
					strtolower($languageAlias), //The string to explode
					TRUE //If set, all empty values (='') will NOT be set in output
				);
			}
		}

		$testOrder = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(
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
							\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('Testing language: ' . $currentLanguage, $this->extKey);
						}
						//If the current language is available (full "US_en" type check)
						if (isset($availableLanguagesArr[$currentLanguage])) {
							$preferredLanguageOrPageUid = $availableLanguagesArr[$currentLanguage];
							if (TYPO3_DLOG) {
								\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('Found: ' . $preferredLanguageOrPageUid . ' (full check)', $this->extKey);
							}
							break;
						}
						//Old-fashioned 2-char test ("en")
						if (strlen($currentLanguage) > 2 && $preferredLanguageOrPageUid === FALSE) {
							$currentLanguageShort = substr($currentLanguage, 0, 2);
							if (isset($availableLanguagesArr[$currentLanguageShort])) {
								$preferredLanguageOrPageUid = $availableLanguagesArr[$currentLanguageShort];
								if (TYPO3_DLOG) {
									\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('Found: ' . $preferredLanguageOrPageUid . ' (normal check)', $this->extKey);
								}
								break;
							}
						}
						//If the user's language is in language aliases
						if ($this->conf['useLanguageAliases'] && array_key_exists($currentLanguage, $languageAliases) && $preferredLanguageOrPageUid === FALSE) {
							$values = $languageAliases[$currentLanguage];
							//Iterate through aliases and choose the first possible
							foreach ($values as $value) {
								if (array_key_exists($value, $availableLanguagesArr)) {
									$preferredLanguageOrPageUid = $availableLanguagesArr[$value];
									if (TYPO3_DLOG) {
										\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('Found: ' . $preferredLanguageOrPageUid . ' (alias check)', $this->extKey);
									}
									break 2;
								}
							}
						}
					}
					break;
				//GeoIP
				case 'ip':
					if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('ml_geoip')) {
						$geoip = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('tx_mlgeoip');
						//Get country code from geoip
						if (TYPO3_DLOG) {
							\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('IP: ' . $this->getUserIP(), $this->extKey);
						}
						$countryCode = strtolower($geoip->getCountryCodeByAddress($this->getUserIP()));
						if (TYPO3_DLOG) {
							\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('GeoIP Country Code: ' . $countryCode, $this->extKey);
						}
						unset($geoip);
						if ($countryCode) {
							//Check for the country code in the configured list of country to languages
							if (array_key_exists($countryCode, $this->conf['countryCodeToLanguageCode.']) && array_key_exists($this->conf['countryCodeToLanguageCode.'][$countryCode], $availableLanguagesArr)) {
								if (TYPO3_DLOG) {
									\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('Available language found in configured: ' . $countryCode, $this->extKey);
								}
								$preferredLanguageOrPageUid = $availableLanguagesArr[$this->conf['countryCodeToLanguageCode.'][$countryCode]];
								//Use the static_info_tables lg_collate_locale to attempt to find a country to language relation.
							} elseif (\TYPO3\CMS\Core\Utility\GeneralUtility::isLoaded('static_info_tables')) {
								if (TYPO3_DLOG) {
									\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('Checking in static_info_tables.', $this->extKey);
								}
								//Get the language codes from lg_collate_locate
								$values = $this->getLanguageCodesForCountry($countryCode);
								foreach ($values as $value) {
									//If one of the languages exist
									if (array_key_exists($value, $availableLanguagesArr)) {
										if (TYPO3_DLOG) {
											\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('Found in static_info_tables: ' . $value, $this->extKey);
										}
										$preferredLanguageOrPageUid = $availableLanguagesArr[$value];
										break;
									}
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
							if ($key == $testOrder[$i]) {
								$preferredLanguageOrPageUid = t3lib_div::callUserFunction($_funcRef, $availableLanguagesArr, $this);
								if ($preferredLanguageOrPageUid) {
									break;
								}
							}
						}
					}
					break;
			}
		}

		if (TYPO3_DLOG)
			\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('END result: Preferred=' . $preferredLanguageOrPageUid, $this->extKey);

		if ($preferredLanguageOrPageUid !== FALSE)
			$this->doRedirect($preferredLanguageOrPageUid, $referrer);
	}

	private function doRedirect($preferredLanguageOrPageUid, $referer) {
		if ($this->conf['useOneTreeMethod']) {
			$page = $GLOBALS['TSFE']->page;
		} else {
			$sys_page = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('t3lib_pageSelect');
			$sys_page->init(0);
			$page = $sys_page->getPage($preferredLanguageOrPageUid);
		}
		$linkData = $GLOBALS['TSFE']->tmpl->linkData($page, '', 0, '', array(), '&' . $this->conf['languageGPVar'] . '=' . $preferredLanguageOrPageUid . "&" . \TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('QUERY_STRING'));
		$locationURL = $this->conf['dontAddSchemeToURL'] ? $linkData['totalURL'] : \TYPO3\CMS\Core\Utility\GeneralUtility::locationHeaderUrl($linkData['totalURL']);

		//Prefer the base URL if available
		if (strlen($GLOBALS['TSFE']->baseUrl) > 1) {
			$locationURL = $GLOBALS['TSFE']->baseURLWrap($linkData['totalURL']);
		} else {
			$locationURL = $this->conf['dontAddSchemeToURL'] ? $linkData['totalURL'] : \TYPO3\CMS\Core\Utility\GeneralUtility::locationHeaderUrl($linkData['totalURL']);
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
			$GLOBALS["TSFE"]->fe_user->setKey(
				'ses',
				$this->extKey . '_languageSelected',
				$this->conf['useOneTreeMethod'] ? $preferredLanguageOrPageUid : TRUE
			);
			$GLOBALS['TSFE']->storeSessionData();
		}

		if (TYPO3_DLOG) {
			\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('Location to redirect to: ' . $locationURL, $this->extKey);
		}
		if (!$this->conf['dieAtEnd'] && $preferredLanguageOrPageUid != 0) {
			header('Location: ' . $locationURL);
			//header('Referer: '.$locationURL);
			header('Connection: close');
			header('X-Note: Redirect by rlmp_language_detection (' . $referer . ')');
		}

		if ($preferredLanguageOrPageUid != 0) {
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
	 * @return    array    An array containing the accepted languages; key and value = iso code, sorted by quality
	 */
	function getAcceptedLanguages() {
		$languagesArr = array();
		$rawAcceptedLanguagesArr = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(
			',',
			\TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('HTTP_ACCEPT_LANGUAGE'),
			1
		);
		foreach ($rawAcceptedLanguagesArr as $languageAndQualityStr) {
			list($languageCode, $quality) = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(';', $languageAndQualityStr);
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
	 * @return    array    sys_language records: ISO code => uid of sys_language record
	 * @access    private
	 */
	function getSysLanguages() {
		$availableLanguages = array();

		if (strlen($this->conf['defaultLang'])) {
			$availableLanguages[0] = trim(strtolower($this->conf['defaultLang']));
		}

		// Two options: prior TYPO3 3.6.0 the title of the sys_language entry must be one of the two-letter iso codes in order
		// to detect the language. But if the static_languages is installed and the sys_language record points to the correct
		// language, we can use that information instead.

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'lg_iso_2',
			'static_languages',
			'1=1'
		);
		if (!$this->conf['useOldOneTreeConcept'] && $res) {
			// Table and field exist so create query for the new approach:
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'sys_language.uid, static_languages.lg_iso_2 as isocode',
				'sys_language LEFT JOIN static_languages ON sys_language.static_lang_isocode = static_languages.uid',
				'1=1' . $this->cObj->enableFields('sys_language') . $this->cObj->enableFields('static_languages')
			);
		} else {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'sys_language.uid, sys_language.title as isocode',
				'sys_language',
				'1=1' . $this->cObj->enableFields('sys_language')
			);
		}
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			if (TYPO3_DLOG && !$row['isocode']) {
				\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('No ISO-code given for language with UID ' . $row['uid'], $this->extKey);
			}
			$availableLanguages[$row['uid']] = trim(strtolower($row['isocode']));
		}

		// Get the isocodes associated with the available sys_languade uid's
		if (is_array($availableLanguages)) {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'sys_language.uid, static_languages.lg_iso_2 as isocode, static_languages.lg_country_iso_2',
				'sys_language LEFT JOIN static_languages ON sys_language.static_lang_isocode=static_languages.uid',
				'sys_language.uid IN(' . implode(',', array_keys($availableLanguages)) . ')' .
				$this->cObj->enableFields('sys_language') .
				$this->cObj->enableFields('static_languages')
			);
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$tmpLanguages[trim(strtolower($row['isocode'] . ($row['lg_country_iso_2'] ? '-' . $row['lg_country_iso_2'] : '')))] = $row['uid'];
			}
			$availableLanguages = $tmpLanguages;
		}

		//Remove all languages except limitToLanguages
		if ($this->conf['limitToLanguages'] != '') {
			$limitToLanguages = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(
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
		if ($this->conf['excludeLanguages'] != '') {
			if ($this->conf['excludeLanguages'] != '') {
				$excludeLanguages = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(
					',', //Delimiter string to explode with
					strtolower($this->conf['excludeLanguages']), //The string to explode
					TRUE //If set, all empty values (='') will NOT be set in output
				);
			}
			$tmp = array();
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
	 * @return    array    available languages: ISO code => Page ID of languages' root page
	 * @access    private
	 */
	function getMultipleTreeLanguages() {
		foreach ($this->conf['multipleTreesRootPages.'] as $isoCode => $uid) {
			$availableLanguages [trim(strtolower($isoCode))] = intval($uid);
		}
		return $availableLanguages;
	}


	function getLanguageCodesForCountry($countryCode) {
		$staticInfoObj = & \TYPO3\CMS\Core\Utility\GeneralUtility::getUserObj('&tx_staticinfotables_pi1');
		if ($staticInfoObj->needsInit()) {
			$staticInfoObj->init();
		}
		$languages = $staticInfoObj->initLanguages(' lg_collate_locale LIKE \'%_' . $GLOBALS['TYPO3_DB']->quoteStr(
				strtoupper($countryCode), //Input string
				'static_languages' //Table name for which to quote string. Just enter the table that the field-value is selected from (and any DBAL will look up which handler to use and then how to quote the string!).
			) . '\' ');

		$tmp = array();
		foreach ($languages as $key => $value) {
			$tmp[] = strtolower($key);
		}

		return $tmp;
	}

	/**
	 * Returns the user's IP
	 *
	 * @return    string    IP address
	 */
	function getUserIP() {
		return \TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('REMOTE_ADDR');
	}

	/**
	 * Test function for preferredLanguageHooks
	 * Prints arguments and dies.
	 *
	 * @param    array         Associative array containing available languages. Key is ISO 639-1 language code. Value is TYPO3 Website Language UID.
	 * @param    object        Reference to the calling object.
	 *
	 * @return    void
	 */
	function test_preferredLanguageHooks($availableLanguagesArr, $parentObject) {
		debug($availableLanguagesArr);
		debug($parentObject);
		die();
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/rlmp_language_detection/pi1/class.tx_rlmplanguagedetection_pi1.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/rlmp_language_detection/pi1/class.tx_rlmplanguagedetection_pi1.php']);
}