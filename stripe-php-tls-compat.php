<?php

/**
 * Returns the TLS version used for establishing outbound connections, using
 * howsmyssl.com's API.
 * Cf. https://www.howsmyssl.com/s/api.html
 *
 * @param int|null $curl_ssl_opt value to use for CURLOPT_SSLVERSION
 * @return string
 */
function get_tls_version($curl_ssl_opt = null)
{
  $c = curl_init();
  curl_setopt($c, CURLOPT_URL, "https://www.howsmyssl.com/a/check");
  curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
  if ($sslversion !== null) {
    curl_setopt($c, CURLOPT_SSLVERSION, $sslversion);
  }
  $rbody = curl_exec($c);
  if ($rbody === false) {
    $errno = curl_errno($c);
    $msg = curl_error($c);
    curl_close($c);
    return "Error! errno = " . $errno . ", msg = " . $msg;
  } else {
    $r = json_decode($rbody);
    curl_close($c);
    return $r->tls_version;
  }
}

/**
 * Returns an array containing the result of the compatibility check for
 * different versions of the Stripe PHP library.
 *
 * @param array $tls_tests array containing the results of the TLS tests
 * @param int $openssl_version value of the OPENSSL_VERSION_NUMBER constant
 * @return array
 */
function get_compat_array($tls_tests, $openssl_version)
{
  $compat = array(
    "<=3.6.0" => null,
    ">=3.7.0 && <= 3.18.0" => null,
    ">=3.19.0 && <= 3.23.0" => null,
    ">=4.0" => null,
  );

  // stripe-php <= 3.6.0 didn't set CURLOPT_SSLVERSION, so only the 'default' test is relevant
  $compat["<=3.6.0"] = ($tls_tests['default'] == 'TLS 1.2' ? "OK" : "KO");

  // 3.7.0 <= stripe-php <= 3.18.0 forced CURLOPT_SSLVERSION to CURL_SSLVERSION_TLSv1,
  // so only the 'TLSv1' test is relevant
  $compat[">=3.7.0 && <= 3.18.0"] = ($tls_tests['TLSv1'] == 'TLS 1.2' ? "OK" : "KO");

  // 3.19.0 <= stripe-php <= 3.23.0 uses OPENSSL_VERSION_NUMBER to force CURLOPT_SSLVERSION
  // to either CURL_SSLVERSION_TLSv1 or CURL_SSLVERSION_TLSv1_2. get_compat_3_19_0 runs the
  // same check and uses the correct test result to check compatibility.
  $compat[">=3.19.0 && <= 3.23.0"] = get_compat_3_19_0(
    $openssl_version,
    $tls_tests['TLSv1'],
    $tls_tests['TLSv1_2']
  );

  // Like stripe-php <= 3.6.0, stripe-php >= 4.0 does not set any value for CURLOPT_SSLVERSION,
  // however it provides a way for users to manually set it themselves.
  if ($tls_tests['default'] == 'TLS 1.2') {
    $compat[">=4.0"] = "OK";
  } else {
    if ($tls_tests['TLSv1'] == 'TLS 1.2') {
      $compat[">=4.0"] = "OK_v1";
    } else {
      if ($tls_tests['TLSv1_2'] == 'TLS 1.2') {
        $compat[">=4.0"] = "OK_v1_2";
      } else {
        $compat[">=4.0"] = "KO";
      }
    }
  }

  return $compat;
}

/**
 * Returns the result of the compatibility check for stripe-php versions
 * 3.19.0 through 3.23.0.
 *
 * @param int $openssl_version value of the OPENSSL_VERSION_NUMBER constant
 * @param string $tls_v1 result of the TLSv1 check
 * @param string $tls_v1_2 result of the TLSv1_2 check
 * @return string
 */
function get_compat_3_19_0($openssl_version, $tls_v1, $tls_v1_2)
{
  if ($openssl_version >= 0x1000100f) {
    return ($tls_v1_2 == "TLS 1.2" ? "OK": "KO");
  } else {
    return ($tls_v1 == "TLS 1.2" ? "OK": "KO");
  }
}

/**
 * Returns the HTML code for displaying the compatibility status with a given
 * version.
 *
 * @param string $result compatibility status
 * @return string
 */
