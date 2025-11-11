(function (drupalSettings, Drupal, once) {
  // --- Utils ---
  function camelToDash(str) {
    return str.replace(/([A-Z])/g, function ($1) { return "-" + $1.toLowerCase(); });
  }
  function toConfigFromDataset(el) {
    return Object.fromEntries(
      Object.entries(el.dataset).map(function (kv) { return [camelToDash(kv[0]), kv[1]]; })
    );
  }
  function findSubmitButton(form, selectorOverride) {
    if (selectorOverride) {
      const custom = form.querySelector(selectorOverride);
      if (custom) return custom;
    }
    return form.querySelector('#edit-actions-submit, button[type="submit"], input[type="submit"]');
  }

  // --- Global flags & onload ---
  let hcaptchaReady = false;
  window.drupalHcaptchaOnload = function () {
    hcaptchaReady = true;
    Drupal.behaviors.hcaptchaUnified.attach(document);
  };

  // --- Callback usado pelo modo invisível ---
  window.hcaptchaInvisibleOnSubmit = function () {
    try {
      const active = document.activeElement;
      const btn = (active && (active.matches('button, input'))) ? active
                 : document.querySelector('.h-captcha[data-size="invisible"][data-hcaptcha-widget-id]');
      const form = btn && btn.closest ? btn.closest('form') : null;
      if (form) form.submit();
    } catch (e) {}
  };

  // --- Carrega script da API uma única vez ---
  if (!document.getElementById('hcaptcha-src') && drupalSettings.hcaptcha) {
    var el = document.createElement('script');
    el.type = 'text/javascript';
    el.src = drupalSettings.hcaptcha.src; // deve conter onload=drupalHcaptchaOnload
    el.id = 'hcaptcha-src';
    el.async = true;
    el.defer = true;
    document.getElementsByTagName('head')[0].appendChild(el);
  }

  // --- Render helpers ---
  function renderContainerWidget(element) {
    if (element.dataset.hcaptchaWidgetId) return; // já renderizado
    const cfg = toConfigFromDataset(element);
    const wid = hcaptcha.render(element, cfg);
    element.dataset.hcaptchaWidgetId = wid;
    element.setAttribute('data-once', 'hcaptcha-rendered');
  }

  function renderInvisibleOnButton(context, cfg) {
    // cfg: { size, mode, sitekey, formId, buttonOverrides? }
    if (cfg.size !== 'invisible' || cfg.mode !== 'on_button' || !cfg.formId) return;

    // Localiza o formulário pelo hidden form_id
    const hidden = context.querySelector('input[name="form_id"][value="' + cfg.formId + '"]');
    if (!hidden) return;
    const form = hidden.closest('form');
    if (!form) return;

    // Opcional: evitar render de containers dentro deste form (caso o PHP ainda os gere)
    const containersInForm = form.querySelectorAll('.h-captcha:not(button):not(input)');
    containersInForm.forEach(function (c) {
      // Marcar como "já processado" para o once() não renderizar
      c.setAttribute('data-once', 'hcaptcha-rendered');
    });

    // Botão alvo
    const selectorOverride = (cfg.buttonOverrides || {})[cfg.formId];
    const btn = findSubmitButton(form, selectorOverride);
    if (!btn) return;

    // Se já tem widget, não repita
    if (btn.dataset.hcaptchaWidgetId) return;

    // Atributos mínimos para invisível
    btn.classList.add('h-captcha');
    if (cfg.sitekey) btn.setAttribute('data-sitekey', cfg.sitekey);
    btn.setAttribute('data-size', 'invisible');
    btn.setAttribute('data-callback', 'hcaptchaInvisibleOnSubmit');

    // Render explícito (o render de containers ignora botões)
    const wid = hcaptcha.render(btn, {
      sitekey: cfg.sitekey,
      size: 'invisible',
      callback: 'hcaptchaInvisibleOnSubmit'
    });
    btn.dataset.hcaptchaWidgetId = wid;
    btn.setAttribute('data-once', 'hcaptcha-rendered');
  }

  // --- Behavior unificado ---
  Drupal.behaviors.hcaptchaUnified = {
    attach: function (context) {
      if (!hcaptchaReady || !drupalSettings || !drupalSettings.hcaptcha) return;
      const cfg = drupalSettings.hcaptcha;

      // 1) Render containers "visíveis" (ou invisíveis via container) — nunca em button/input
      once('hcaptcha-rendered', '.h-captcha:not(button):not(input)', context)
        .forEach(renderContainerWidget);

      // 2) Render modo invisível acoplado ao botão (evita conflito)
      renderInvisibleOnButton(context, cfg);
    }
  };
})(drupalSettings, Drupal, once);
