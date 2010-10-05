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
$dbh = new PDO("mysql:host=$host;dbname=$database", $username, $password);
if(!$dbh) {
  print "Failed to connect to database\n";  
  return FALSE;
}


foreach(glob(dirname(__FILE__) . '/../schemes/*.class.php') as $idx => $filename) {
  print "Loading {$filename}\n";
  require_once($filename);
}

foreach(get_declared_classes() as $idx => $classname) {
  if(get_parent_class($classname) == 'BikeHireFeeder') {
    print "Processing $classname\n";  
    $feeder = new $classname($dbh);
    $feeder->update();
  }
}


?>