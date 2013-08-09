<?php

include(dirname(__FILE__) .'/../lib/station.class.php');

/**
* Class BikeHireFeeder
* Abstract class, just really providing an API and simple cacheing for schemes. Should be extended for each bike hire scheme.
*/
abstract class BikeHireFeeder
{
  var $db = NULL;
  var $config = array();
    
  function __construct(MongoCollection $db) {
    $this->db = $db;
    $config = parse_ini_file(dirname(__FILE__).'/../conf/watb.ini', TRUE);
    $this->config = array_merge( (isset($config['defaults']) ? $config['defaults'] : array()),
    (isset($config[$this->name()]) ? $config[$this->name()] : array()));
  }

  /**
   * Implement this in abstract classes to provide a unique id for scheme
   *
   * @return void
   * @author Andrew Larcombe
   */
  static abstract function name();

  /**
   * Implement this in abstract classes to provide a description for the scheme
   *
   * @return void
   * @author Andrew Larcombe
   */
  static abstract function description();

  
  /**
   * Implement this in abstract classes to process a hire scheme's data. 
   *
   * @return void
   * @author Andrew Larcombe
   */
  abstract function update();
  
  /**
   * Loads content from the url
   *
   * @param string $url 
   * @return the contents of the url
   * @author Andrew Larcombe
   */
  function load($url) {
    
    $contents = file_get_contents($url);
    return $contents;
    
  }

  /**
   * Abstract function for getting a scheme from a name. Factory pattern
   *
   * @param string $name the name of the scheme
   * @param PDO $dbh handle to a database connection
   * @return void
   * @author Andrew Larcombe
   */
  static function get_scheme($name, $dbh) {
    
    //include all the scheme class files
    foreach(glob(dirname(__FILE__) . '/../schemes/*.class.php') as $idx => $filename) {
      require_once($filename);
    }

    foreach(get_declared_classes() as $idx => $classname) {
      if(get_parent_class($classname) == 'BikeHireFeeder') {
        if(call_user_func(array($classname,'name')) == $name) {
          $obj = new $classname($dbh);
          return $obj;
        }
      }
    }
    
    return FALSE;
    
  }
  
  /**
   * Returns an array of scheme names that are installed on the system
   *
   * @return void
   * @author Andrew Larcombe
   */
  static function get_scheme_names() {
    
    $scheme_names = array();
    
    //include all the scheme classe files
    foreach(glob(dirname(__FILE__) . '/../schemes/*.class.php') as $idx => $filename) {
      require_once($filename);
    }

    foreach(get_declared_classes() as $idx => $classname) {
      if(get_parent_class($classname) == 'BikeHireFeeder') {
        $scheme_names[call_user_func(array($classname,'name'))] = call_user_func(array($classname,'description'));
      }
    }
    
    return $scheme_names;
    
  }
  

  function get_info() {
    
    $sql = "select count(*) as cnt, max(updated) as upd, scheme from stations where scheme = ". $this->dbh->quote(call_user_func(array($this,'name')))." group by scheme";
    $res = $this->dbh->query($sql);
    
    if(!$res) {
      return "Failed to run query '$sql'";
    }
    
    $row = $res->fetch();
    
    return array('id' => call_user_func(array($this,'name')),
                  'description' => call_user_func(array($this,'description')),
                  'stations' => $row['cnt'],
                  'updated' => $row['upd']);
    
  }
  
}
