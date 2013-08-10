<?php
require_once __DIR__.'/../autoload.php';
require_once __DIR__.'/solr_query.php';

$app = new Silex\Application();

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
    ));

$app->get('/stations', function () use ($app) {
    return $app->json(get_latest_bikes());
});


$app->get('/{type}', function($type) use ($app) {
  if ($type != 'bikes' && $type != 'docks') {
    $type = 'bikes';
  }
  $vars = array('criteria' => $type);
  return $app['twig']->render('index.twig', $vars);
})->value('type', 'bikes');

$app->run();
