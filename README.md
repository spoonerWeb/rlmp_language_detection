rlmp_language_detection
=======================

Changes for version 7.0.0
-------------------------

TYPO3 CMS 7 compatibility

**ATTENTION:** The plugin is not automatically registered to your PAGE object anymore, do that manually where needed.
See manual!

* Removed old xclassing
* Created a TypoScript static template (!!! breaking)
* Moved class to Classes folder and namespaced it
* Migrated manual to ReST

Changes for version 3.1.0
-------------------------

* Added support for PEAR package Net_GeoIP
* Removed support for deprecated extension ml_geoip

Changes for version 3.0.0
-------------------------

TYPO3 extension rlmp_language_detection ready for 6.2

* Removed all require_once() calls
* Replaced deprecated function calls to the new namespaced ones
* Reformatted code according to the TYPO3 CGL