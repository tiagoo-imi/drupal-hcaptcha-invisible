<?php


namespace Drupal\hcaptcha\HCaptcha;


class HCaptcha
{
  const SITE_VERIFY_URL = 'https://hcaptcha.com/siteverify';

  protected $attributes = array(
    'class' => 'h-captcha',
    'data-sitekey' => '',
    'data-theme' => '',
    'data-size' => '',
    'data-tabindex' => 0,
  );

  protected $siteKey = '';
  protected $secretKey = '';
  protected $errors = array();
  private $success = false;
  private $validated;
  private $requestMethod;

  public function __construct($site_key, $secret_key, $attributes = array(), RequestMethod $requestMethod = null) {
    $this->siteKey = $site_key;
    $this->secretKey = $secret_key;
    $this->requestMethod = $requestMethod;

    if (!empty($attributes) && is_array($attributes)) {
      foreach ($attributes as $name => $attribute) {
        if (isset($this->attributes[$name])) $this->attributes[$name] = $attribute;
      }
    }
  }

  /**
   * Build the hCaptcha captcha form.
   * @return mixed
   */
  public function getWidget($validation_function) {
    // Captcha requires TRUE to be returned in solution.
    $widget['solution'] = true;
    $widget['captcha_validate'] = $validation_function;
    $widget['form']['captcha_response'] = array(
      '#type' => 'hidden',
      '#value' => 'hCaptcha no captcha',
    );

    $size = isset($this->attributes['data-size']) ? $this->attributes['data-size'] : '';
    $mode = isset($this->attributes['data-mode']) ? $this->attributes['data-mode'] : 'container';

  $siteKeyFromConfig = !empty($this->attributes['data-sitekey'])
    ? $this->attributes['data-sitekey']
    : ''


  $is_invisible_on_button = ($size === 'invisible' && $mode === 'on_button');
  if ($is_invisible_on_button) {
    $widget['form']['hcaptcha_invisible_flag'] = [
      '#type' => 'hidden',
      '#value' => '1',
      '#attributes' => ['name' => 'hcaptcha_invisible_flag'],
    ];
    
    $widget['form']['#attached']['drupalSettings']['hcaptcha'] = [
      'size' => 'invisible',
      'mode' => 'on_button',
      'sitekey' => $siteKeyFromConfig,
    ];
  }

  // Como o validate não depende de sid/solution, pode ser cacheável.
  $widget['cacheable'] = true;

  if ($is_invisible_on_button) {
    // Invisible acoplado ao botão: NÃO renderizamos o <div class="h-captcha">.
    // O botão será marcado pelo JS e a API é carregada via 'loader' (dependency da nossa lib).
    return $widget;
  }

  // Caso contrário (normal/compact ou invisible container_execute):
  // Monta os atributos do DIV como antes.
  $widget['form']['hcaptcha_widget'] = [
    '#markup' => '<div' . $this->getAttributesString() . '></div>',
    // Garante que a API será carregada pelo loader padrão:
    '#attached' => ['library' => ['hcaptcha/loader']],
  ];

    return $widget;
  }

  public function validate($response_token, $remote_ip = '', $max_score = 0.8) {
    $query = array(
      'secret' => $this->secretKey,
      'response' => $response_token,
      'remoteip' => $remote_ip,
    );
    $this->validated = $this->requestMethod->submit(self::SITE_VERIFY_URL, array_filter($query));

    if (isset($this->validated->score)) {
      if ($this->validated->score <= $max_score) {
        $this->success = TRUE;
      }
      else {
        $this->errors = [t('Score for the response (@score) is above the acceptable max score (@max_score).', [
          '@score' => $this->validated->score,
          '@max_score' => $max_score,
        ])];
      }
    }
    elseif (isset($this->validated->success)) {
      if ($this->validated->success === TRUE) {
        // Verified!
        $this->success = TRUE;
      }
      else {
        $this->errors = $this->getResponseErrors();
      }
    }
  }

  public function isSuccess() {
    return $this->success;
  }

  public function getErrors() {
    return $this->errors;
  }

  public function getResponseErrors() {
    // Error code reference, https://hcaptcha.com/docs#server
    $errors = array();
    if (isset($this->validated->{'error-codes'}) && is_array($this->validated->{'error-codes'})) {
      $error_codes = $this->getErrorCodes();
      foreach ($this->validated->{'error-codes'} as $code) {
        if (!isset($error_codes[$code])) {
          $code = 'unknown-error';
        }
        $errors[] = $error_codes[$code];
      }
    }
    return $errors;
  }

  public function getErrorCodes() {
    $error_codes = array(
      'missing-input-secret' => t('Your secret key is missing.'),
      'invalid-input-secret' => t('Your secret key is invalid or malformed.'),
      'sitekey-secret-mismatch' => t('Your site key is invalid for your secret key.'),
      'missing-input-response' => t('The response parameter (verification token) is missing.'),
      'invalid-input-response' => t('The response parameter (verification token) is invalid or malformed.'),
      'bad-request' => t('The request is invalid or malformed.'),
      'bad-response' => t('Did not receive a 200 from the service.'),
      'connection-failed' => t('Could not connect to service.'),
      'unknown-error' => t('Not a success, but no error codes received.'),
    );
    return $error_codes;
  }

  public function getAttributesString() {
    $attributes = array_filter($this->attributes);
    foreach ($attributes as $attribute => &$data) {
      $data = implode(' ', (array) $data);
      $data = $attribute . '="' . htmlspecialchars($data, ENT_QUOTES, 'UTF-8') . '"';
    }
    return $attributes ? ' ' . implode(' ', $attributes) : '';
  }
}
