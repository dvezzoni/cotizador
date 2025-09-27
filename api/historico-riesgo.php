<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

function http_get(string $url, int $timeout=10){
  if(function_exists('curl_init')){
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_CONNECTTIMEOUT => $timeout,
      CURLOPT_TIMEOUT => $timeout,
      CURLOPT_USERAGENT => 'Underc0de-DolarLanding/1.0'
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if($code>=200 && $code<300 && $resp!==false) return $resp;
    return false;
  } else {
    $ctx = stream_context_create(['http'=>[
      'timeout'=>$timeout,
      'ignore_errors'=>true,
      'header'=>"User-Agent: Underc0de-DolarLanding/1.0\r\n"
    ]]);
    $resp = @file_get_contents($url,false,$ctx);
    return $resp!==false ? $resp : false;
  }
}

$url = 'https://api.argentinadatos.com/v1/finanzas/indices/riesgo-pais';
$raw = http_get($url);
if($raw === false){
  http_response_code(502);
  echo json_encode(['error'=>'No se pudo obtener histórico de ArgentinaDatos']);
  exit;
}

$arr = json_decode($raw, true);
if(!is_array($arr)){
  http_response_code(502);
  echo json_encode(['error'=>'Histórico inválido']);
  exit;
}

// Normalize and sort by fecha DESC
$norm = [];
foreach($arr as $row){
  if(isset($row['fecha']) && isset($row['valor']) && is_numeric($row['valor'])){
    $norm[] = ['fecha'=>strval($row['fecha']), 'valor'=>floatval($row['valor'])];
  }
}
usort($norm, function($a,$b){
  // YYYY-MM-DD lexicographically sorts correctly
  return strcmp($b['fecha'], $a['fecha']);
});

$prev = null; $last = null;
if(count($norm) >= 2){
  $last = $norm[0]['valor'];
  $prev = $norm[1]['valor'];
} elseif(count($norm) === 1){
  $last = $norm[0]['valor'];
}

echo json_encode(['prev'=>$prev, 'last'=>$last], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