function get_compatibility_html($result)
{
  switch ($result) {
    case "OK":
      return '<span class="label label-success">Compatible</span>';
    case "OK_v1":
      return '<span class="label label-warning">Compatible</span>
<p>Add these lines in your code, before sending any request to the API:
<pre>$curl = new \Stripe\HttpClient\CurlClient(array(
  CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1
));
\Stripe\ApiRequestor::setHttpClient($curl);</pre>';
    case "OK_v1_2":
      return '<span class="label label-warning">Compatible</span>
<p>Add these lines in your code, before sending any request to the API:
<pre>$curl = new \Stripe\HttpClient\CurlClient(array(
  CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2
));
\Stripe\ApiRequestor::setHttpClient($curl);</pre>';
    case "KO":
    default:
      return '<span class="label label-danger">Incompatible</span>';
  }
}

/**
 * Returns the HTML code for displaying the compatibility status with a given
 * version.
 *
 * @param string $result compatibility status
 * @return string
 */
function get_recommendation_html($result)
{
  switch ($result) {
    case "OK":
      return '<p>If you aren\'t already using it, we recommend that you update to the <a href="https://github.com/stripe/stripe-php/releases">latest available version</a> (4.0.0 or more recent) of Stripe\'s PHP library.</p>';
    case "OK_v1":
      return '<p>If you aren\'t already using it, we recommend that you update to the <a href="https://github.com/stripe/stripe-php/releases">latest available version</a> (4.0.0 or more recent) of Stripe\'s PHP library.</p>
<p>You will need to set <code>CURLOPT_SSLVERSION</code> to <code>CURL_SSLVERSION_TLSv1</code> as shown above.</p>';
    case "OK_v1_2":
      return '<p>If you aren\'t already using it, we recommend that you update to the <a href="https://github.com/stripe/stripe-php/releases">latest available version</a> (4.0.0 or more recent) of Stripe\'s PHP library.</p>
<p>You will need to set <code>CURLOPT_SSLVERSION</code> to <code>CURL_SSLVERSION_TLSv1_2</code> as shown above.</p>';
    case "OK_v1_2":
    default:
      return '<p>Your server is <strong>not</strong> capable of using TLS 1.2 at all. You need to upgrade the PHP, curl and OpenSSL software packages.</p>
<p>You can find some generic instructions here:</p>
<ul>
<li><a href="https://support.stripe.com/questions/how-do-i-upgrade-my-stripe-integration-from-tls-1-0-to-tls-1-2">How do I upgrade my Stripe integration from TLS 1.0 to TLS 1.2?</a></li>
<li><a href="https://support.stripe.com/questions/how-do-i-upgrade-my-openssl-to-support-tls-1-2">How do I upgrade my OpenSSL to support TLS 1.2?</a></li>
</ul>';
  }

  return $recommendation;
}

// Gather all relevant information
$results = array(
  'os' => PHP_OS,
  'uname' => php_uname(),
  'php_version' => phpversion(),
  'curl_version' => curl_version()['version'],
  'curl_ssl_version' => curl_version()['ssl_version'],
  'curl_ssl_version_number' => sprintf('0x%08x', curl_version()['ssl_version_number']),
  'openssl_version' => sprintf('0x%08x', OPENSSL_VERSION_NUMBER),
  'tls_tests' => array(
    'default' => get_tls_version(),
    'TLSv1' => get_tls_version(1),
    'TLSv1_2' => get_tls_version(6)
  )
);

// Compute compatibility array
$compat = get_compat_array($results['tls_tests'], OPENSSL_VERSION_NUMBER);

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>stripe-php TLS compatibility tool</title>

    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet">

    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->

    <style>
/* Space out content a bit */
body {
  padding-top: 20px;
  padding-bottom: 20px;
}

/* Everything but the jumbotron gets side spacing for mobile first views */
.header,
.marketing,
.footer {
  padding-right: 15px;
  padding-left: 15px;
}

/* Custom page header */
.header {
  padding-bottom: 20px;
  border-bottom: 1px solid #e5e5e5;
}
/* Make the masthead heading the same height as the navigation */
.header h3 {
  margin-top: 0;
  margin-bottom: 0;
  line-height: 40px;
}

/* Custom page footer */
.footer {
  padding-top: 19px;
  color: #777;
  border-top: 1px solid #e5e5e5;
}

