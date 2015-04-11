

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


Installation
============

Installing this extension is fairly easy. Just download it via the
Extension Manager (EM) and click on the install button.

.. important::
   Don't forget to include the static TypoScript template in your template.

   To use the plugin you have to add it to your PAGE object in TypoScript

   .. code-block:: typoscript

      page = PAGE
      ...
      page.987 =< plugin.tx_rlmplanguagedetection_pi1
