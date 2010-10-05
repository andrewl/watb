<?php
/**
* class Station
* An individual docking station within a bike hire scheme
*/
class Station
{
  var $_dbh = NULL;
  
  /**
   * The name of the bike hire scheme this station belongs to
   *
   * @var string
   */
  var $scheme = NULL;
  
  /**
   * The ID of this station within the scheme. The combination of scheme + id should be unique
   *
   * @var int
   */
  var $id = NULL;
  
  /**
   * The name of the docking station
   *
   * @var string
   */
  var $name = NULL;
  
  /**
   * The number of bikes available at this station
   *
   * @var int
   */
  var $bikes = NULL;
  
  /**
   * The number of stands available at this station
   *
   * @var int
   */
  var $stands = NULL;
  
  /**
   * The latitude and longitude of this station
   *
   */
  var $longitude = NULL;
  var $latitude = NULL;
  
  /**
   * Any other variables are stored in this array which is serialised for data storage
   *
   * @var string
   */
  var $extra = array();


  function __construct(PDO $dbh)
  {
    $this->_dbh = $dbh;
  }
  
  function __set($name, $value) {
    $this->extra[$name] = $value;
  }
    
  /**
   * Attempt to update an existing station, or create a new one
   *
   * @return void
   * @author Andrew Larcombe
   */
  function save() {
    //using exec here rather than prepare because prepare doesn't seem to like GeomFromText
    //attempt to update. If returned no rows, attempt to insert.
    $sql = "UPDATE stations set bikes = " . $this->_dbh->quote($this->bikes) . ", " .
                               "stands = " . $this->_dbh->quote($this->stands) . ", " .
                               "extra = " . $this->_dbh->quote(serialize($this->extra)) . ", " .
                               "updated = NOW() " . 
            "WHERE scheme = " . $this->_dbh->quote($this->scheme) . " AND id = " . $this->_dbh->quote($this->id);
    
    if($this->_dbh->exec($sql) === 0) {
      
      //attempt the insert
      $sql = "INSERT INTO stations (scheme, id, name, bikes, stands, extra, location, updated) VALUES (" . $this->_dbh->quote($this->scheme) . ", " .
                                            $this->_dbh->quote($this->id) . ", " .
                                            $this->_dbh->quote($this->name) . ", " .
                                            $this->_dbh->quote($this->bikes) . ", " .
                                            $this->_dbh->quote($this->stands) . ", " .
                                            $this->_dbh->quote(serialize($this->extra)) . ", " .
                                            "GeomFromText('POINT (".(float)$this->longitude." ".(float)$this->latitude.")'), NOW())";
      $this->_dbh->exec($sql);

      if((int)$this->_dbh->errorCode()) {
        $this->log("Insert failed: Error details " . print_r($this->_dbh->errorInfo(),1));
        return FALSE;
      }
    }
    elseif((int)$this->_dbh->errorCode()) {
      $this->log("Update failed: Error details " . print_r($this->_dbh->errorInfo(),1));
      return FALSE;
    }

    return TRUE;

  }
  
  /**
   * Load a station from the DB given the scheme name and station id
   *
   * @param PDO $dbh 
   * @param string $scheme 
   * @param string $id 
   * @return A new station object
   * @author Andrew Larcombe
   */
  static function load(PDO $dbh, $scheme, $id) {
    $sql = "select *, X(location) as longitude, Y(location) as latitude from stations where scheme = " . $dbh->quote($scheme) . " and id = " . $dbh->quote($id);
    $res = $dbh->query($sql);
        
    if($res->rowCount() != 1) {
      return NULL;
    }
    
    $row = $res->fetch();
    $station = new Station($dbh);
    $station->id = $row['id'];
    $station->scheme = $row['scheme'];    
    $station->longitude = $row['longitude'];
    $station->latitude = $row['latitude'];
    $station->name = $row['name'];
    $station->bikes = $row['bikes'];
    $station->stands = $row['stands'];
    $station->extra = unserialize($row['extra']);
    $station->updated = $row['updated'];
    
    return $station;
    
  }
  
  /**
   * Locate the nearest $count stations from this latitude, longitude
   *
   * @param PDO $dbh 
   * @param float $longitude 
   * @param float $latitude 
   * @param int $count 
   * @return void
   * @author Andrew Larcombe
   */
  static function find_nearest(PDO $dbh, $longitude, $latitude, $count = 1) {
    
    $sql = "select scheme, id,
                            acos( 
                            cos(radians( Y(location) ))
                            * cos(radians( {$latitude} ))
                            * cos(radians( X(location) ) - radians( {$longitude} ))
                            + sin(radians( Y(location) )) 
                            * sin(radians( {$latitude} ))
                            ) * 6371000 AS dist 
          from stations order by dist asc limit {$count}";
                  
    $res = $dbh->query($sql);
    
    $stations = array();
    while($row = $res->fetch()) {
      $station = Station::load($dbh, $row['scheme'], $row['id']);
      $station->distance = $row['dist'];
      $stations[] = $station;
    }
    
    return $stations;

  }
  
  /**
   * Crappy logging function. Needs to be ripped out and replaced
   *
   * @param string $message 
   * @return void
   * @author Andrew Larcombe
   */
  function log($message) {
    print $message . "\n";
  }
  
}
