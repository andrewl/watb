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



require_once('../db.inc.php');
$dbh = new PDO("mysql:host=$host;dbname=$database", $username, $password);
if(!$dbh) {
  return FALSE;
}


foreach(glob('../schemes/*.class.php') as $idx => $filename) {
  require_once($filename);
}

foreach(get_declared_classes() as $idx => $classname) {
  if(get_parent_class($classname) == 'BikeHireFeeder') {
    $feeder = new $classname($dbh);
    $feeder->update();
  }
}


?>