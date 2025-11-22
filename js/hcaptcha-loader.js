(function (drupalSettings, Drupal, once) {
  'use strict';

  // Taken from: https://stackoverflow.com/a/47932848
  function camelToDash(str) {
    return str.replace(/([A-Z])/g, function ($1) {
      return '-' + $1.toLowerCase();
    });
  }

  let hcaptchaReady = false;

  window.drupalHcaptchaOnload = function () {
    hcaptchaReady = true;

    Drupal.behaviors.hcaptcha.attach(document, drupalSettings);
    if (Drupal.behaviors.hcaptchaInvisible) {
      Drupal.behaviors.hcaptchaInvisible.attach(document, drupalSettings);
    }
  };

  // Callback used by invisible hCaptcha to submit the form
  window.hcaptchaInvisibleOnSubmit = function (token) {
    try {
      // For each invisible widget on the page
      const widgets = document.querySelectorAll('.h-captcha[data-size="invisible"]');
      widgets.forEach(function (widget) {
        const form = widget.closest('form');
        if (!form) {
          return;
        }

        // Ensure the token is in the field (usually hCaptcha already fills it)
        const tokenField = form.querySelector('[name="h-captcha-response"]');
        if (tokenField && !tokenField.value && token) {
          tokenField.value = token;
        }

        // Retrieve the original button's name/value saved in the dataset
        const submitName = form.dataset.hcaptchaSubmitName;
        const submitValue = form.dataset.hcaptchaSubmitValue;

        if (submitName) {
          // Create (or reuse) a hidden input that simulates the clicked button
          let hidden = form.querySelector('input[type="hidden"][name="' + submitName + '"]');
          if (!hidden) {
            hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = submitName;
            form.appendChild(hidden);
          }
          hidden.value = submitValue || '';
        }

        form.submit();
      });
    } catch (e) {
      console.error('hcaptchaInvisibleOnSubmit error', e);
    }
  };

  // Load the hCaptcha script once
  if (!document.getElementById('hcaptcha-src') && drupalSettings.hcaptcha) {
    var el = document.createElement('script');
    el.type = 'text/javascript';
    el.src = drupalSettings.hcaptcha.src;
    el.id = 'hcaptcha-src';
    el.async = true;
    el.defer = true;
    document.getElementsByTagName('head')[0].appendChild(el);
  }

  const renderWidget = function (element) {
    const config = Object.fromEntries(
      Object.entries(element.dataset).map(function (key_val) {
        return [camelToDash(key_val[0]), key_val[1]];
      })
    );
    const widgetId = hcaptcha.render(element, config);

    element.dataset.hcaptchaWidgetId = widgetId;
  };

  Drupal.behaviors.hcaptcha = {
    attach: function (context) {
      if (!hcaptchaReady) {
        return;
      }
      once('hcaptcha-rendered', '.h-captcha', context).forEach(renderWidget);
    }
  };

  // New behavior: handles the submit flow for invisible widgets
  Drupal.behaviors.hcaptchaInvisible = {
    attach: function (context) {
      if (!hcaptchaReady) {
        return;
      }

      once('hcaptcha-invisible-submit', 'form', context).forEach(function (form) {
        // Only affect forms that have an invisible widget
        const widget = form.querySelector('.h-captcha[data-size="invisible"]');
        if (!widget) {
          return;
        }

        form.addEventListener('submit', function (e) {
          // If there is already a token, do not intercept: let the normal submit proceed
          const tokenField = form.querySelector('[name="h-captcha-response"]');
          if (tokenField && tokenField.value) {
            return;
          }

          const submitter = e.submitter || document.activeElement;
          if (submitter && submitter.name) {
            form.dataset.hcaptchaSubmitName = submitter.name;
            form.dataset.hcaptchaSubmitValue = submitter.value || '';
          }

          e.preventDefault();
          e.stopPropagation();

          // Execute the invisible hCaptcha
          if (typeof hcaptcha !== 'undefined') {
            const widgetId = widget.dataset.hcaptchaWidgetId;
            if (widgetId) {
              hcaptcha.execute(widgetId);
            } else {
              hcaptcha.execute();
            }
          }
        });
      });
    }
  };

})(drupalSettings, Drupal, once);
