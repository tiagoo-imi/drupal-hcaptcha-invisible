(function (Drupal, drupalSettings) {
  function findSubmitButton(form, selectorOverride) {
    if (selectorOverride) {
      const custom = form.querySelector(selectorOverride);
      if (custom) return custom;
    }
    return form.querySelector('#edit-actions-submit, button[type="submit"], input[type="submit"]');
  }

  // Callback exigido pelo Invisible quando acoplado ao botão.
  window.hcaptchaInvisibleOnSubmit = function () {
    try {
      const btn = document.activeElement || document.querySelector('.h-captcha[data-size="invisible"]');
      const form = btn && btn.closest ? btn.closest('form') : null;
      if (form) form.submit();
    } catch (e) {}
  };

  Drupal.behaviors.hcaptchaInvisible = {
    attach: function (context) {
      const cfg = (drupalSettings && drupalSettings.hcaptcha) || {};
      if (cfg.size !== 'invisible' || cfg.mode !== 'on_button') return;

      const formId = cfg.formId;
      if (!formId) return;

      // Localiza o form pelo hidden form_id.
      const hidden = context.querySelector('input[name="form_id"][value="' + formId + '"]');
      if (!hidden) return;
      const form = hidden.closest('form');
      if (!form) return;

      // Botão alvo (pode ser override por form_id).
      const selectorOverride = (cfg.buttonOverrides || {})[formId];
      const btn = findSubmitButton(form, selectorOverride);
      if (!btn) return;

      // Marca o botão conforme doc do Invisible.
      btn.classList.add('h-captcha');
      if (cfg.sitekey) btn.setAttribute('data-sitekey', cfg.sitekey);
      btn.setAttribute('data-size', 'invisible');
      btn.setAttribute('data-callback', 'hcaptchaInvisibleOnSubmit');
      // A library "loader" já carregou a API; o botão é auto-inicializado.
    }
  };
})(Drupal, drupalSettings);
