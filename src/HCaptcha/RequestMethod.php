<?php

namespace Drupal\hcaptcha\HCaptcha;

/**
 * Summary of RequestMethod.
 */
interface RequestMethod {

  /**
   * Submit the request with the specified parameters.
   *
   * @param string $url
   *   Url.
   * @param array $params
   *   Request parameters.
   *
   * @return mixed
   *   \stdClass Body of the hCaptcha response
   */
  public function submit(string $url, array $params);

}
