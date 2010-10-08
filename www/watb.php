<?php
require_once('../lib/station.class.php');

if(!isset($_SERVER['PATH_INFO'])) {
  print json_encode("PATH_INFO not set");
}

$args = get_args();

//the main controller
switch($args[1]) {
  
  case 'nearest':
  default:
    $fn = 'nearest';
}

if(function_exists($fn)) {
  $data = $fn();
}
else {
  $data['error'] = "Function {$fn} does not exist";
}

output($data);
exit;


/**
 * The Actions
 */


/**
 * nearest/LON/LAT/COUNT/FILTER
 * LON - longitude
 * LAT - latitude
 * COUNT - max number of results to return
 * FILTER - 0 all stations, 1 only stations with bikes, 2 only stations with stands
 *
 * @return void
 * @author Andrew Larcombe
 */
function nearest() {
  
  $dbh = get_dbh();
  $args = get_args();
  
  $lon = (float)$args[2];
  $lat = (float)$args[3];
  $count = (int)$args[4];
  $filter = isset($args[5]) ? (int)$args[5] : 0;

  return Station::find_nearest($dbh, $lon, $lat, $count, $filter);

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
  // if(!$dbh) {
  //   print json_encode("Failed to connect to database");
  // }
  return $dbh;
}

