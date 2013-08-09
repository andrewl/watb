<?php
/**
* class Station
* An individual docking station within a bike hire scheme
*/
class Station
{
  var $db = NULL;
  
  /**
   * All variables are stored in this array which is serialised for data storage
   *
   * @var string
   */
  var $data = array();


  function __construct(MongoCollection $db)
  {
    $this->db = $db;
  }
  
  function __set($name, $value) {
    $this->data[$name] = $value;
  }

  function setTime($timestamp = NULL) {

    if(!$timestamp) {
      $timestamp = time();
    }

    $this->time = $timestamp;

  }

  function preInsert() {

    if(!isset($this->data['time'])) {
      $this->setTime();
    }

  }
    
  /**
   * Attempt to update an existing station, or create a new one
   *
   * @return void
   * @author Andrew Larcombe
   */
  function save() {

    $this->preInsert();

    $this->log('Inserting ' . $this->data['name']);
    $this->db->insert($this->data);   

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
   * Return stations from cycle hire schemes
   *
   * @param PDO $dbh 
   * @param string $params
   * An array of params used to limit the number of stations returned
   * filter = 2 (only stations with empty stands) or 1 (only stations with bikes available)
   * bbox = x0,y0,x1,y1 (only stations within these bounds)
   * nearest = x0,y0 (order by distance from x0, y0)
   * max_dist = max distance in metres from nearest
   * count = number of stations to return
   * page = used in conjunction with count. Returns n'th page of count results
   * scheme = limit to those bikes in a particular scheme
   * @return void
   * @author Andrew Larcombe
   */
  static function find(PDO $dbh, $params) {

    $where_clauses = array('1=1');
    $fields = array('scheme','id');
    $orders = array();
    $limits = "";
    
    foreach($params as $key => $value) {
      switch ($key) {
        case 'scheme':
          $where_clauses[] = 'scheme = ' . $dbh->quote($value);
        case 'filter':
          if($value == 4) {
            $where_clauses[] = 'stands = 0';
          }
          else if($value == 3) {
            $where_clauses[] = 'bikes = 0';
           }
          else if($value == 2) {
            $where_clauses[] = 'stands > 0';
          }
          else if($value == 1) {
            $where_clauses[] = 'bikes > 0';
          }
          break;

        case 'bbox':
          list($x0,$y0,$x1,$y1) = split(",",$value);
          if(isset($x0) && isset($y0) && isset($x1) && isset($y1)) {
            $where_clauses[] = "Within(location, GeomFromText(\"LINESTRING({$x0} {$y0},{$x0} {$y1},{$x1} {$y1},{$x1} {$y0},{$x0} {$y0})\"))";
          }
          break;

        default:
          //ignore, discard
          break;
      }
    }
    
    if(isset($params['nearest'])) {
      list($longitude, $latitude) = split(',' , $params['nearest']); 
      
      $distance_function = "(acos(cos(radians( Y(location) ))
        * cos(radians( {$latitude} ))
        * cos(radians( X(location) ) - radians( {$longitude} ))
        + sin(radians( Y(location) )) 
        * sin(radians( {$latitude} ))
        ) * 6371000)";
      
      $fields[] = "$distance_function AS dist";
        
      $orders[] = 'dist asc';
      
      if(isset($params['max_dist'])) {
        $where_clauses[] = "$distance_function < " . (float)$params['max_dist'];
      }
      
    }
    
    if(isset($params['count'])) {
      if(isset($params['page']) && $params['page'] > 1) {
        $limits = 'LIMIT ' .  abs((int)$params['count']) . ' OFFSET ' . abs((int)$params['page']*(int)$params['count']);
      }
      else {
        $limits = 'LIMIT ' . abs((int)$params['count']);
      }
    }
    
    $sql = "SELECT " . join(',',$fields) . " FROM stations WHERE " . join(' AND ',$where_clauses);
    if(count($orders)) {
      $sql .=  " order by " . (join(',',$orders));
    }
    $sql .= " " . $limits;
    
    if(!$res = $dbh->query($sql)) {
      return "Failed to run sql '{$sql}'";
    }
    
    $stations = array();
    while($row = $res->fetch()) {
      $station = Station::load($dbh, $row['scheme'], $row['id']);
      if(isset($row['dist'])) {
        $station->distance = $row['dist'];
      }
      unset($station->_dbh);
      $stations[] = $station;
    }
    
    return $stations;
    
  }
  
  
  /**
   * Crappy logging function. Needs to be ripped out and replaced, maybe with log4php
   *
   * @param string $message 
   * @return void
   * @author Andrew Larcombe
   */
  function log($message) {
    print $message . "\n";
  }
  
}
