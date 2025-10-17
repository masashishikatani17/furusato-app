(function () {
  'use strict';

  document.addEventListener(
    'submit',
    function (event) {
      var form = event.target;
      if (!(form instanceof HTMLFormElement)) {
        return;
      }

      var submitter = event.submitter;
      if (!submitter) {
        return;
      }

      var redirectTo = submitter.getAttribute('data-redirect-to');
      if (redirectTo !== null) {
        var redirectInput = form.querySelector('input[name="redirect_to"]');
        if (!redirectInput) {
          redirectInput = document.createElement('input');
          redirectInput.type = 'hidden';
          redirectInput.name = 'redirect_to';
          form.appendChild(redirectInput);
        }

        redirectInput.value = redirectTo;
      }

      var shouldDisable =
        submitter.hasAttribute('data-disable-on-submit') ||
        (submitter.getAttribute('name') === 'recalc_all' && submitter.getAttribute('value') === '1');

      if (shouldDisable) {
        var buttons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
        buttons.forEach(function (button) {
          button.disabled = true;
        });
      }
    },
    true
  );
})();