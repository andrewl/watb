<?php

require_once('../lib/bikehirefeeder.class.php');

/**
* Class to processing data from the London Bike Hire Scheme
*/
class London extends BikeHireFeeder
{
  
  /**
   * Implementation of abstraction function BikeHireFeeder::update()
   *
   * @return void
   * @author Andrew Larcombe
   */
  function update() {
    
    $contents = $this->load("http://web.barclayscyclehire.tfl.gov.uk/maps");
    
    if(!$contents) {
      return FALSE;
    }
    
    $regex = '/\{id:"(\d+)".+?name:"(.+?)".+?lat:"(.+?)".+?long:"(.+?)".+?nbBikes:"(\d+)".+?nbEmptyDocks:"(\d+)".+?installed:"(.+?)".+?locked:"(.+?)".+?temporary:"(.+?)"\}/';  

    $matches[1][1] = 0;
    while(preg_match($regex, $contents, $matches, PREG_OFFSET_CAPTURE, $matches[1][1])) {
      $station = new Station($this->dbh);  
      $station->scheme = "london";
      $station->id = $matches[1][0];
      $station->name = $matches[2][0];
      $station->latitude = $matches[3][0];
      $station->longitude = $matches[4][0];
      $station->bikes = $matches[5][0];
      $station->stands = $matches[6][0];
      $station->installed = $matches[7][0];
      $station->locked = $matches[8][0];
      $station->temporary = $matches[9][0];
      $station->save();
    }
    
  }
  
}
