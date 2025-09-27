<?php
// api/proxy.php - Proxy y normalizador para DolarAPI
// Uso: /api/proxy.php?tipo=blue|oficial|bolsa|ccl|cripto|tarjeta|mayorista
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// --- Helpers de almacenamiento compatibles con hosting compartido ---
if (!function_exists('dl_ensure_dir')) {
  function dl_ensure_dir($dir) {
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return is_dir($dir) && is_writable($dir);
  }
}
if (!function_exists('dl_dir_candidates')) {
  function dl_dir_candidates($sub) {
    $cands = [
      __DIR__ . '/../' . $sub,
      __DIR__ . '/../storage/' . $sub,
      sys_get_temp_dir() . '/dolar-landing/' . $sub
    ];
    $ok = [];
    foreach ($cands as $d) {
      if (dl_ensure_dir($d)) $ok[] = $d;
    }
    return $ok;
  }
}
if (!function_exists('dl_pick_writable_dir')) {
  function dl_pick_writable_dir($sub) {
    $c = dl_dir_candidates($sub);
    return count($c) ? $c[0] : null;
  }
}
if (!function_exists('dl_find_readable_file')) {
  function dl_find_readable_file($sub, $filename) {
    $cands = dl_dir_candidates($sub);
    foreach ($cands as $d) {
      $p = rtrim($d, '/\\') . '/' . $filename;
      if (file_exists($p)) return $p;
    }
    return null;
  }
}


$tipo = isset($_GET['tipo']) ? strtolower($_GET['tipo']) : 'blue';
$valid = ['blue','oficial','bolsa','ccl','cripto','tarjeta','mayorista'];
if(!in_array($tipo, $valid)){
  http_response_code(400);
  // Append to server-side history (cache_hist) and include previous values (cache_prev)
$histDir = dl_pick_writable_dir('cache_hist') ?: __DIR__ . '/../cache_hist';
if(!is_dir($histDir)) @mkdir($histDir, 0777, true);
$histFile = $histDir . "/{$tipo}.json";
$hist = [];
if(file_exists($histFile)){
  $old = json_decode(@file_get_contents($histFile), true);
  if(is_array($old)) $hist = $old;
}
$entry = ['ts'=>time(), 'compra'=>$compra, 'venta'=>$venta];
$last = end($hist);
// Snapshot del valor previo ANTES de agregar el actual
$prevSnapshot_compra = is_array($last) && isset($last['compra']) ? floatval($last['compra']) : null;
$prevSnapshot_venta  = is_array($last) && isset($last['venta'])  ? floatval($last['venta'])  : null;

if(!is_array($last) || floatval($last['compra']??-1)!==floatval($compra) || floatval($last['venta']??-1)!==floatval($venta)){
  $hist[] = $entry;
  if(count($hist)>500) $hist = array_slice($hist, -500);
  @file_put_contents($histFile, json_encode($hist));
}

$prevDir = dl_pick_writable_dir('cache_prev') ?: __DIR__ . '/../cache_prev';
if(!is_dir($prevDir)) @mkdir($prevDir, 0777, true);
$prevFile = $prevDir . "/{$tipo}.json";
$prev_compra = null; $prev_venta = null;
if(file_exists($prevFile)){
  $prevData = json_decode(@file_get_contents($prevFile), true);
  if(is_array($prevData)){
    if(isset($prevData['compra'])) $prev_compra = floatval($prevData['compra']);
    if(isset($prevData['venta']))  $prev_venta  = floatval($prevData['venta']);
  }
}
@file_put_contents($prevFile, json_encode(['compra'=>$prevSnapshot_compra, 'venta'=>$prevSnapshot_venta, 'ts'=>time()]));

echo json_encode(['error'=>'tipo inválido']);
  exit;
}

$map = [
  'blue'      => 'blue',
  'oficial'   => 'oficial',
  'bolsa'     => 'bolsa',
  'ccl'       => 'contadoconliqui',
  'cripto'    => 'cripto',
  'tarjeta'   => 'tarjeta',
  'mayorista' => 'mayorista'
];

$endpoint = 'https://dolarapi.com/v1/dolares/' . $map[$tipo];

$cacheDir = dl_pick_writable_dir('cache_api') ?: (__DIR__ . '/.cache');
if(!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
$cacheFile = $cacheDir . '/' . $tipo . '.json';
$cacheTTL = 60; // segundos
$forceNoCache = isset($_GET['nocache']) && $_GET['nocache'] === '1';

function fetch_remote($url){
  if(function_exists('curl_init')){
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_CONNECTTIMEOUT => 5,
      CURLOPT_TIMEOUT => 8,
      CURLOPT_USERAGENT => 'DolarLanding/1.0'
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if($http >= 200 && $http < 300 && $resp !== false) return $resp;
    return false;
  } else {
    $ctx = stream_context_create(['http'=>['method'=>'GET','timeout'=>8,'header'=>"User-Agent: DolarLanding/1.0\r\n"]]);
    $resp = @file_get_contents($url,false,$ctx);
    return $resp ?: false;
  }
}

// Cache válido
if(!$forceNoCache && file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTTL)){
  $raw = file_get_contents($cacheFile);
  if($raw !== false){
    echo $raw;
    exit;
  }
}

