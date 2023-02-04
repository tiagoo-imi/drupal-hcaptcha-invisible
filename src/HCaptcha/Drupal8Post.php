<?php

namespace Drupal\hcaptcha\HCaptcha;

/**
 * Sends POST requests to the hCaptcha service.
 */
class Drupal8Post implements RequestMethod {

  /**
   * Submit the POST request with the specified parameters.
   *
   * @param string $url
   *   Url.
   * @param array $params
   *   Request parameters.
   *
   * @return object
   *   Body of the hCaptcha response.
   */
  public function submit(string $url, array $params) {
    $options = [
      'headers' => [
        'Content-type' => 'application/x-www-form-urlencoded',
      ],
      'body' => http_build_query($params, '', '&'),
      // Stop firing exception on response status code >= 300.
      // See http://docs.guzzlephp.org/en/stable/handlers-and-middleware.html
      'http_errors' => FALSE,
    ];
    $response = \Drupal::httpClient()->post($url, $options);

    if ($response->getStatusCode() == 200) {
      // The service request was successful.
      $result = (string) $response->getBody();
    }
    elseif ($response->getStatusCode() < 0) {
      // Negative status codes typically point to network or socket issues.
      $result = '{"success": false, "error-codes": ["connection-failed"]}';
    }
    else {
      // Positive none 200 status code typically means the request has failed.
      $result = '{"success": false, "error-codes": ["bad-response"]}';
    }

    return json_decode($result);
  }

}
