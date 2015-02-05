

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


Using the Multiple Page Trees Concept
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

So you need different structures for each language? Then you will
likely choose the multiple page tree method.

In order to enable it, you'll have to add some TypoScript to the setup
field of your root template. Just have a look at the example:

.. code-block:: typoscript

   plugin.tx_rlmplanguagedetection_pi1 {
      useOneTreeMethod = 0
      multipleTreesRootPages  {
         de = 216
         en = 120
         es = 482
      }
   }

First you disable the One-Tree-Method by setting useOneTreeMethod
**=** 0. Then you define the unique ids of those pages being the root
page for each language.

In our example visitors who prefer spanish will automatically guided
to the page with the id 482 if they enter the website.
