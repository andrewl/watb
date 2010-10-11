<?php

require_once(dirname(__FILE__) . '/../lib/bikehirefeeder.class.php');

/**
* Class to processing data from the London Bike Hire Scheme
*/
class NextbikeWachau extends BikeHireFeeder
{
  
  /**
   * Implementation of abstract function BikeHireFeeder::name()
   *
   * @return void
   * @author Andrew Larcombe
   */
  static function name() {
    return 'wachau-nextbike';
  }
  
  /**
   * Implementation of abstract function BikeHireFeeder::description()
   *
   * @return void
   * @author Andrew Larcombe
   */
  static function description() {
    return "Nextbike Wachau";
  }
  
  
  /**
   * Implementation of abstraction function BikeHireFeeder::update()
   *
   * @return void
   * @author Andrew Larcombe
   */
  function update() {
  
    $contents = $this->load("http://nextbike.co.nz/maps/nextbike-official.xml?domain=nz");
    
    if(!$contents) {
      return FALSE;
    }
    
    $dom = new DOMDocument();
    $dom->loadXML($contents);
    $cities = $dom->getElementsByTagName('city');
    
    foreach ($cities as $city) {
      if($city->getAttribute('name') == "Wachau") {
        $stations = $city->getElementsByTagName('place');
        foreach ($stations as $station_node) {
          $station = new Station($this->dbh);  
          $station->scheme = call_user_func(array($this,'name'));
          $station->id = $station_node->getAttribute('uid');
          $station->name = $station_node->getAttribute('name');
          $station->latitude = $station_node->getAttribute('lat');
          $station->longitude = $station_node->getAttribute('lng');
          $station->bikes = $station_node->getAttribute('bikes');
          $station->stands = -1;  //we never know :(
          $station->spot = $station_node->getAttribute('spot');
          $station->number = $station_node->getAttribute('number');
          $station->bike_numbers = $station_node->getAttribute('bike_numbers');
          $station->save();
        }

      }
    }
    
  }
  
}
