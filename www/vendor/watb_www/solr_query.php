<?php
//@TODO: refactor all this into a nice class
require_once __DIR__.'/../autoload.php';

function get_station_history($station_id) {

  require(__DIR__.'/../../../solr_connection.php');

  // create a client instance
  $client = new Solarium\Client($config);

  // get a select query instance
  $query = $client->createQuery($client::QUERY_SELECT);

  $query->setQuery('station_id: ' . $station_id);
  $query->createFilterQuery('update_time')->setQuery("update_time:[NOW-1DAY TO NOW]");
  $query->setStart(1);
  $query->setRows(9999);

  // this executes the query and returns the result
  $resultset = $client->execute($query);

  $history = array();

  foreach($resultset as $document) {
    $station_history = new stdClass;
    $station_history->time = strtotime($document->update_time);
    $station_history->bikes = $document->bikes;
    $station_history->docks = $document->docks;
    $history[] = $station_history;
  }

  return $history;

}

function get_latest_bikes($bbox, $hash) {
  require(__DIR__.'/../../../solr_connection.php');
  if ($hash) {
    return get_bikes_hash($config, $bbox, $hash);
  }
  else {
    return get_bikes($config, $bbox);
  }
}

function get_bikes_hash($config, $bbox, $hash) {

  $coords = explode(',',$bbox);

  // create a client instance
  $client = new Solarium\Client($config);

  // get a select query instance
  $query = $client->createSelect();

  // get the facetset component
  $facetSet = $query->getFacetSet();

  $facetSet->createFacetQuery('location')->setQuery("location:[{$coords[1]},{$coords[0]} TO {$coords[3]},{$coords[2]}]");

  $facetSet->createFacetField('geohash_4')->setField('geohash_4');

  // this executes the query and returns the result
  $resultset = $client->select($query);

  $facet = $resultset->getFacetSet()->getFacet('geohash_4');
  $hashes = array();
  foreach ($facet as $value => $count) {
    $hashes[$value] = $count;
  }

  $response = array();
  $response['hashes'] = $hashes;
  return $response;
}

function get_bikes($config, $bbox) {

  $coords = explode(',',$bbox);

  // create a client instance
  $client = new Solarium\Client($config);

  // get a select query instance
  $query = $client->createQuery($client::QUERY_SELECT);

  $query->createFilterQuery('coords')->setQuery("location:[{$coords[1]},{$coords[0]} TO {$coords[3]},{$coords[2]}]");
  $query->setStart(1);
  $query->setRows(5000);

  // this executes the query and returns the result
  $resultset = $client->execute($query);

  return json_decode($resultset->getResponse()->getBody());
}

function get_latest_update_time($config) {

  // create a client instance
  $client = new Solarium\Client($config);

  // get a select query instance
  $query = $client->createQuery($client::QUERY_SELECT);

  $query->addSort('loader_time', 'desc');
  $query->setStart(1);
  $query->setRows(1);

  // this executes the query and returns the result
  $resultset = $client->execute($query);

  if ($resultset->getNumFound() != 1) {
    //    return NULL;
  }

  $docs = $resultset->getDocuments();

  return $docs[0]->update_time;

}
