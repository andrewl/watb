<?php

require_once(dirname(__FILE__) . '/../lib/bikehirefeeder.class.php');

/**
* Class to processing data from the London Bike Hire Scheme
*/
class Rio extends BikeHireFeeder
{
  
  /**
   * Implementation of abstract function BikeHireFeeder::name()
   *
   * @return void
   * @author Andrew Larcombe
   */
  static function name() {
    return 'rio-pedelario';
  }
  
  /**
   * Implementation of abstract function BikeHireFeeder::description()
   *
   * @return void
   * @author Andrew Larcombe
   */
  static function description() {
    return "PedelaRio";
  }
  
  
  
  /**
   * Implementation of abstraction function BikeHireFeeder::update()
   *
   * @return void
   * @author Andrew Larcombe
   */
  function update() {
    
    $contents = $this->load("http://www.zae.com.br/zaerio/mapaestacao.asp");
    
    if(!$contents) {
      return FALSE;
    }
    
    if(!preg_match('!map.clearOverlays()!', $contents, $matches, PREG_OFFSET_CAPTURE, 0)) {
      return FALSE;
    }
    
    // criaPonto(point,1,'Santos Dumont','Av.Rodrigo Otávio','Av. Rodrigo Otávio/Bartolomeu Mitre','8x6',2,'A','EO',14,1,1155,7,14) );
    
    $regex = "!GLatLng\((.+?),(.+?)\);.*?criaPonto\(point,(.+?),'(.+?)','(.+?)','(.+?)','(.+?)',(.+?),'(.+?)','(.+?)',(.+?),(.+?),(.+?),(.+?),(.+?)\) \);!s";
    
    // function criaPonto(ponto, 3 idEstacao, nome, endereco, referencia, descTipo, codArea, statusOnline, 
    //   statusOperacao, numBicicletas, vagasOcupadas, ocorrenciasAbertas, taxaOcupacao){
    // 
    $matches[1][1] = $matches[0][1];
    while(preg_match($regex, $contents, $matches, PREG_OFFSET_CAPTURE, $matches[1][1])) {
      
      // print_r($matches); exit;
      $station = new Station($this->dbh);  
      $station->scheme = call_user_func(array($this,'name'));
      $station->id = $matches[3][0];
      $station->name = $matches[4][0];
      $station->latitude = $matches[1][0];
      $station->longitude = $matches[2][0];
      $station->bikes = $matches[11][0];
      $station->stands = $matches[12][0];
      
      $station->endereco = $matches[5][0];
      $station->referencia = $matches[6][0];
      $station->descTipo = $matches[7][0];
      $station->codArea = $matches[8][0];      
      $station->statusOnline = $matches[9][0];
      $station->statusOperacao = $matches[10][0];      
      $station->ocorrenciasAbertas = $matches[11][0];      
      $station->taxaOcupacao = $matches[12][0];      
      $station->save();
    }
    
  }
  
}
