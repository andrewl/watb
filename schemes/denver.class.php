<?php

require_once(dirname(__FILE__) . '/../lib/bikehirefeeder.class.php');

/**
* Class to processing data from the London Bike Hire Scheme
*/
class Denver extends BikeHireFeeder
{
  
  /**
   * Implementation of abstract function BikeHireFeeder::name()
   *
   * @return void
   * @author Andrew Larcombe
   */
  static function name() {
    return 'denver-bcycle';
  }
  
  /**
   * Implementation of abstract function BikeHireFeeder::description()
   *
   * @return void
   * @author Andrew Larcombe
   */
  static function description() {
    return "Denver Bcycle";
  }
  
  
  
  /**
   * Implementation of abstraction function BikeHireFeeder::update()
   * RegEx shamelessy stolen from http://github.com/adrianshort/borisapi
   *
   * @return void
   * @author Andrew Larcombe
   */
  function update() {
    
    $contents = $this->load("http://denver.bcycle.com/");
    
    if(!$contents) {
      return FALSE;
    }
    
    if(!preg_match('!function LoadKiosks!', $contents, $matches, PREG_OFFSET_CAPTURE, 0)) {
      return FALSE;
    }
    
    $regex = "!var point = new google.maps.LatLng\((.+?), (.+?)\);.*?createMarker\(point, \"<div class='location'><strong>(.+?)</strong><br />(.+?)</div><div class='avail'>Bikes available: <strong>(\d+)</strong><br />Docks available: <strong>(\d+)</strong></div><br/>!s";
    
    $matches[1][1] = $matches[0][1];
    while(preg_match($regex, $contents, $matches, PREG_OFFSET_CAPTURE, $matches[1][1])) {
            
      $station = new Station($this->dbh);  
      $station->scheme = $this::name();
      $station->id = $matches[3][0];
      $station->name = $matches[4][0];
      $station->latitude = $matches[2][0];
      $station->longitude = $matches[1][0];
      $station->bikes = $matches[5][0];
      $station->stands = $matches[6][0];
      $station->save();
    }
    
  }
  
}
