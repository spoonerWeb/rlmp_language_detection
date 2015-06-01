

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


FAQ
^^^

How can I make sure that the language detection works fine?
"""""""""""""""""""""""""""""""""""""""""""""""""""""""""""

Just change your browser's language settings: You can choose different
languages as well as a priority for each of them. If you hit a page of
your website without any L parameter in the URL, the extension should
apply the correct language.


Where do I find ISO 639-1 codes?
""""""""""""""""""""""""""""""""

Wikipedia offers a list of the ISO 639-1 codes. The actual codes used
depends on the version of static\_info\_tables you have installed.
Using the latest version is of course recommended.

.. _`NetGeoIP`

How can I use the PEAR package Net_GeoIP?
"""""""""""""""""""""""""""""""""""""""""

You have to install the PEAR package `Net_GeoIP`_.
After that you only need a GeoIP database to get the origin country by
the visitor's IP address.
A database (with CC license) can be found on the website of `Maxmind`_.
After that you have to define the path to this database file in
TypoScript:

.. code-block:: typoscript

   plugin.tx_rlmplanguagedetection_pi1 {
      pathToDatabaseForGeoIPData = EXT:another_extension/path/to/file
   }



.. _Net_GeoIP: https://pear.php.net/package/Net_GeoIP
.. _Maxmind: http://dev.maxmind.com/geoip/legacy/geolite/