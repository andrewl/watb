<?php
/**
 * Twit A Bike Bot
 * 
 *
 *
 * @author Andrew Larcombe
 */
//import the configuration
include(dirname(__FILE__) .'/twitabikebot.conf.php');


//$application_state contains the last known state of the bot
//last_run contains the timestamp of the last sent tweet and/or last run of the bot
$application_state = array();
if(file_exists($application_state_file)) {
  $application_state = unserialize(file_get_contents($application_state_file));
}
else {
  logit("Creating new application state file");
  //by default, set the last_run time to now
  $application_state['last_run'] = time();
}

//retrieve all of the mentions
logit("Retrieving mentions since " . $application_state['last_run'] . "");
$mentions = get_next_mentions(OAUTH_CONSUMER_KEY, OAUTH_CONSUMER_SECRET,
                      $access_token, $access_token_secret, FALSE);
                      
                                            
foreach(json_decode($mentions[2]) as $mention) {

  $created_time = strtotime($mention->created_at);
  //if we've processed this mention, forget it
  if( $created_time < $application_state['last_run']) {
    logit("Ignoring '".$mention->text."' from " . $mention->user->screen_name ." - too old " . $created_time );
    continue;
  }
  else {
    logit("Processing '".$mention->text."' from " . $mention->user->screen_name ." - is new " . $created_time );
  }
  
  //get the screen name of the user who sent the tweet
  $user = $mention->user->screen_name;
  
  //if it's got coordinates we can play with it
  if(isset($mention->coordinates->coordinates)) {
    $x = $mention->coordinates->coordinates[0];
    $y = $mention->coordinates->coordinates[1];    
    
    //if I'm asked for a space, send empty docking stations, else send
    //locations of bikes
    if(strtolower($text) == 'space') {
      $filter = 2;
    }
    else {
      $filter = 1;
    }
    
    //call the watb api to get the 3 nearest spaces or bikes
    $url = $watb_api_endpoint . "/scheme/all/stations?nearest={$x},{$y}&count=3&filter={$filter}";
    logit("Calling {$url}");
    $watb_response = json_decode(file_get_contents($url));
    
    if(count($watb_response)) {
      
      //create a map of the nearest three docking stations
      $map_url = "http://maps.google.com/maps/api/staticmap?center={$y},{$x}&size=320x240&maptype=roadmap&";
      
      $first = TRUE;
      foreach ($watb_response as $station) {
        
        //if this is the first (ie nearest) marker and if it's closer than 1km then
        //draw the user's location on the map. if it's further than 1km then the map
        //will be zoomed out too far.
        if($first && $station->extra->distance < 1000) {
          $map_url .= "markers=color:red|{$y},{$x}&";
        }
        
        $label = ($filter == 1) ? $station->bikes : $station->stands;
        if((int)$label > 9) {
          $label = "+";
        }
        $map_url .= "markers=color:blue|label:{$label}|{$station->latitude},{$station->longitude}&";
        $first = FALSE;
      }
      
      $map_url .= "&sensor=false";
      
      //shorten the map url using bitly
      $bitly_return = json_decode(file_get_contents($bitly_api_endpoint . '/v3/shorten?longUrl=' . urlencode($map_url) . "&login={$bitly_username}&apiKey={$bitly_apikey}"));
      if(isset($bitly_return->data->url)) {
        $shortened_map_url = $bitly_return->data->url;
      }
      else {
        $shortened_map_url = FALSE;
      }

      //prepare the tweet
      $distance = (int)$watb_response[0]->extra->distance;
      $tweet = "@{$user} {$watb_response[0]->name} ";
      if($filter == 2) {
        $tweet .= "Spaces: {$watb_response[0]->stands} ";        
      }
      else {
        $tweet .= "Bikes: {$watb_response[0]->bikes} ";
      }
      $tweet .= " Distance: {$distance}m. ";
      
      if($shortened_map_url !== FALSE) {
        if(strlen($tweet) + strlen($shortened_map_url) < 140) {
          $tweet .= $shortened_map_url;
        }
      }
      
      $updated = " Updated: {$watb_response[0]->extra->updated}";
      
      if(strlen($tweet) + strlen($updated) < 140) {
        $tweet .= $updated;
      }

    }
    
  }
  else {

    //this isn't a geocoded tweet - tell the user
    $tweet = "@{$user} Sorry, you need to send me a geocoded tweet!";

  }
  
  //post the tweet if we're configured to do so
  if($post_tweet) {
    post_tweet(OAUTH_CONSUMER_KEY, OAUTH_CONSUMER_SECRET, $tweet, $access_token, $access_token_secret);
  }
  else {
    logit("Not configured to send tweet '{$tweet}'");
  }
  
  $application_state['last_run'] = time();
  file_put_contents($application_state_file, serialize($application_state));
  
}

