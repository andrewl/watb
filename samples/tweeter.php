<?php
require_once(dirname(__FILE__) . '/tweeter.conf.php');
require_once(dirname(__FILE__) . '/oauth_helper.php');

$url = $watb_api_endpoint . "/scheme/{$scheme}/stations/{$stations}";

//load the station info
$stations = json_decode(file_get_contents($url));

if(!$stations) {
  print "Failed to load stations from {$url}";
  exit;
}

//load the previous state
if(file_exists($previous_state_file)) {
  $previous_state = unserialize(file_get_contents($previous_state_file));
}

$current_state = array();
foreach($stations as $station) {
  $current_state[$station->id] = $station;
}

//work out what message to tweet, if any
$tweet = NULL;

foreach($current_state as $id => $station) {
  
  $alerts = array();
  if((!$previous_state[$id] || $previous_state[$id]->bikes) && !$station->bikes) {
    $alerts = "0 bikes";
  }
  elseif(!$previous_state[$id]->bikes && $station->bikes) {
    $alerts = "{$station->bikes} bikes";
  }

  if((!$previous_state[$id] || $previous_state[$id]->stands) && !$station->stands) {
    $alerts = "0 stands";
  }
  elseif(!$previous_state[$id]->stands && $station->stands) {
    $alerts = "{$station->bikes} stands";
  }

  if(count($alerts)) {
    $tweet = "{$station->name} has " . join('and ', $alerts);
    $ret = post_tweet(OAUTH_CONSUMER_KEY, OAUTH_CONSUMER_SECRET, $tweet, $access_token, $access_token_secret);
  }
  
}

file_put_contents($previous_state_file,serialize($current_state));

exit(0);






/**
 * Everything from here down is stolen and hacked from http://github.com/joechung/oauth_twitter
 */

/**
 * Call twitter to post a tweet
 * @param string $consumer_key obtained when you registered your app
 * @param string $consumer_secret obtained when you registered your app
 * @param string $status_message
 * @param string $access_token obtained from get_request_token
 * @param string $access_token_secret obtained from get_request_token
 * @return response string or empty array on error
 */
function post_tweet($consumer_key, $consumer_secret, $status_message, $access_token, $access_token_secret) {

  $retarr = array();  // return value
  $response = array();

  $url = 'http://api.twitter.com/1/statuses/update.json';
  $params['status'] = $status_message;
  $params['oauth_version'] = '1.0';
  $params['oauth_nonce'] = mt_rand();
  $params['oauth_timestamp'] = time();
  $params['oauth_consumer_key'] = $consumer_key;
  $params['oauth_token'] = $access_token;

  // compute hmac-sha1 signature and add it to the params list
  $params['oauth_signature_method'] = 'HMAC-SHA1';
  $params['oauth_signature'] =
      oauth_compute_hmac_sig('POST', $url, $params,
                             $consumer_secret, $access_token_secret);

  // Pass OAuth credentials in a separate header or in the query string
  $query_parameter_string = oauth_http_build_query($params, true);
  $header = build_oauth_header($params, "Twitter API");
  $headers[] = $header;

  $request_url = $url;
  print("tweet:INFO:request_url:$request_url\n");
  print("tweet:INFO:post_body:$query_parameter_string\n");
  $headers[] = 'Content-Type: application/x-www-form-urlencoded';
  $response = do_post($request_url, $query_parameter_string, 80, $headers);

  // extract successful response
  if (! empty($response)) {
    list($info, $header, $body) = $response;
    if ($body) {
      print("tweet:INFO:response:\n");
      print(json_pretty_print($body));
    }
    $retarr = $response;
  }

  return $retarr;
}

function logit($msg,$preamble=true)
{
  //  date_default_timezone_set('America/Los_Angeles');
  $now = date(DateTime::ISO8601, time());
  error_log(($preamble ? "+++${now}:" : '') . $msg);
}


/**
 * Do an HTTP POST
 * @param string $url
 * @param int $port (optional)
 * @param array $headers an array of HTTP headers (optional)
 * @return array ($info, $header, $response) on success or empty array on error.
 */
