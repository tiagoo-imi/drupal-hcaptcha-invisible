<?php

namespace Drupal\hcaptcha\HCaptcha;

/**
 * Summary of HCaptcha.
 */
class HCaptcha {
  const SITE_VERIFY_URL = 'https://hcaptcha.com/siteverify';
  /**
   * Summary of attributes.
   *
   * @var mixed
   */
  protected $attributes = [
    'class' => 'h-captcha',
    'data-sitekey' => '',
    'data-theme' => '',
    'data-size' => '',
    'data-tabindex' => 0,
  ];
  /**
   * Summary of siteKey.
   *
   * @var mixed
   */
  protected $siteKey = '';
  /**
   * Summary of secretKey.
   *
   * @var mixed
   */
  protected $secretKey = '';
  /**
   * Summary of errors.
   *
   * @var mixed
   */
  protected $errors = [];
  /**
   * Summary of success.
   *
   * @var mixed
   */
  private $success = FALSE;
  /**
   * Summary of validated.
   *
   * @var mixed
   */
  private $validated;
  /**
   * Summary of requestMethod.
   *
   * @var mixed
   */
  private $requestMethod;

  /**
   * Summary of __construct.
   *
   * @param mixed $site_key
   *   The site_key.
   * @param mixed $secret_key
   *   The secret_key.
   * @param mixed $attributes
   *   The attributes.
   * @param RequestMethod|null $requestMethod
   *   The requestMethod.
   */
  public function __construct($site_key, $secret_key, $attributes = [], RequestMethod $requestMethod = NULL) {
    $this->siteKey = $site_key;
    $this->secretKey = $secret_key;
    $this->requestMethod = $requestMethod;

    if (!empty($attributes) && is_array($attributes)) {
      foreach ($attributes as $name => $attribute) {
        if (isset($this->attributes[$name])) {
          $this->attributes[$name] = $attribute;
        }
      }
    }
  }

  /**
   * Build the hCaptcha captcha form.
   *
   * @return mixed
   *   Returns form widget.
   */
  public function getWidget($validation_function) {
    // Captcha requires TRUE to be returned in solution.
    $widget['solution'] = TRUE;
    $widget['captcha_validate'] = $validation_function;
    $widget['form']['captcha_response'] = [
      '#type' => 'hidden',
      '#value' => 'hCaptcha no captcha',
    ];

    // As the validate callback does not depend on sid or solution, this
    // captcha type can be displayed on cached pages.
    $widget['cacheable'] = TRUE;

    $widget['form']['hcaptcha_widget'] = [
      '#markup' => '<div' . $this->getAttributesString() . '></div>',
    ];
    return $widget;
  }

  /**
   * Validate.
   *
   * @param string $response_token
   *   Response Token.
   * @param string $remote_ip
   *   Remote IP.
   * @param string $max_score
   *   Max Score.
   */
  public function validate($response_token, $remote_ip = '', $max_score = 0.8) {
    $query = [
      'secret' => $this->secretKey,
      'response' => $response_token,
      'remoteip' => $remote_ip,
    ];
    $this->validated = $this->requestMethod->submit(self::SITE_VERIFY_URL, array_filter($query));

    if (isset($this->validated->score)) {
      if ($this->validated->score <= $max_score) {
        $this->success = TRUE;
      }
      else {
        $this->errors = [$this->t('Score for the response (@score) is above the acceptable max score (@max_score).', [
          '@score' => $this->validated->score,
          '@max_score' => $max_score,
        ]),
        ];
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

  /**
   * Is Success.
   */
  public function isSuccess() {
    return $this->success;
  }

  /**
   * Get Errors.
   */
  public function getErrors() {
    return $this->errors;
  }

  /**
   * Get Response Errors.
   */
  public function getResponseErrors() {
    // Error code reference, https://hcaptcha.com/docs#server
    $errors = [];
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

  /**
   * Get Error Codes.
   */
  public function getErrorCodes() {
    $error_codes = [
      'missing-input-secret' => $this->t('Your secret key is missing.'),
      'invalid-input-secret' => $this->t('Your secret key is invalid or malformed.'),
      'sitekey-secret-mismatch' => $this->t('Your site key is invalid for your secret key.'),
      'missing-input-response' => $this->t('The response parameter (verification token) is missing.'),
      'invalid-input-response' => $this->t('The response parameter (verification token) is invalid or malformed.'),
      'bad-request' => $this->t('The request is invalid or malformed.'),
      'bad-response' => $this->t('Did not receive a 200 from the service.'),
      'connection-failed' => $this->t('Could not connect to service.'),
      'unknown-error' => $this->t('Not a success, but no error codes received.'),
    ];
    return $error_codes;
  }

  /**
   * Get Attributes String.
   */
  public function getAttributesString() {
    $attributes = array_filter($this->attributes);
    foreach ($attributes as $attribute => &$data) {
      $data = implode(' ', (array) $data);
      $data = $attribute . '="' . htmlspecialchars($data, ENT_QUOTES, 'UTF-8') . '"';
    }
    return $attributes ? ' ' . implode(' ', $attributes) : '';
  }

}
