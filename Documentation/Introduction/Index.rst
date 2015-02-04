

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


What does it do?
^^^^^^^^^^^^^^^^

This small extension enables a language detection based on the
visitor's browser settings, IP address. More ways of language
detection can be added through hooks.

The current page will be reloaded and the appropriate language will be
set, in case:

- The visitor prefers a different language than the default language

- The visitor has not already actively selected a different language on
  the website

- The website provides the visitor's preferred language or the website
  provides a language similar to the visitor's.

Alternatively the multiple-tree concept for multi-lingual websites may
be used. In that case the visitor will be redirected to a certain page
which acts as an entry page for the preferred language.