/* Customize container */
@media (min-width: 768px) {
  .container {
    max-width: 730px;
  }
}
.container-narrow > hr {
  margin: 30px 0;
}

/* Main marketing message and sign up button */
.jumbotron {
  text-align: center;
  border-bottom: 1px solid #e5e5e5;
}
.jumbotron .btn {
  padding: 14px 24px;
  font-size: 21px;
}

/* Supporting marketing content */
.marketing {
  margin: 40px 0;
}
.marketing p + h4 {
  margin-top: 28px;
}

/* Responsive: Portrait tablets and up */
@media screen and (min-width: 768px) {
  /* Remove the padding we set earlier */
  .header,
  .marketing,
  .footer {
    padding-right: 0;
    padding-left: 0;
  }
  /* Space out the masthead */
  .header {
    margin-bottom: 30px;
  }
  /* Remove the bottom border on the jumbotron for visual effect */
  .jumbotron {
    border-bottom: 0;
  }
}
    </style>
  </head>
  <body>
    <div class="container">
      <div class="header clearfix">
        <nav>
          <ul class="nav nav-pills pull-right">
            <li role="presentation"><a href="https://github.com/stripe/stripe-php#stripe-php-bindings">stripe-php</a></li>
            <li role="presentation"><a href="https://support.stripe.com/email">Stripe support</a></li>
          </ul>
        </nav>
        <h3 class="text-muted">stripe-php TLS compatibility tool</h3>
        <h6 class="text-muted">v1.0.0</h6>
      </div>

      <div class="row marketing">

        <h3>System information</h3>

        <table class="table table-hover">
          <thead>
            <tr>
              <th>Property</th>
              <th>Value</th>
            </tr>
          </thead>
          <tr>
            <td>OS</td>
            <td><?php echo $results['os']; ?></td>
          </tr>
          <tr>
            <td>uname</td>
            <td><?php echo $results['uname']; ?></td>
          </tr>
          <tr>
            <td>PHP version</td>
            <td><?php echo $results['php_version']; ?></td>
          </tr>
          <tr>
            <td>curl version</td>
            <td><?php echo $results['curl_version']; ?></td>
          </tr>
          <tr>
            <td>curl/SSL version</td>
            <td><?php echo $results['curl_ssl_version']; ?></td>
          </tr>
          <tr>
            <td>curl/SSL version number</td>
            <td><?php echo $results['curl_ssl_version_number']; ?></td>
          </tr>
          <tr>
            <td>OpenSSL version number</td>
            <td><?php echo $results['openssl_version']; ?></td>
          </tr>
          <tr>
            <td>TLS test (default)</td>
            <td><?php echo $results['tls_tests']['default']; ?></td>
          </tr>
          <tr>
            <td>TLS test (TLS_v1)</td>
            <td><?php echo $results['tls_tests']['TLSv1']; ?></td>
          </tr>
          <tr>
            <td>TLS test (TLS_v1_2)</td>
            <td><?php echo $results['tls_tests']['TLSv1_2']; ?></td>
          </tr>
        </table>

        <h3>Compatibility results</h3>

        <table class="table table-hover">
          <thead>
            <tr>
              <th>Stripe PHP library version</th>
              <th>Compatibility</th>
            </tr>
          </thead>
          <tr>
            <td>1.5.0 through 3.6.0</td>
            <td><?php echo get_compatibility_html($compat['<=3.6.0']); ?></td>
          </tr>
          <tr>
            <td>3.7.0 through 3.18.0</td>
            <td><?php echo get_compatibility_html($compat['>=3.7.0 && <= 3.18.0']); ?></td>
          </tr>
          <tr>
            <td>3.19.0 through 3.23.0</td>
            <td><?php echo get_compatibility_html($compat['>=3.19.0 && <= 3.23.0']); ?></td>
          </tr>
          <tr>
            <td>4.0.0 or more recent</td>
            <td><?php echo get_compatibility_html($compat['>=4.0']); ?></td>
          </tr>
        </table>

        <h3>Recommendation</h3>
        <span><?php echo get_recommendation_html($compat['>=4.0']); ?></span>
      </div>

      <footer class="footer">
        <p>&copy; 2016 Stripe</p>
      </footer>

    </div><!-- /.container -->

  </body>
</html>
