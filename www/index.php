<?php
if(!isset($_SERVER['PATH_INFO'])) {
  print json_encode("PATH_INFO not set");
}



require_once('../db.inc.php');
$dbh = new PDO("mysql:host=$host;dbname=$database", $username, $password);
if(!$dbh) {
  print json_encode("Failed to connect to database");
}


$params = split('/', $_SERVER['PATH_INFO']);

$lat = (float)$params[1];
$lon = (float)$params[2];
$count = (int)$params[3];

require_once('../lib/station.class.php');

$stations = Station::find_nearest($dbh, $lon, $lat, $count);
print json_encode($stations);