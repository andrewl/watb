<?php
require(__DIR__.'/solr_config.php');
require 'vendor/autoload.php';
use Guzzle\Http\Client;

get_london();

function get_london() {

  /**
   * Implementation of abstraction function BikeHireFeeder::update()
   * RegEx shamelessy stolen from http://github.com/adrianshort/borisapi
   *
   * @return void
   * @author Andrew Larcombe
   */
  $client = new Client();
  $response = $client->get('http://www.tfl.gov.uk/tfl/syndication/feeds/cycle-hire/livecyclehireupdates.xml')->send();

  $docking_stations = simplexml_load_string((string)$response->getBody());

  // create a client instance
  $client = new Solarium\Client($config);

  // get an update query instance
  $update = $client->createUpdate();

  $recorded_time = time();
  $iso_recorded_time = date('c', $recorded_time) . 'Z';

  foreach($docking_stations->station as $station) {

    // create a new document for the data
    $docking_station_doc = $update->createDocument();
    $docking_station_doc->id = 'london_' . $recorded_time . '_' . $station->id;
    $docking_station_doc->update_time = $iso_recorded_time;
    $docking_station_doc->station_name = $station->name;
    $docking_station_doc->station_id = $station->id;
    $docking_station_doc->bikes = $station->nbBikes;
    $docking_station_doc->docks = $station->nbEmptyDocks;
    $docking_station_doc->location = "{$station->lat},{$station->long}";

    print "Adding {$docking_station_doc->id}\n";

    $update->addDocument($docking_station_doc);

  }

  $update->addCommit();

  // this executes the query and returns the result
  $result = $client->update($update);

  print $result->getStatus();

}