$application_state['last_run'] = time();
file_put_contents($application_state_file, serialize($application_state));


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

/**
 * Call twitter to get the mentions
 * @param string $consumer_key obtained when you registered your app
 * @param string $consumer_secret obtained when you registered your app
 * @param string $access_token obtained from get_request_token
 * @param string $access_token_secret obtained from get_request_token
 * @return json encoded response string or empty array on error
 */
function get_next_mentions($consumer_key, $consumer_secret, $access_token, $access_token_secret, $since_id) {

  $retarr = array();  // return value
  $response = array();

  $url = 'http://api.twitter.com/statuses/mentions.json';
  
  if($since_id) {
    $params['since_id'] = $since_id;
  }
  $params['count'] = 200;
  $params['page'] = 1;
  
  $params['oauth_version'] = '1.0';
  $params['oauth_nonce'] = mt_rand();
  $params['oauth_timestamp'] = time();
  $params['oauth_consumer_key'] = $consumer_key;
  $params['oauth_token'] = $access_token;

  // compute hmac-sha1 signature and add it to the params list
  $params['oauth_signature_method'] = 'HMAC-SHA1';
  $params['oauth_signature'] =
      oauth_compute_hmac_sig('GET', $url, $params,
                             $consumer_secret, $access_token_secret);

  // Pass OAuth credentials in a separate header or in the query string
  $query_parameter_string = oauth_http_build_query($params, true);
  $header = build_oauth_header($params, "Twitter API");
  $headers[] = $header;

  $request_url = $url;
  // print("tweet:INFO:request_url:$request_url\n");
  // print("tweet:INFO:post_body:$query_parameter_string\n");
  $headers[] = 'Content-Type: application/x-www-form-urlencoded';
  $response = do_get($request_url, $query_parameter_string, 80, $headers);

  // extract successful response
  if (! empty($response)) {
    list($info, $header, $body) = $response;
    if ($body) {
      // print("tweet:INFO:response:\n");
      // print(json_pretty_print($body));
    }
    $retarr = $response;
  }

  return $retarr;
}

