<?php
// api/historico-blue.php - Histórico del dólar blue (compra/venta) usando Bluelytics Evolution
// Uso: /api/historico-blue.php?range=90  (días)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$range = isset($_GET['range']) ? intval($_GET['range']) : 90;
if($range <= 0) $range = 90;

$endpoint = "https://api.bluelytics.com.ar/v2/evolution.json";
$cacheDir = __DIR__ . '/../cache';
if(!is_dir($cacheDir)) { @mkdir($cacheDir, 0777, true); }
$cacheFile = $cacheDir . "/evolution.json";
$ttl = 6 * 60 * 60; // 6 horas

function fetch_remote($url){
  $ctx = stream_context_create([
    'http' => ['timeout' => 12, 'ignore_errors' => true, 'header' => "User-Agent: Underc0de-DolarLanding/1.0\r\n"]
  ]);
  return @file_get_contents($url, false, $ctx);
}

// Cache
$needFetch = true;
if(file_exists($cacheFile) && (time() - filemtime($cacheFile) <= $ttl)){
  $raw = file_get_contents($cacheFile);
  if($raw !== false) $needFetch = false;
}
if($needFetch){
  $raw = fetch_remote($endpoint);
  if($raw !== false) file_put_contents($cacheFile, $raw);
  else if(file_exists($cacheFile)) $raw = file_get_contents($cacheFile);
}

if($raw === false){
  http_response_code(502);
  echo json_encode(['error'=>'No se pudo obtener histórico']);
  exit;
}

$arr = json_decode($raw, true);
if(!is_array($arr)){
  http_response_code(502);
  echo json_encode(['error'=>'Histórico inválido']);
  exit;
}

// Filtrar sólo entradas del "blue"
$blue = array_values(array_filter($arr, function($row){
  return isset($row['source']) && strtolower($row['source']) === 'blue';
}));

// Ordenar por fecha ascendente
usort($blue, function($a,$b){
  return strcmp($a['date'], $b['date']);
});

// Recortar por range (últimos N días)
$cutDate = (new DateTime())->modify("-{$range} days");
$out = [];
foreach($blue as $row){
  $date = DateTime::createFromFormat('Y-m-d', substr($row['date'],0,10));
  if(!$date) continue;
  if($date < $cutDate) continue;
  $out[] = [
    'fecha' => $date->format('Y-m-d'),
    'compra'=> isset($row['value_buy']) ? floatval($row['value_buy']) : null,
    'venta' => isset($row['value_sell']) ? floatval($row['value_sell']) : null
  ];
}

// En caso de vacío (API cambió), devolvemos array vacío
echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