// Fetch remoto
$raw = fetch_remote($endpoint);
if($raw === false){
  // Si hay cache viejo, servimos eso
  if(file_exists($cacheFile)){
    echo file_get_contents($cacheFile);
    exit;
  }
  http_response_code(502);
  // Append to server-side history (cache_hist) and include previous values (cache_prev)
$histDir = dl_pick_writable_dir('cache_hist') ?: __DIR__ . '/../cache_hist';
if(!is_dir($histDir)) @mkdir($histDir, 0777, true);
$histFile = $histDir . "/{$tipo}.json";
$hist = [];
if(file_exists($histFile)){
  $old = json_decode(@file_get_contents($histFile), true);
  if(is_array($old)) $hist = $old;
}
$entry = ['ts'=>time(), 'compra'=>$compra, 'venta'=>$venta];
$last = end($hist);
// Snapshot del valor previo ANTES de agregar el actual
$prevSnapshot_compra = is_array($last) && isset($last['compra']) ? floatval($last['compra']) : null;
$prevSnapshot_venta  = is_array($last) && isset($last['venta'])  ? floatval($last['venta'])  : null;

if(!is_array($last) || floatval($last['compra']??-1)!==floatval($compra) || floatval($last['venta']??-1)!==floatval($venta)){
  $hist[] = $entry;
  if(count($hist)>500) $hist = array_slice($hist, -500);
  @file_put_contents($histFile, json_encode($hist));
}

$prevDir = dl_pick_writable_dir('cache_prev') ?: __DIR__ . '/../cache_prev';
if(!is_dir($prevDir)) @mkdir($prevDir, 0777, true);
$prevFile = $prevDir . "/{$tipo}.json";
$prev_compra = null; $prev_venta = null;
if(file_exists($prevFile)){
  $prevData = json_decode(@file_get_contents($prevFile), true);
  if(is_array($prevData)){
    if(isset($prevData['compra'])) $prev_compra = floatval($prevData['compra']);
    if(isset($prevData['venta']))  $prev_venta  = floatval($prevData['venta']);
  }
}
@file_put_contents($prevFile, json_encode(['compra'=>$prevSnapshot_compra, 'venta'=>$prevSnapshot_venta, 'ts'=>time()]));

echo json_encode(['error'=>'fuente no disponible']);
  exit;
}

$data = json_decode($raw, true);
if(!is_array($data)){
  http_response_code(502);
  // Append to server-side history (cache_hist) and include previous values (cache_prev)
$histDir = dl_pick_writable_dir('cache_hist') ?: __DIR__ . '/../cache_hist';
if(!is_dir($histDir)) @mkdir($histDir, 0777, true);
$histFile = $histDir . "/{$tipo}.json";
$hist = [];
if(file_exists($histFile)){
  $old = json_decode(@file_get_contents($histFile), true);
  if(is_array($old)) $hist = $old;
}
$entry = ['ts'=>time(), 'compra'=>$compra, 'venta'=>$venta];
$last = end($hist);
// Snapshot del valor previo ANTES de agregar el actual
$prevSnapshot_compra = is_array($last) && isset($last['compra']) ? floatval($last['compra']) : null;
$prevSnapshot_venta  = is_array($last) && isset($last['venta'])  ? floatval($last['venta'])  : null;

if(!is_array($last) || floatval($last['compra']??-1)!==floatval($compra) || floatval($last['venta']??-1)!==floatval($venta)){
  $hist[] = $entry;
  if(count($hist)>500) $hist = array_slice($hist, -500);
  @file_put_contents($histFile, json_encode($hist));
}

$prevDir = dl_pick_writable_dir('cache_prev') ?: __DIR__ . '/../cache_prev';
if(!is_dir($prevDir)) @mkdir($prevDir, 0777, true);
$prevFile = $prevDir . "/{$tipo}.json";
$prev_compra = null; $prev_venta = null;
if(file_exists($prevFile)){
  $prevData = json_decode(@file_get_contents($prevFile), true);
  if(is_array($prevData)){
    if(isset($prevData['compra'])) $prev_compra = floatval($prevData['compra']);
    if(isset($prevData['venta']))  $prev_venta  = floatval($prevData['venta']);
  }
}
@file_put_contents($prevFile, json_encode(['compra'=>$prevSnapshot_compra, 'venta'=>$prevSnapshot_venta, 'ts'=>time()]));

echo json_encode(['error'=>'respuesta inválida']);
  exit;
}

$out = [
  'tipo'   => $tipo,
  'moneda' => 'ARS',
  'compra' => isset($data['compra']) ? (float)$data['compra'] : (isset($data['buy']) ? (float)$data['buy'] : null),
  'venta'  => isset($data['venta']) ? (float)$data['venta'] : (isset($data['sell']) ? (float)$data['sell'] : null),
  'fuente' => 'dolarapi.com',
  'fecha'  => isset($data['fechaActualizacion']) ? $data['fechaActualizacion'] : (isset($data['fecha']) ? $data['fecha'] : date('c'))
];

file_put_contents($cacheFile, json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
