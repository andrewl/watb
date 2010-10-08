<?php

require_once(dirname(__FILE__) . '/../lib/bikehirefeeder.class.php');

/**
* Class to processing data from the London Bike Hire Scheme
*/
class BixiMontreal extends BikeHireFeeder
{
  
  /**
   * Implementation of abstract function BikeHireFeeder::name()
   *
   * @return void
   * @author Andrew Larcombe
   */
  static function name() {
    return 'montreal-bixi';
  }
  
  /**
   * Implementation of abstract function BikeHireFeeder::description()
   *
   * @return void
   * @author Andrew Larcombe
   */
  static function description() {
    return "Bixi Montreal";
  }
  
  
  /**
   * Implementation of abstraction function BikeHireFeeder::update()
   *
   * @return void
   * @author Andrew Larcombe
   */
  function update() {
  
    $contents = $this->load("https://profil.bixi.ca/data/bikeStations.xml");
    
    if(!$contents) {
      return FALSE;
    }
    
    $dom = new DOMDocument();
    $dom->loadXML($contents);
    $stations = $dom->getElementsByTagName('station');
    
    foreach ($stations as $station_node) {

      $station = new Station($this->dbh);  
      $station->scheme = call_user_func(array($this,'name'));
      $station->id = $station_node->getElementsByTagName('id')->item(0)->nodeValue;
      $station->name = $station_node->getElementsByTagName('name')->item(0)->nodeValue;
      $station->latitude = $station_node->getElementsByTagName('lat')->item(0)->nodeValue;
      $station->longitude = $station_node->getElementsByTagName('long')->item(0)->nodeValue;
      $station->bikes = $station_node->getElementsByTagName('nbBikes')->item(0)->nodeValue;
      $station->stands = $station_node->getElementsByTagName('nbEmptyDocks')->item(0)->nodeValue;
      $station->installed = $station_node->getElementsByTagName('installed')->item(0)->nodeValue;
      $station->installDate = $station_node->getElementsByTagName('installDate')->item(0)->nodeValue;
      $station->removalDate = $station_node->getElementsByTagName('removalDate')->item(0)->nodeValue;
      $station->temporary = $station_node->getElementsByTagName('temporary')->item(0)->nodeValue;
      $station->save();
    }
    
  }
  
}
