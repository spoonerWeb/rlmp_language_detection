

.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. ==================================================
.. DEFINE SOME TEXTROLES
.. --------------------------------------------------
.. role::   underline
.. role::   typoscript(code)
.. role::   ts(typoscript)
   :class:  typoscript
.. role::   php(code)

.. highlight:: ts

TypoScript Reference
^^^^^^^^^^^^^^^^^^^^

.. ### BEGIN~OF~TABLE ###

.. container:: table-row

   Property
         useOneTreeMethod

   Data type
         boolean

   Description
         If true, the One-Tree-Method will be used, otherwise the Multiple-
         Trees concept is chosen.

         See the manual for more information about these two concepts.

   Default
         1


.. container:: table-row

   Property
         multipleTreesRootPages

   Data type
         array of isocodes

   Description
         When using the Multiple-Trees-Method, you have to define the different
         languages which are available throughout your website. You do that by
         using ISO codes for the languages and pointing them to the appropriate
         page id.

         Example:

         ::

            multipleTreesRootPages  {
               de = 216
               en = 120
               es = 482
            }

   Default
         (see TypoScript file)

.. container:: table-row

   Property
         defaultLang

   Data type
         string

   Description
         Set this to the ISO code of your default language (L=0)

         ::

            plugin.tx_rlmplanguagedetection_pi1 {
               defaultLang = de
            }


   Default
         (empty)

.. container:: table-row

   Property
         dontAddSchemeToURL

   Data type
         boolean

   Description
         When the URI is built for redirecting to a different page, the URL is
         parsed through a function which adds the correct scheme. I.e.
         *246.0.html?L=1* will be transformed to *http://example.com/246.0.html?L=1*

         This behavior makes sense of course, but you might have a certain
         environment (some testing setup) where you want to disable this
         feature. In that case, set dontAddSchemeToURL to 1.

         For most people it's safe to leave setting as is.

   Default
         0


.. container:: table-row

   Property
         useLanguageAliases

   Data type
         boolean

   Description
         Enables selecting preferred language from a list. E.g.: If user's
         preference of Swedish language is not available, the script will test
         for languages from a customizable list of relatives.

   Default
         1


.. container:: table-row

   Property
         languageAliases

   Data type
         array of strings

   Description
         Preferred language alternatives (iso 2 char codes). Feel free to
         suggest language preferences which can be included in future versions
         of this extension.

         Example:

         ::

            ...
            languageAliases  {
               no = dk,sv
               dk = no,sv
               sv = no,dk
            }

         To make this setting work also after the first page, remember to set
         this TypoScript:

         ::

            config.sys\_language\_mode= content\_fallback; *{list}*


         Where {list} is a comma separated list of the order in which you want
         languages to be tested, e.g.: 1,0 (tests for content translations in
         language UID 1 before 0)

   Default
         | no = dk,sv
         | dk = no,sv
         | sv = no,dk


.. container:: table-row

   Property
         dontBreakIfLanguageIsAlreadySelected

   Data type
         boolean

   Description
         If set, the script will still run if language is already selected. NB!
         May lead to infinite loop.

   Default
         0


.. container:: table-row

   Property
         dontBreakIfLastPageWasOnSite

   Data type
         boolean

   Description
         If set, the script will still run if the referring page was on the
         same site. NB! May lead to infinite loop.

   Default
         0


.. container:: table-row

   Property
         dontBreakIfLanguageAlreadySelected

   Data type
         boolean

   Description
         If set, the script will still run if the language detection has
         already been processed before.

   Default
         0


.. container:: table-row

   Property
         testOrder

   Data type
         string

   Description
         Comma separated list of tests to run to find the user's language.

         - browser: Checks the browser's language settings
         - ip: Finds the language of the country of the IP address. First checks
           TypoScript countryCodeToLanguageCode, then static\_info\_tables' lg\_collate\_language for a country code.
         - And any hook value.

   Default
         browser,ip


.. container:: table-row

   Property
         limitToLanguages

   Data type
         string

   Description
         Comma separated list of ISO 2 char language codes (e.g.: "en" or "en-us")
         that are the only ones which should be considered. If left empty,
         this setting is ignored. This setting can be overruled by excludeLanguages.

   Default
         (empty)

.. container:: table-row

   Property
         excludeLanguages

   Data type
         string

   Description
         Comma separated list of ISO 2 char language codes (e.g.: "en" or "en-us")
         that should be excluded from being considered. If left empty,
         this setting is ignored. This setting has priority over limitToLanguages.

   Default
         (empty)

.. container:: table-row

   Property
         countryCodeToLanguageCode

   Data type
         array of strings

   Description
         Used by the IP country check. This list converts a country code into
         the preferred language code for users from that country.

         Example:

         ::

            ...
            countryCodeToLanguageCode  {
               us = en
               gb = en
               nz = en
            }

   Default
         *(see TypoScript)*


.. container:: table-row

   Property
         useOldOneTreeConcept

   Data type
         boolean

   Description
         Use the old One-Tree concept where the name of the Website Language
         records specifies the language code. Also should be used for TYPO3 version below 7.

   Default
         0


.. container:: table-row

   Property
         languageGPVar

   Data type
         string

   Description
         The string to use for the language parameter in URLs.

   Default
         L


.. container:: table-row

   Property
         dieAtEnd

   Data type
         boolean

   Description
         If redirection is required it is not performed, processing just stops
         (user will see empty screen instead of a page). If no redirection is
         required (e.g. language is explicitly specified in URL) all works as
         usual. This option can be used for debug purposes, never use it in
         production environment.

   Default
         0


.. container:: table-row

   Property
         cookieLifetime

   Data type
         integer

   Description
         Lifetime (in seconds) of a cookie that stores selected language. If
         set to zero, TYPO3 session will be used as a storage. If set to
         something below zero, nothing will be stored and language will be
         detected each time user access the site.

   Default
         0


.. container:: table-row

   Property
         pathToDatabaseForGeoIPData

   Data type
         string

   Description
         Path to the GeoIP database file, which must be stored locally. One free
         GeoIP database file can be found on the `website of Maxmind`_. For more
         details look to the :ref:`NetGeoIP`.

   Default
         (empty)


.. _website of Maxmind: http://dev.maxmind.com/geoip/legacy/geolite/

.. ###### END~OF~TABLE ######

