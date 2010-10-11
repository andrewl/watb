<?php

require_once(dirname(__FILE__) . '/../lib/bikehirefeeder.class.php');

/**
* Class to processing data from the London Bike Hire Scheme
*/
class Melbourne extends BikeHireFeeder
{
  /**
   * Implementation of abstract function BikeHireFeeder::name()
   *
   * @return void
   * @author Andrew Larcombe
   */
  static function name() {
    return 'melbourne-bikehire';
  }
  
  /**
   * Implementation of abstract function BikeHireFeeder::description()
   *
   * @return void
   * @author Andrew Larcombe
   */
  static function description() {
    return "Melbourne Bikehire";
  }
  
  
  
  /**
   * Implementation of abstraction function BikeHireFeeder::update()
   *
   * @return void
   * @author Andrew Larcombe
   */
  function update() {
    
    $contents = $this->load("http://www.melbournebikeshare.com.au/stationmap/data");
    
    if(!$contents) {
      return FALSE;
    }
    
    $regex = '/\{ "id": "(\d+)".+?"name": "(.+?)".+?"terminalName": "(.+?)".+?"lat": "(.+?)".+?"long": "(.+?)".+?"installed": "(.+?)".+?"locked": "(.+?)".+?"temporary": "(.+?)".+?"nbBikes": "(\d+)".+?"nbEmptyDocks": "(\d+)".+? \}/';  

    $matches[1][1] = 0;
    while(preg_match($regex, $contents, $matches, PREG_OFFSET_CAPTURE, $matches[1][1])) {            
      $station = new Station($this->dbh);  
      $station->scheme = call_user_func(array($this,'name'));
      $station->id = $matches[3][0];
      $station->name = $matches[2][0];
      $station->latitude = $matches[4][0];
      $station->longitude = $matches[5][0];
      $station->bikes = $matches[9][0];
      $station->stands = $matches[10][0];
      $station->installed = $matches[6][0];
      $station->locked = $matches[7][0];
      $station->temporary = $matches[8][0];
      $station->save();
    }
    
  }
  
}
