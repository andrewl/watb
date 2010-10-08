<?php

include(dirname(__FILE__) .'/../lib/station.class.php');

/**
* Class BikeHireFeeder
* Abstract class, just really providing an API and simple cacheing for schemes. Should be extended for each bike hire scheme.
*/
abstract class BikeHireFeeder
{
  var $cache_dir = '/tmp';
  var $cache_max_time = 300;
  var $dbh = NULL;
    
  function __construct(PDO $dbh) {
    $this->dbh = $dbh;
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
   * Loads content from the url, or from the cache if it is still in time.
   *
   * @param string $url 
   * @return the contents of the url or the cached file
   * @author Andrew Larcombe
   */
  function load($url) {
    $cache_file = $this->cache_dir . "/" . md5($url) . "_" . get_class($this) . '.feed_cache';
    
    if(!file_exists($cache_file) || (filemtime($cache_file) + $this->cache_max_time) < time()) { 
      if(($contents = file_get_contents($url)) !== FALSE) {
        file_put_contents($cache_file, $contents);
      }
      else {
        $contents = FALSE;
      }
    }
    else {
      $contents = file_get_contents($cache_file);
    }
    
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
    
    //include all the scheme classe files
    foreach(glob(dirname(__FILE__) . '/../schemes/*.class.php') as $idx => $filename) {
      require_once($filename);
    }

    foreach(get_declared_classes() as $idx => $classname) {
      if(get_parent_class($classname) == 'BikeHireFeeder') {
        if($classname::name() == $name) {
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
        $scheme_names[$classname::name()] = $classname::description();
      }
    }
    
    return $scheme_names;
    
  }
  
}