function do_post($url, $postbody, $port=80, $headers=NULL)
{
  $retarr = array();  // Return value

  $curl_opts = array(CURLOPT_URL => $url,
                     CURLOPT_PORT => $port,
                     CURLOPT_POST => true,
                     CURLOPT_SSL_VERIFYHOST => false,
                     CURLOPT_SSL_VERIFYPEER => false,
                     CURLOPT_POSTFIELDS => $postbody,
                     CURLOPT_RETURNTRANSFER => true);

  if ($headers) { $curl_opts[CURLOPT_HTTPHEADER] = $headers; }

  $response = do_curl($curl_opts);

  if (! empty($response)) { $retarr = $response; }

  return $retarr;
}

/**
 * Make a curl call with given options.
 * @param array $curl_opts an array of options to curl
 * @return array ($info, $header, $response) on success or empty array on error.
 */
function do_curl($curl_opts)
{
  global $debug;

  $retarr = array();  // Return value

  if (! $curl_opts) {
    if ($debug) { logit("do_curl:ERR:curl_opts is empty"); }
    return $retarr;
  }

  // Open curl session
  $ch = curl_init();
  if (! $ch) {
    if ($debug) { logit("do_curl:ERR:curl_init failed"); }
    return $retarr;
  }

  // Set curl options that were passed in
  curl_setopt_array($ch, $curl_opts);

  // Ensure that we receive full header
  curl_setopt($ch, CURLOPT_HEADER, true);

  if ($debug) {
    curl_setopt($ch, CURLINFO_HEADER_OUT, true);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
  }

  // Send the request and get the response
  ob_start();
  $response = curl_exec($ch);
  $curl_spew = ob_get_contents();
  ob_end_clean();
  if ($debug && $curl_spew) {
    logit("do_curl:INFO:curl_spew begin");
    logit($curl_spew, false);
    logit("do_curl:INFO:curl_spew end");
  }

  // Check for errors
  if (curl_errno($ch)) {
    $errno = curl_errno($ch);
    $errmsg = curl_error($ch);
    if ($debug) { logit("do_curl:ERR:$errno:$errmsg"); }
    curl_close($ch);
    unset($ch);
    return $retarr;
  }

  if ($debug) {
    logit("do_curl:DBG:header sent begin");
    $header_sent = curl_getinfo($ch, CURLINFO_HEADER_OUT);
    logit($header_sent, false);
    logit("do_curl:DBG:header sent end");
  }

  // Get information about the transfer
  $info = curl_getinfo($ch);

  // Parse out header and body
  $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  $header = substr($response, 0, $header_size);
  $body = substr($response, $header_size );

  // Close curl session
  curl_close($ch);
  unset($ch);

  if ($debug) {
    logit("do_curl:DBG:response received begin");
    if (!empty($response)) { logit($response, false); }
    logit("do_curl:DBG:response received end");
  }

  // Set return value
  array_push($retarr, $info, $header, $body);

  return $retarr;
}

/**
 * Pretty print some JSON
 * @param string $json The packed JSON as a string
 * @param bool $html_output true if the output should be escaped
 * (for use in HTML)
 * @link http://us2.php.net/manual/en/function.json-encode.php#80339
 */
function json_pretty_print($json, $html_output=false)
{
  $spacer = '  ';
  $level = 1;
  $indent = 0; // current indentation level
  $pretty_json = '';
  $in_string = false;

  $len = strlen($json);

  for ($c = 0; $c < $len; $c++) {
    $char = $json[$c];
    switch ($char) {
    case '{':
    case '[':
      if (!$in_string) {
        $indent += $level;
        $pretty_json .= $char . "\n" . str_repeat($spacer, $indent);
      } else {
        $pretty_json .= $char;
      }
      break;
    case '}':
    case ']':
      if (!$in_string) {
        $indent -= $level;
        $pretty_json .= "\n" . str_repeat($spacer, $indent) . $char;
      } else {
        $pretty_json .= $char;
      }
      break;
    case ',':
      if (!$in_string) {
        $pretty_json .= ",\n" . str_repeat($spacer, $indent);
      } else {
        $pretty_json .= $char;
      }
      break;
    case ':':
      if (!$in_string) {
        $pretty_json .= ": ";
      } else {
        $pretty_json .= $char;
      }
      break;
    case '"':
      if ($c > 0 && $json[$c-1] != '\\') {
        $in_string = !$in_string;
      }
    default:
      $pretty_json .= $char;
      break;
    }
  }

  return ($html_output) ?
    '<pre>' . htmlentities($pretty_json) . '</pre>' :
    $pretty_json . "\n";
}