function logit($msg,$preamble=true)
{
  //  date_default_timezone_set('America/Los_Angeles');
  $now = date("r", time());
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
 * Do an HTTP GET
 * @param string $url
 * @param int $port (optional)
 * @param array $headers an array of HTTP headers (optional)
 * @return array ($info, $header, $response) on success or empty array on error.
 */
function do_get($url, $query_params, $port=80, $headers=NULL)
{
  $retarr = array();  // Return value

  $curl_opts = array(CURLOPT_URL => $url . "?{$query_params}",
                     CURLOPT_PORT => $port,
                     CURLOPT_POST => false,
                     CURLOPT_SSL_VERIFYHOST => false,
                     CURLOPT_SSL_VERIFYPEER => false,
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



/**
 * Stolen from
 *
 */
 
/**
 * Build a query parameter string according to OAuth Spec.
 * @param array $params an array of query parameters
 * @return string all the query parameters properly sorted and encoded
 * according to the OAuth spec, or an empty string if params is empty.
 * @link http://oauth.net/core/1.0/#rfc.section.9.1.1
 */
function oauth_http_build_query($params, $excludeOauthParams=false)
{
  $query_string = '';
  if (! empty($params)) {

    // rfc3986 encode both keys and values
    $keys = rfc3986_encode(array_keys($params));
    $values = rfc3986_encode(array_values($params));
    $params = array_combine($keys, $values);

    // Parameters are sorted by name, using lexicographical byte value ordering.
    // http://oauth.net/core/1.0/#rfc.section.9.1.1
    uksort($params, 'strcmp');

    // Turn params array into an array of "key=value" strings
    $kvpairs = array();
    foreach ($params as $k => $v) {
      if ($excludeOauthParams && substr($k, 0, 5) == 'oauth') {
        continue;
      }
      if (is_array($v)) {
        // If two or more parameters share the same name,
        // they are sorted by their value. OAuth Spec: 9.1.1 (1)
        natsort($v);
        foreach ($v as $value_for_same_key) {
          array_push($kvpairs, ($k . '=' . $value_for_same_key));
        }
      } else {
        // For each parameter, the name is separated from the corresponding
        // value by an '=' character (ASCII code 61). OAuth Spec: 9.1.1 (2)
        array_push($kvpairs, ($k . '=' . $v));
      }
    }

    // Each name-value pair is separated by an '&' character, ASCII code 38.
    // OAuth Spec: 9.1.1 (2)
    $query_string = implode('&', $kvpairs);
  }

  return $query_string;
}

/**
 * Parse a query string into an array.
 * @param string $query_string an OAuth query parameter string
 * @return array an array of query parameters
 * @link http://oauth.net/core/1.0/#rfc.section.9.1.1
 */
function oauth_parse_str($query_string)
{
  $query_array = array();

  if (isset($query_string)) {

    // Separate single string into an array of "key=value" strings
    $kvpairs = explode('&', $query_string);

    // Separate each "key=value" string into an array[key] = value
    foreach ($kvpairs as $pair) {
      list($k, $v) = explode('=', $pair, 2);

      // Handle the case where multiple values map to the same key
      // by pulling those values into an array themselves
      if (isset($query_array[$k])) {
        // If the existing value is a scalar, turn it into an array
        if (is_scalar($query_array[$k])) {
          $query_array[$k] = array($query_array[$k]);
        }
        array_push($query_array[$k], $v);
      } else {
        $query_array[$k] = $v;
      }
    }
  }

  return $query_array;
}

/**
 * Build an OAuth header for API calls
 * @param array $params an array of query parameters
 * @return string encoded for insertion into HTTP header of API call
 */
function build_oauth_header($params, $realm='')
{
  $header = 'Authorization: OAuth realm="' . $realm . '"';
  foreach ($params as $k => $v) {
    if (substr($k, 0, 5) == 'oauth') {
      $header .= ',' . rfc3986_encode($k) . '="' . rfc3986_encode($v) . '"';
    }
  }
  return $header;
}

/**
 * Compute an OAuth PLAINTEXT signature
 * @param string $consumer_secret
 * @param string $token_secret
 */
function oauth_compute_plaintext_sig($consumer_secret, $token_secret)
{
  return ($consumer_secret . '&' . $token_secret);
}

/**
 * Compute an OAuth HMAC-SHA1 signature
 * @param string $http_method GET, POST, etc.
 * @param string $url
 * @param array $params an array of query parameters for the request
 * @param string $consumer_secret
 * @param string $token_secret
 * @return string a base64_encoded hmac-sha1 signature
 * @see http://oauth.net/core/1.0/#rfc.section.A.5.1
 */
function oauth_compute_hmac_sig($http_method, $url, $params, $consumer_secret, $token_secret)
{
  global $debug;

  $base_string = signature_base_string($http_method, $url, $params);
  $signature_key = rfc3986_encode($consumer_secret) . '&' . rfc3986_encode($token_secret);
  $sig = base64_encode(hash_hmac('sha1', $base_string, $signature_key, true));
  if ($debug) {
    logit("oauth_compute_hmac_sig:DBG:sig:$sig");
  }
  return $sig;
}

/**
 * Make the URL conform to the format scheme://host/path
 * @param string $url
 * @return string the url in the form of scheme://host/path
 */
function normalize_url($url)
{
  $parts = parse_url($url);

  $scheme = $parts['scheme'];
  $host = $parts['host'];
  $port = $parts['port'];
  $path = $parts['path'];

  if (! $port) {
    $port = ($scheme == 'https') ? '443' : '80';
  }
  if (($scheme == 'https' && $port != '443')
      || ($scheme == 'http' && $port != '80')) {
    $host = "$host:$port";
  }

  return "$scheme://$host$path";
}

/**
 * Returns the normalized signature base string of this request
 * @param string $http_method
 * @param string $url
 * @param array $params
 * The base string is defined as the method, the url and the
 * parameters (normalized), each urlencoded and the concated with &.
 * @see http://oauth.net/core/1.0/#rfc.section.A.5.1
 */
function signature_base_string($http_method, $url, $params)
{
  // Decompose and pull query params out of the url
  $query_str = parse_url($url, PHP_URL_QUERY);
  if ($query_str) {
    $parsed_query = oauth_parse_str($query_str);
    // merge params from the url with params array from caller
    $params = array_merge($params, $parsed_query);
  }

  // Remove oauth_signature from params array if present
  if (isset($params['oauth_signature'])) {
    unset($params['oauth_signature']);
  }

  // Create the signature base string. Yes, the $params are double encoded.
  $base_string = rfc3986_encode(strtoupper($http_method)) . '&' .
                 rfc3986_encode(normalize_url($url)) . '&' .
                 rfc3986_encode(oauth_http_build_query($params));

  logit("signature_base_string:INFO:normalized_base_string:$base_string");

  return $base_string;
}

/**
 * Encode input per RFC 3986
 * @param string|array $raw_input
 * @return string|array properly rfc3986 encoded raw_input
 * If an array is passed in, rfc3896 encode all elements of the array.
 * @link http://oauth.net/core/1.0/#encoding_parameters
 */
function rfc3986_encode($raw_input)
{
  if (is_array($raw_input)) {
    return array_map('rfc3986_encode', $raw_input);
  } else if (is_scalar($raw_input)) {
    return str_replace('%7E', '~', rawurlencode($raw_input));
  } else {
    return '';
  }
}

function rfc3986_decode($raw_input)
{
  return rawurldecode($raw_input);
} 
?>
