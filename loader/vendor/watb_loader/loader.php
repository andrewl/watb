<?php
require(__DIR__.'/solr_config.php');
require 'vendor/autoload.php';
use Guzzle\Http\Client;

//@TODO - make this all nice and classy :)
get_london();

function get_london() {

  /**
   * Implementation of abstraction function BikeHireFeeder::update()
   * RegEx shamelessy stolen from http://github.com/adrianshort/borisapi
   *
   * @return void
   * @author Andrew Larcombe
   */
  $http_client = new Client();
  $response = $http_client->get('http://www.tfl.gov.uk/tfl/syndication/feeds/cycle-hire/livecyclehireupdates.xml')->send();
  $docking_stations = simplexml_load_string((string)$response->getBody());

  $response = $http_client->get('http://api.wunderground.com/api/22014eb8a46f058b/conditions/q/UK/London.json')->send();
  $weather = json_decode((string)$response->getBody());

  $weather_description = $weather->current_observation->weather;
  $temperature = $weather->current_observation->temp_c;
  $precipitation = $weather->current_observation->precip_today_metric;
  $wind_speed = $weather->current_observation->wind_kph;


  // create a client instance
  $client = new Solarium\Client($config);

  // get an update query instance
  $update = $client->createUpdate();

  $recorded_time = time();
  $iso_recorded_time = date('c', $recorded_time) . 'Z';
  $day = date('l', $recorded_time);
  $time_period = floor(($recorded_time % 86400) / 900);

  foreach($docking_stations->station as $station) {

    // create a new document for the data
    $docking_station_doc = $update->createDocument();
    $docking_station_doc->id = 'london_' . $recorded_time . '_' . $station->id;
    $docking_station_doc->scheme = 'london';
    $docking_station_doc->update_time = $iso_recorded_time;
    $docking_station_doc->day = $day;
    $docking_station_doc->time_period = $time_period;
    $docking_station_doc->weather_description = $weather_description;
    $docking_station_doc->temperature = $temperature;
    $docking_station_doc->precipitation = $precipitation;
    $docking_station_doc->wind_speed = $wind_speed;

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
