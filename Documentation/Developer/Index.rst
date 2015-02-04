

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


Hooks
^^^^^

This extension supports hooks. There's only one hook at the moment,
but feel free to suggest more. You should read the section about hooks
in the TYPO3 Core API :ref:`t3api:hooks-basics` before you start.



preferredLanguageHook
"""""""""""""""""""""

This hook adds language checks. The extension supports checking for
supported languages in the browser's settings and by IP address. This
is where you can add more.

The following line in ext\_localconf.php would include a hook function
``test\_preferredLanguageHooks`` in the class ``Rlmp\\RlmpLanguageDetection\\LanguageDetection``.

It could be enabled by adding the string ``test`` to the testOrder TypoScript property.

::

   $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['rlmp_language_detection']['preferredLanguageHooks']['test'] = 'Rlmp\\RlmpLanguageDetection\\LanguageDetection->testPreferredLanguageHooks';

The function must support the following arguments and return values:

.. code-block:: php

	/*
	 * @param array $availableLanguagesArr Associative array containing available languages. Key is ISO 639-1 language code. Value is TYPO3 Website Language UID.
	 * @param LanguageDetection $parentObject Reference to the calling object.
	 *
	 * @return int/bool Website Language UID if successful, otherwise FALSE
	 */
	public function testPreferredLanguageHooks($availableLanguagesArr, LanguageDetection $parentObject) {
