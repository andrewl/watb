<?php
require_once __DIR__.'/../autoload.php';
require_once __DIR__.'/solr_query.php';

$app = new Silex\Application();

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
    ));

$app->get('/station_history/{station_id}', function ($station_id) use ($app) {
    return $app->json(get_station_history($station_id));
});

$app->get('/stations/{bbox}/{hash}', function ($bbox, $hash) use ($app) {
    return $app->json(get_latest_bikes($bbox, $hash));
})->value('bbox', '39,-44,54,40')->value('hash',NULL);

$app->get('/{type}/{bbox}', function($type, $bbox) use ($app) {
  
  if ($type != 'bikes' && $type != 'docks') {
    $type = 'bikes';
  }

  $bbox_coords = explode(',', $bbox);
  
  if(count($bbox_coords)!=4) {
//    $bbox_coords = explode(',', '51.503,-0.161,51.521,-0.05');
    $bbox_coords = explode(',', '39.503,-44,54.521,40');
  }

  $vars = array('criteria' => $type, 'bbox' => $bbox_coords);

  return $app['twig']->render('index.twig', $vars);

//})->value('type', 'bikes')->value('bbox','51.503,-0.161,51.521,-0.05');
})->value('type', 'bikes')->value('bbox','39,-44,54,40');

$app->run();
