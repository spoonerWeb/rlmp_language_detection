

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


Blacklisting Pages
^^^^^^^^^^^^^^^^^^

You can set a list of pages where you want no redirects via TypoScript:

   .. code-block:: typoscript

	plugin.tx_rlmplanguagedetection_pi1 {
		# no redirects on page id 10, 11 and 12
		noRedirectPidList = 10,11,12
	}
