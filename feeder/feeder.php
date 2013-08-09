<?php
/**
 * load up all the classes int the schemes directory, run through each declared class and for those that
 * extend BikeHireFeeder and run the update method on an instance of that class.
 *
 * Ideally should be run from a cron job
 *
 * @package default
 * @author Andrew Larcombe
 */


require_once(dirname(__FILE__) . '/../db.inc.php');
require_once(dirname(__FILE__) . '/../lib/bikehirefeeder.class.php');

while(TRUE) {

  foreach(BikeHireFeeder::get_scheme_names() as $scheme_name => $scheme_description) {
    $scheme = BikeHireFeeder::get_scheme($scheme_name, $collection);
    if($scheme) {
      print "Processing {$scheme_name} ({$scheme_description})\n";  
      $scheme->update();
    }
    else {
      print "Failed to load {$scheme_name}\n";
    }
  }

  for($i = 0; $i < 10; $i++) {
    print ".";
    sleep(30);
  }

  print "\n";

}


?>
