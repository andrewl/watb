<?php
require_once(dirname(__FILE__) . '/../lib/bikehirefeeder.class.php');
require_once(dirname(__FILE__) . '/../lib/station.class.php');

if(!isset($_SERVER['PATH_INFO'])) {
  print json_encode("PATH_INFO not set");
}

$args = get_args();

//the router
$path = $_SERVER['PATH_INFO'];
$fn = NULL;
if(preg_match('!scheme/.+?/stations/.+?!', $path)) {
  $fn = 'scheme_station';
}
else if(preg_match('!scheme/.+?/stations$!', $path)) {
  $fn = 'scheme_stations';
}
else if(preg_match('!scheme/.+?!', $path)) {
  $fn = 'scheme';
}
else if(preg_match('!scheme$!', $path)) {
  $fn = 'schemes';
}
else {
  $data['error'] = "Failed to understand endpoint";
}

if($fn) {
  if(function_exists($fn)) {
    $data = $fn();
  }
  else {
    $data['error'] = "Function {$fn} does not exist";
  }
}

output($data);
exit;


/**
 * The Actions
 */

//return all scheme names and descriptions
function schemes() {
  
  return BikeHireFeeder::get_scheme_names();

}


//return info about a scheme
function scheme() {
  
  $args = get_args();
  $dbh = get_dbh();
  
  $scheme_name = $args[2];
  $scheme = BikeHireFeeder::get_scheme($scheme_name, $dbh);
  
  if(!$scheme) {
    return "Failed to get scheme with name '{$scheme_name}'";
  }

  return $scheme->get_info();
  
}

function scheme_stations() {
  
  $args = get_args();
  $params = array();
  
  if($args[2] != 'all') {
    $params['scheme'] = $args[2];
  }
  
  foreach($_GET as $name => $value) {
    
    switch ($name) {
      case 'filter':
        $params['filter'] = (int)$value;
        break;

      case 'count':
        $params['count'] = (int)$value;
        break;

      case 'page':
        $params['page'] = (int)$value;
        break;

      case 'bbox':
        $params['bbox'] = $value;
        break;

      case 'nearest':
        $params['nearest'] = $value;
        break;

      case 'max_dist':
        $params['max_dist'] = $value;
        break;
      
      default:
        //ignore, discard
        break;
    }
    
  }
  
  
  $dbh = get_dbh();  
  return Station::find($dbh, $params);  
  
}

function scheme_station() {
  
  $dbh = get_dbh();
  $args = get_args();
  $params = array();
  
  if(!$args[2]) {
    return "scheme id not set";
  }
  
  if(!$args[4]) {
    return "station id not set";
  }
  
  $station_ids = split(',',$args[4]);
  $stations = array();
  
  foreach($station_ids as $idx => $station_id) {
    if($station = Station::load($dbh, $args[2], $station_id)) {
      $stations[] = $station;
    }
  }

  return $stations;
  
  
}


/**
 * Formats output dependendent upon extension of url
 *
 * @param string $output 
 * @return void
 * @author Andrew Larcombe
 */
function output($var) {
  $args = get_args(FALSE);
  $extension = preg_replace('/.*\.(.*?$)/','$1',$args[count($args)-1]);
  
  switch($extension) {
    case 'debug':
      print var_dump($var);    
      break;
      
    case 'xml':
    case 'html';
    case 'gjson':
    case 'json':
    default:
      print json_encode($var);
  }

}


/**
 * Returns an array of arguments passed to the script, after removing any extension
 *
 * @return void
 * @author Andrew Larcombe
 */
function get_args($remove_extension = TRUE) {
  $args = split('/', $_SERVER['PATH_INFO']);
  if($remove_extension) {
    $args[count($args)-1] = preg_replace('/\..*?$/','',$args[count($args)-1]);
  }
  return $args;
}


/**
 * Returns a database handle
 *
 * @author Andrew Larcombe
 */
function get_dbh() {
  require_once('../db.inc.php');
  return new PDO("mysql:host=$host;dbname=$database", $username, $password);
  return $dbh;
}

