<?php
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
$valid = ['blue','oficial','bolsa','ccl','mayorista','tarjeta','cripto'];
$campo = isset($_GET['campo']) ? strtolower($_GET['campo']) : 'compra';
if(!in_array($campo, ['compra','venta','pb'])) $campo = 'compra';
if(!in_array($tipo, $valid)){ echo json_encode(['prev'=>null,'last'=>null]); exit; }

function http_get($url, $timeout=10){
  if(function_exists('curl_init')){
    $ch = curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>$timeout,CURLOPT_USERAGENT=>'Underc0de-DolarLanding/1.0']);
    $resp = curl_exec($ch); $http = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if($http>=200 && $http<300 && $resp!==false) return $resp; return false;
  } else {
    $ctx = stream_context_create(['http'=>['timeout'=>$timeout,'ignore_errors'=>true,'header'=>"User-Agent: Underc0de-DolarLanding/1.0\r\n"]]);
    $resp = @file_get_contents($url,false,$ctx); return $resp!==false ? $resp : false;
  }
}

$prev = null; $last = null;

if($tipo==='blue' || $tipo==='oficial'){
  $raw = http_get('https://api.bluelytics.com.ar/v2/evolution.json');
  if($raw!==false){
    $arr = json_decode($raw, true);
    if(is_array($arr)){
      $source = ($tipo==='blue') ? 'Blue' : 'Oficial';
      $vals = [];
      foreach($arr as $row){
        if(isset($row['source']) && strtolower($row['source'])===strtolower($source) && isset($row['value_buy'])){
          $vals[] = ['d'=>$row['date'] ?? '', 'c'=>floatval($row['value_buy'])];
        }
      }
      if(!empty($vals)){
        usort($vals, function($a,$b){ return strcmp($a['d'],$b['d']); });
        $n = count($vals);
        if($n>=2){ $prev=$vals[$n-2]['c']; $last=$vals[$n-1]['c']; }
        elseif($n==1){ $last=$vals[0]['c']; }
      }
    }
  }
}

// Fallback: server-side cache from proxy.php for all tipos
$file = dl_find_readable_file('cache_hist', "{$tipo}.json");
if(($prev===null || $last===null) && $file && file_exists($file)){
  $arr = json_decode(@file_get_contents($file), true);
  if(is_array($arr) && count($arr)>=1){
    $n = count($arr);
    $p = $n>=2 ? ($arr[$n-2][$campo] ?? null) : null;
    $l = ($arr[$n-1][$campo] ?? null);
    if($prev===null) $prev = ($p!==null) ? floatval($p) : null;
    if($last===null) $last = ($l!==null) ? floatval($l) : null;
  }
}


// --- Use the last two DISTINCT values for selected $campo ---
if (!function_exists('dl_get_last_two_distinct')) {
  function dl_get_last_two_distinct($list, $campo){
    if (!is_array($list)) return [null, null];
    $vals = [];
    foreach ($list as $row) {
      if (isset($row[$campo]) && is_numeric($row[$campo])) $vals[] = floatval($row[$campo]);
    }
    $n = count($vals);
    if ($n < 2) return [null, null];
    $lastVal = $vals[$n-1];
    for ($i = $n-2; $i >= 0; $i--) {
      if ($vals[$i] !== $lastVal) return [$vals[$i], $lastVal];
    }
    return [null, $lastVal]; // all equal
  }
}

// If prev/last missing or equal, try cache_hist first
if (($prev===null || $last===null) || $prev===$last) {
  $hf = dl_find_readable_file('cache_hist', "{$tipo}.json");
  if ($hf && file_exists($hf)) {
    $histArr = @json_decode(@file_get_contents($hf), true);
    if (is_array($histArr) && count($histArr)>=2) {
      list($p,$l) = dl_get_last_two_distinct($histArr, $campo);
      if ($p !== null && $l !== null) { $prev = $p; $last = $l; }
    }
  }
}

// If prev still missing, try cache_prev/<tipo>.json (last snapshot)
if(($prev===null || $prev===0) && $campo!=='pb'){
  $pf = dl_find_readable_file('cache_prev', "{$tipo}.json");
  if($pf && file_exists($pf)){
    $pd = json_decode(@file_get_contents($pf), true);
    if(is_array($pd) && isset($pd[$campo]) && is_numeric($pd[$campo])){
      $prev = floatval($pd[$campo]);
    }
  }
}


echo json_encode(['prev'=>$prev,'last'=>$last]);

// Fallback adicional: si falta 'prev', usar cache_prev/<tipo>.json (donde proxy.php guarda el último)
if(($prev===null || $prev===0) && $campo!=='pb'){
  $pf = dl_find_readable_file('cache_prev', "{$tipo}.json");
  if($pf && file_exists($pf)){
    $pd = json_decode(@file_get_contents($pf), true);
    if(is_array($pd) && isset($pd[$campo]) && is_numeric($pd[$campo])){
      $prev = floatval($pd[$campo]);
    }
  }
}
