<?php

require_once(dirname(__FILE__) .'/../lib/bikehirefeeder.class.php');

/**
* Class to processing data from the Paris Velib Scheme
*/
class Velib extends BikeHireFeeder
{
  
  /**
   * Implementation of abstraction function BikeHireFeeder::update()
   * RegEx shamelessy stolen from http://github.com/adrianshort/borisapi
   *
   * @return void
   * @author Andrew Larcombe
   */
  function update() {
    
    $contents = $this->load("http://www.velib.paris.fr/service/carto");
    
    if(!$contents) {
      return FALSE;
    }
    
    $dom = new DOMDocument();
    $dom->loadXML($contents);
    $markers = $dom->getElementsByTagName('marker');
    
    foreach ($markers as $marker) {
      
      $station_contents = $this->load("http://www.velib.paris.fr/service/stationdetails/" . $marker->getAttribute('number'));
      
      if(!$station_contents) {
        continue;
      }
      
      $station_dom = new DOMDocument();
      $station_dom->loadXML($station_contents);
      $available = $station_dom->getElementsByTagName('available');
            
      $station = new Station($this->dbh);  
      $station->scheme = "Paris Velib";
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
