/* Domenig & Partner Notariat AG – Schuldbriefbestellung
   Progressive Verbesserung: Alles funktioniert auch ohne JavaScript.
   Kein Framework, keine Fremdquellen, keine Speicherung im Browser. */
(function () {
  'use strict';

  // 1. Fokus auf Fehlerübersicht bzw. Überschrift von Bestätigungs-/Meldeseiten (Screenreader, Tastatur).
  var focusTarget = document.getElementById('error-summary') || document.querySelector('[data-autofocus]');
  if (focusTarget) {
    focusTarget.focus();
  }

  var form = document.querySelector('[data-form]');
  if (!form) {
    return;
  }

  // 2. Doppeltes Absenden verhindern (sonst kämen zwei Mails ans Notariat).
  var submit = form.querySelector('[data-submit]');
  if (submit) {
    var label = submit.textContent;
    form.addEventListener('submit', function () {
      submit.disabled = true;
      submit.textContent = submit.getAttribute('data-busy-text') || label;
    });
    // Zurück-Taste (bfcache): Button wieder freigeben.
    window.addEventListener('pageshow', function () {
      submit.disabled = false;
      submit.textContent = label;
    });
  }
})();
