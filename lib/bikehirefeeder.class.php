<?php

include('../lib/station.class.php');

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

    if(!file_exists($cache_file) || (filemtime($cache_file) + $cache_max_time) > time()) { 
      $contents = file_get_contents($url);
      file_put_contents($cache_file, $contents);
    }
    else {
      $contents = file_get_contents($cache_file);
    }
    
    return $contents;
  }
  
}
