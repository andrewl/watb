<?php

require_once(dirname(__FILE__) .'/../lib/bikehirefeeder.class.php');

/**
* Class to processing data from the Paris Velib Scheme
*/
class Velib extends BikeHireFeeder
{
  
  /**
   * Implementation of abstract function BikeHireFeeder::name()
   *
   * @return void
   * @author Andrew Larcombe
   */
  static function name() {
    return 'paris-velib';
  }
  
  /**
   * Implementation of abstract function BikeHireFeeder::description()
   *
   * @return void
   * @author Andrew Larcombe
   */
  static function description() {
    return "Paris Velib";
  }
   
  
  /**
   * Implementation of abstraction function BikeHireFeeder::update()
   * RegEx shamelessy stolen from http://github.com/adrianshort/borisapi
   *
   * @return void
   * @author Andrew Larcombe
   */
  function update() {
    
    ini_set('user_agent', 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.9) Gecko/20071025 Firefox/2.0.0.9');
    $contents = $this->load("http://www.velib.paris.fr/service/carto");
    
    if(!$contents) {
      return FALSE;
    }
    
    $dom = new DOMDocument();
    $dom->loadXML($contents);
    $markers = $dom->getElementsByTagName('marker');
    
    foreach ($markers as $marker) {

      $station_url = "http://www.velib.paris.fr/service/stationdetails/" . $marker->getAttribute('number');
      $station_contents = $this->load($station_url);
      
      if(!$station_contents) {
        continue;
      }
      
      $station_dom = new DOMDocument();
      if(!$station_dom->loadXML($station_contents)) {
        print "loadXML failed for {$station_url}\n";
      }
      $available = $station_dom->getElementsByTagName('available');
            
      $station = new Station($this->dbh);  
      $station->scheme = $this::name();
      $station->id = $marker->getAttribute('number');
      $station->name = $marker->getAttribute('name');
      $station->latitude = $marker->getAttribute('lat');
      $station->longitude = $marker->getAttribute('lng');
      $station->bikes = $station_dom->getElementsByTagName('available')->item(0)->nodeValue;
      $station->stands = $station_dom->getElementsByTagName('free')->item(0)->nodeValue;
      $station->address = $marker->getAttribute('address');
      $station->fullAddress = $marker->getAttribute('fullAddress');
      $station->open = $marker->getAttribute('open');
      $station->bonus = $marker->getAttribute('bonus');
      $station->save();
    }
    
  }
  
}
