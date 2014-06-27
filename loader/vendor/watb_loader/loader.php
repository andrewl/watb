<?php
require(__DIR__.'/solr_config.php');
require 'vendor/autoload.php';
use Guzzle\Http\Client;
use Camcima\Geohash;

$loader_time = time();

//@TODO - make this all nice and classy :)
get_decaux($config, $loader_time);
get_london($config, $loader_time);

function get_weather($country, $city) {

  if ($country == 'BE' && $city == 'Bruxelles-Capitale') {
    $city = 'Bruxelles';
  }
  
  $cache_file = "./weather_".$country."_".$city.".cache";

  if (file_exists($cache_file) && (time() - filemtime($cache_file) < 3600)) {
    print "Retrieving weather for $country $city from cache\n";
    $weather_data = file_get_contents($cache_file);
  }
  else {
    print "Retrieving weather for $country $city from wunderground service\n";
    $http_client = new Client();
    $response = $http_client->get('http://api.wunderground.com/api/22014eb8a46f058b/conditions/q/'.$country.'./'.$city.'.json')->send();
    $weather_data = (string)($response->getBody());
    file_put_contents($cache_file, $weather_data);
  }
  $weather = json_decode($weather_data);
  return $weather;
}

function get_london($config, $loader_time) {

  $geohash = new Geohash();

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

  $weather = get_weather('UK','London');
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
  $iso_loader_time = date('c', $loader_time) . 'Z';
  $day = date('l', $recorded_time);
  $time_period = floor(($recorded_time % 86400) / 900);

  foreach($docking_stations->station as $station) {


    // create a new document for the data
    $docking_station_doc = $update->createDocument();
    //$docking_station_doc->id = 'london_' . $recorded_time . '_' . $station->id;
    $docking_station_doc->id = 'london_' . $station->id;
    $docking_station_doc->scheme = 'london';
    $docking_station_doc->update_time = $iso_recorded_time;
    $docking_station_doc->loader_time = $iso_loader_time;
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

    $geohash->setLatitude($station->lat);
    $geohash->setLongitude($station->long);
    for ($i = 1; $i < 6; $i++) {
      $fieldname = "geohash_{$i}";
      $docking_station_doc->$fieldname = substr((string)$geohash,0,$i);
    }

    print "Adding {$docking_station_doc->id}\n";

    $update->addDocument($docking_station_doc);

  }

  $update->addCommit();

  // this executes the query and returns the result
  $result = $client->update($update);

  print $result->getStatus();

}


function get_decaux($config, $loader_time) {

  $geohash = new Geohash();

  $recorded_time = time();
  $iso_recorded_time = date('c', $recorded_time) . 'Z';
  $iso_loader_time = date('c', $loader_time) . 'Z';
  $day = date('l', $recorded_time);
  $time_period = floor(($recorded_time % 86400) / 900);

  $api_key = '374cb3f4b53cb25d0a4b3071eb7b426948bd11da';

  //get the list of contracts
  $http_client = new Client();
  $response = $http_client->get('https://api.jcdecaux.com/vls/v1/contracts?apiKey='.$api_key)->send();
  $contracts = json_decode((string)$response->getBody());

  foreach($contracts as $contract) {
    $country_code = $contract->country_code;
    $city = $contract->name;

    $weather = get_weather($country_code, $city);
    $weather_description = (string)$weather->current_observation->weather;
    $temperature = (float)$weather->current_observation->temp_c;
    $precipitation = (float)$weather->current_observation->precip_today_metric;
    $wind_speed = (float)$weather->current_observation->wind_kph;

    $http_client = new Client();
    $response = $http_client->get('https://api.jcdecaux.com/vls/v1/stations?contract='.$city.'&apiKey='.$api_key)->send();
    $stations = json_decode((string)$response->getBody());

    // create a client instance
    $client = new Solarium\Client($config);

    // get an update query instance
    $update = $client->createUpdate();


    foreach($stations as $station) {
      // create a new document for the data
      $docking_station_doc = $update->createDocument();
      //$docking_station_doc->id = strtolower($city) . '_' . $recorded_time . '_' . $station->number;
      $docking_station_doc->id = strtolower($city) . '_' . $station->number;
      $docking_station_doc->scheme = strtolower($city);
      $docking_station_doc->update_time = $iso_recorded_time;
      $docking_station_doc->loader_time = $iso_loader_time;
      $docking_station_doc->day = $day;
      $docking_station_doc->time_period = $time_period;
      $docking_station_doc->weather_description = $weather_description;
      $docking_station_doc->temperature = $temperature;
      $docking_station_doc->precipitation = $precipitation;
      $docking_station_doc->wind_speed = $wind_speed;

      $docking_station_doc->station_name = $station->name;
      $docking_station_doc->station_id = $station->number;
      $docking_station_doc->bikes = $station->available_bikes;
      $docking_station_doc->docks = $station->available_bike_stands;

      if (isset($station->position->lat) && isset($station->position->lng)) {

        $docking_station_doc->location = "{$station->position->lat},{$station->position->lng}";
        
        $geohash->setLatitude($station->position->lat);
        $geohash->setLongitude($station->position->lng);
        for ($i = 1; $i < 6; $i++) {
          $fieldname = "geohash_{$i}";
          $docking_station_doc->$fieldname = substr((string)$geohash,0,$i);
        }

        print "Adding {$docking_station_doc->id}\n";

        $update->addDocument($docking_station_doc);

      }
      
    }

    try {
      $update->addCommit();
      $result = $client->update($update);
      print  $result->getStatus();
    }
    catch (Exception $e) {
      print $e->getMessage();
      exit;
    }

  }

}
