<?php
declare(strict_types=1);

namespace Rlmp\RlmpLanguageDetection;

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

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use SJBR\StaticInfoTables\PiBaseApi;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageRepository;
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
class LanguageDetection extends AbstractPlugin  implements LoggerAwareInterface
{
    use LoggerAwareTrait;

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
    public $conf = [];

    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManager
     * @inject
     */
    protected $objectManager;

    /**
     * @var int
     */
    protected $cookieLifetime = 0;

    /**
     * @var string
     */
    protected $botPattern = '/bot|crawl|slurp|spider/i';

    /**
     * @var Logger
     */
    protected $customLogger;

    public function __construct()
    {
        parent::__construct();
        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);

        $this->setCustomLogger();
    }

    public function setCustomLogger()
    {
        /** @var \TYPO3\CMS\Core\Log\LogManager $logManager */
        $logManager = $this->objectManager->get(LogManager::class);

        $this->customLogger = $logManager->getLogger(__CLASS__);
    }


    /**
     * The main function recognizes the browser's preferred languages and
     * reloads the page accordingly. Exits if successful.
     *
     * @param string $content HTML content
     * @param array $conf The mandatory configuration array
     * @return string
     */
    public function main(string $content, array $conf):string
    {
        $this->conf = $conf;
        $this->cookieLifetime = (int)$conf['cookieLifetime'];

        // Break out if a spider/search engine bot is visiting the website
        if ($this->isBot()) {
            return $content;
        }

        // Break out if language already selected
        if (!$this->conf['dontBreakIfLanguageIsAlreadySelected']
            && GeneralUtility::_GP($this->conf['languageGPVar']) !== null
            && GeneralUtility::_GP($this->conf['languageGPVar']) !== ''
        ) {

            $this->customLogger->info($this->extKey . ' Break out since language is already selected');

            return $content;
        }

        // Break out if the last page visited was also on our site:
        $referrer = (string)GeneralUtility::getIndpEnv('HTTP_REFERER');

        $this->customLogger->info($this->extKey . ' Referrer: ' . $referrer);

        if (!$this->conf['dontBreakIfLastPageWasOnSite']
            && $referrer !== ''
            && (
                stripos($referrer, GeneralUtility::getIndpEnv('TYPO3_SITE_URL')) !== false
                || stripos($referrer, $this->getTSFE()->baseUrl) !== false
                || stripos($referrer . '/', GeneralUtility::getIndpEnv('TYPO3_SITE_URL')) !== false
                || stripos($referrer . '/', $this->getTSFE()->baseUrl) !== false
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
                $languageSessionKey = $this->getTSFE()->fe_user->getKey('ses', $this->extKey . '_languageSelected');
            }

            // If session key exists but no language GP var -
            // we should redirect client to selected language
            if (isset($languageSessionKey)) {
                // Can redirect only in one tree method for now
                if ($this->conf['useOneTreeMethod'] && is_numeric($languageSessionKey)) {
                    $this->doRedirect((int)$languageSessionKey, $referrer);

                    return '';
                }

                return $content;
            }
        }

        //Get available languages
        $availableLanguagesArr = $this->conf['useOneTreeMethod'] ? $this->getSysLanguages() : $this->getMultipleTreeLanguages();

        $this->customLogger->log(0,$this->extKey . ' Detecting available languages in installation', $availableLanguagesArr);

        //Collect language aliases
        $languageAliases = [];
        if ($this->conf['useLanguageAliases']) {
            $tmp = $conf['languageAliases.'];
            foreach ($tmp as $key => $languageAlias) {
                $languageAliases[strtolower($key)] = GeneralUtility::trimExplode(
                    ',',
                    strtolower($languageAlias),
                    true
                );
            }
        }

        $testOrder = GeneralUtility::trimExplode(
            ',',
            $conf['testOrder'],
            true
        );
        $preferredLanguageOrPageUid = false;
        for ($i = 0; $i < count($testOrder) && $preferredLanguageOrPageUid === false; $i++) {
            switch ($testOrder[$i]) {
                //Browser information
                case 'browser':
                    //Get Accepted Languages from Browser
                    $acceptedLanguagesArr = $this->getAcceptedLanguages();

                    if (empty($acceptedLanguagesArr)) {
                        break;
                    }

                    $this->customLogger->log(0,$this->extKey . ' Detecting available languages in installation', $acceptedLanguagesArr);


                    //Break out if the default languange is already selected
                    //Thanks to Stefan Mielke
                    $first = substr(key($acceptedLanguagesArr), 0, 2);
                    if ($first === $this->conf['defaultLang']) {
                        $preferredLanguageOrPageUid = 0;
                        break;
                    }
                    //Iterate through the user's accepted languages
                    foreach ($acceptedLanguagesArr as $currentLanguage) {

                        $this->customLogger->info($this->extKey . ' Testing language: ' . $currentLanguage);

                        //If the current language is available (full "US_en" type check)
                        if (isset($availableLanguagesArr[$currentLanguage])) {
                            $preferredLanguageOrPageUid = $availableLanguagesArr[$currentLanguage];

                            $this->customLogger->info($this->extKey . ' Found: ' . $preferredLanguageOrPageUid . ' (full check)');
                            break;
                        }

                        // If the available language is greater (e.g. "fr-ca") as the accepted language ("fr")
                        foreach ($availableLanguagesArr as $short => $languageUid) {
                            if (\strlen($short) > 2) {
                                $availableLanguageShort = substr($short, 0, 2);
                                if ($currentLanguage === $availableLanguageShort) {
                                    $preferredLanguageOrPageUid = $languageUid;
                                    break 2;
                                }
                            }
                        }

                        //Old-fashioned 2-char test ("en")
                        if ($preferredLanguageOrPageUid === false && \strlen($currentLanguage) > 2) {
                            $currentLanguageShort = substr($currentLanguage, 0, 2);
                            if (isset($availableLanguagesArr[$currentLanguageShort])) {
                                $preferredLanguageOrPageUid = $availableLanguagesArr[$currentLanguageShort];

                                $this->customLogger->info($this->extKey . ' Found: ' . $preferredLanguageOrPageUid . ' (normal check)');
                                break;
                            }
                        }
                        //If the user's language is in language aliases
                        if ($this->conf['useLanguageAliases'] && array_key_exists($currentLanguage, $languageAliases) && $preferredLanguageOrPageUid === false) {
                            $values = $languageAliases[$currentLanguage];
                            //Iterate through aliases and choose the first possible
                            foreach ($values as $value) {
                                if (isset($availableLanguagesArr[$value])) {
                                    $preferredLanguageOrPageUid = $availableLanguagesArr[$value];

                                    $this->customLogger->info($this->extKey . ' Found: ' . $preferredLanguageOrPageUid . ' (alias check)');
                                    break 2;
                                }
                            }
                        }
                    }
                    break;
                //GeoIP
                case 'ip':
                    if ($this->conf['pearDirectory']) {
                        $pearDirectory = $this->conf['pearDirectory'];
                    } else {
                        $pearDirectory = PEAR_INSTALL_DIR;
                    }

                    if ($this->conf['pathToDatabaseForGeoIPData'] && file_exists($pearDirectory . '/Net/GeoIP.php')) {
                        require_once $pearDirectory . '/Net/GeoIP.php';
                        $pathToDatabase = GeneralUtility::getFileAbsFileName($this->conf['pathToDatabaseForGeoIPData']);
                        $geoIp = new \Net_GeoIP($pathToDatabase);
                        // Get country code from geoip
                        $this->customLogger->info($this->extKey . ' IP: ' . $this->getUserIP());
                        $countryCode = strtolower($geoIp->lookupCountryCode($this->getUserIP()));
                        $this->customLogger->info($this->extKey . ' GeoIP Country Code: ' . $countryCode);
                        unset($geoIp);
                    }

                    // PHP module geoip
                    if (!$countryCode && function_exists('geoip_country_code_by_name')) {
                        // Get country code from geoip
                        $this->customLogger->info($this->extKey . ' IP: ' . $this->getUserIP());
                        $countryCode = geoip_country_code_by_name($this->getUserIP());
                        $this->customLogger->info($this->extKey . ' GeoIP Country Code: ' . $countryCode);
                    }

                    if ($countryCode) {
                        $countryCode = strtolower($countryCode);

                        //Check for the country code in the configured list of country to languages
                        if (array_key_exists($countryCode, $this->conf['countryCodeToLanguageCode.'])
                            && array_key_exists($this->conf['countryCodeToLanguageCode.'][$countryCode], $availableLanguagesArr)
                        ) {
                            $this->customLogger->info($this->extKey . ' Available language found in configured: ' . $countryCode);
                            $preferredLanguageOrPageUid = $availableLanguagesArr[$this->conf['countryCodeToLanguageCode.'][$countryCode]];
                            //Use the static_info_tables lg_collate_locale to attempt to find a country to language relation.
                        } elseif (ExtensionManagementUtility::isLoaded('static_info_tables')) {
                            $this->customLogger->info($this->extKey . ' Checking in static_info_tables.');
                            //Get the language codes from lg_collate_locate
                            $values = $this->getLanguageCodesForCountry($countryCode);
                            foreach ($values as $value) {
                                //If one of the languages exist
                                if (array_key_exists($value, $availableLanguagesArr)) {
                                    $this->customLogger->info($this->extKey . ' Found in static_info_tables: ' . $value);
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
                                $preferredLanguageOrPageUid = GeneralUtility::callUserFunction($_funcRef, $availableLanguagesArr,
                                    $this);
                                if ($preferredLanguageOrPageUid) {
                                    break;
                                }
                            }
                        }
                    }
                    break;
            }
        }

        $this->customLogger->info($this->extKey . ' END result: Preferred=' . $preferredLanguageOrPageUid);

        if ($preferredLanguageOrPageUid !== false) {
            $this->doRedirect((int)$preferredLanguageOrPageUid, $referrer);
        }

        return '';
    }

    /**
     * @param int $preferredLanguageOrPageUid
     * @param string $referrer
     * @return void
     */
    protected function doRedirect(int $preferredLanguageOrPageUid, string $referrer)
    {
        if ($this->conf['useOneTreeMethod']) {
            $page = $this->getTSFE()->page;
        } else {
            /** @var PageRepository $sys_page */
            $sys_page = GeneralUtility::makeInstance(PageRepository::class);
            $sys_page->__call('init', [0]);
            $page = $sys_page->getPage($preferredLanguageOrPageUid);
        }
        $pageId = method_exists($this->getTSFE(), 'getRequestedId') ? $this->getTSFE()->getRequestedId() : $page['uid'];
        //Add id to url GET parameters to remove
        $removeParams = array('id');
        //Check allowed url GET parameters if configured
        if ($this->conf['allowedParams']) {
            $getVariables = GeneralUtility::_GET();
            if (isset($getVariables) && is_array($getVariables)) {
                $allowedParams = GeneralUtility::trimExplode(',', $this->conf['allowedParams'], true);
                //"type" and "MP" GET parameters are allowed by default
                $allowedParams = array_merge($allowedParams, array('type', 'MP'));
                $this->getTSFE()->calculateLinkVars();
                parse_str($this->getTSFE()->linkVars, $query);
                $allowedParams = array_merge($allowedParams, array_keys($query));
                $disallowedParams = array_diff(array_keys($getVariables), $allowedParams);
                // Add disallowed parameters to parameters to remove
                $removeParams = array_merge($removeParams, $disallowedParams);
            }
        }

        $urlParams = [
            'parameter' => $pageId,
            'addQueryString' => true,
            'addQueryString.' => [
                'exclude' => implode(',', $removeParams)
            ]
        ];

        if ($this->conf['useOneTreeMethod']) {
            $urlParams['additionalParams'] = '&' . $this->conf['languageGPVar'] . '=' . $preferredLanguageOrPageUid;
        }

        $url = $this->cObj->typoLink_URL($urlParams);

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
                (string)($this->conf['useOneTreeMethod'] ? $preferredLanguageOrPageUid : true),
                $this->cookieLifetime + time()
            );
        } else {
            $this->getTSFE()->fe_user->setKey(
                'ses',
                $this->extKey . '_languageSelected',
                $this->conf['useOneTreeMethod'] ? $preferredLanguageOrPageUid : true
            );
            $this->getTSFE()->fe_user->storeSessionData();
        }

        $this->customLogger->info($this->extKey . ' Location to redirect to: ' . $locationURL);
        if (!$this->conf['dieAtEnd'] && ($preferredLanguageOrPageUid || $this->conf['forceRedirect'])) {
            $this->customLogger->info($this->extKey . ' Perform redirect');
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
    protected function getAcceptedLanguages():array
    {
        $languagesArr = [];
        $rawAcceptedLanguagesArr = GeneralUtility::trimExplode(',', GeneralUtility::getIndpEnv('HTTP_ACCEPT_LANGUAGE'), true);
        $acceptedLanguagesArr = [];
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
    protected function getSysLanguages():array
    {
        $availableLanguages = [];

        if (\strlen($this->conf['defaultLang'])) {
            $availableLanguages[trim(strtolower($this->conf['defaultLang']))] = 0;
        }

        $res = $this->getQueryBuilder()
            ->select('sys.uid', 'static.lg_iso_2', 'static.lg_country_iso_2')
            ->from('sys_language', 'sys')
            ->join('sys', 'static_languages', 'static', 'sys.static_lang_isocode = static.uid')
            ->execute();

        while ($row = \mysqli_fetch_assoc($res)) {
            if (!$row['isocode']) {
                $this->customLogger->info($this->extKey . ' No ISO-code given for language with UID ' . $row['uid']);
            }
            if (!empty($row['lg_country_iso_2'])) {
                $availableLanguages[trim(strtolower($row['lg_iso_2'] . '-' . $row['lg_country_iso_2']))] = (int)$row['uid'];
            } else {
                $availableLanguages[trim(strtolower($row['lg_iso_2']))] = (int)$row['uid'];
            }
        }

        //Remove all languages except limitToLanguages
        if ($this->conf['limitToLanguages'] !== '') {
            $limitToLanguages = GeneralUtility::trimExplode(
                ',',
                strtolower($this->conf['limitToLanguages']),
                true
            );
            $tmp = [];
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
            $excludeLanguages = GeneralUtility::trimExplode(
                ',',
                strtolower($this->conf['excludeLanguages']),
                true
            );
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
    protected function getMultipleTreeLanguages():array
    {
        $availableLanguages = [];
        foreach ($this->conf['multipleTreesRootPages.'] as $isoCode => $uid) {
            $availableLanguages[strtolower(trim($isoCode))] = (int)$uid;
        }

        return $availableLanguages;
    }

    /**
     * @param string $countryCode
     * @return array
     */
    protected function getLanguageCodesForCountry(string $countryCode):array
    {
        /** @var PiBaseApi $staticInfoObj */
        $staticInfoObj = GeneralUtility::makeInstance(PiBaseApi::class);
        if ($staticInfoObj->needsInit()) {
            $staticInfoObj->init();
        }
        $languages = $staticInfoObj->initLanguages(
            ' lg_collate_locale LIKE \'%_' . $this->getQueryBuilder()->quote(strtoupper($countryCode), MYSQLI_TYPE_STRING) . '\' '
        );

        return array_map('strtolower', array_keys($languages));
    }

    /**
     * Returns the user's IP
     *
     * @return string IP address
     */
    protected function getUserIP():string
    {
        return GeneralUtility::getIndpEnv('HTTP_CLIENT_IP') ?? GeneralUtility::getIndpEnv('HTTP_X_FORWARDED_FOR') ?? GeneralUtility::getIndpEnv('REMOTE_ADDR');
    }

    /**
     * @return TypoScriptFrontendController
     */
    protected function getTSFE():TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'];
    }

    /**
     * @return QueryBuilder
     */
    protected function getQueryBuilder(): QueryBuilder
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_language');
    }

    /**
     * @return bool
     */
    protected function isBot():bool
    {
        $userAgent = GeneralUtility::getIndpEnv('HTTP_USER_AGENT');

        return isset($userAgent) && preg_match($this->botPattern, $userAgent);
    }

    /**
     * Test function for preferredLanguageHooks
     * Prints arguments and dies.
     *
     * @param array $availableLanguagesArr Associative array containing available languages. Key is ISO 639-1 language code.
     *     Value is TYPO3 Website Language UID.
     * @param LanguageDetection $parentObject Reference to the calling object.
     *
     * @return void
     */
    public function testPreferredLanguageHooks($availableLanguagesArr, LanguageDetection $parentObject)
    {
        debug($availableLanguagesArr);
        debug($parentObject);
        die();
    }
}
