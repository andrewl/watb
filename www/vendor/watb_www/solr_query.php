<?php
require_once __DIR__.'/../autoload.php';

function get_latest_bikes() {
  require(__DIR__.'/../../../solr_connection.php');
  $latest_update_time = get_latest_update_time($config);
  return get_bikes($config, $latest_update_time);
}


function get_bikes($config, $update_time) {

  // create a client instance
  $client = new Solarium\Client($config);

  // get a select query instance
  $query = $client->createQuery($client::QUERY_SELECT);

  $query->createFilterQuery('time')->setQuery("update_time:[{$update_time} TO {$update_time}]");
  $query->setStart(1);
  $query->setRows(999);

  // this executes the query and returns the result
  $resultset = $client->execute($query);

  return json_decode($resultset->getResponse()->getBody());

}

function get_latest_update_time($config) {

  // create a client instance
  $client = new Solarium\Client($config);

  // get a select query instance
  $query = $client->createQuery($client::QUERY_SELECT);

  $query->addSort('update_time', 'desc');
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
