<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

/**
 * Minimal HTTP GET with curl fallback to file_get_contents
 */
function http_get(string $url, int $timeout = 10){
  if (function_exists('curl_init')) {
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
    if ($code >= 200 && $code < 300 && $resp !== false) return $resp;
    return false;
  } else {
    $ctx = stream_context_create([
      'http' => [
        'timeout' => $timeout,
        'ignore_errors' => true,
        'header' => "User-Agent: Underc0de-DolarLanding/1.0\r\n"
      ]
    ]);
    $resp = @file_get_contents($url, false, $ctx);
    return ($resp !== false) ? $resp : false;
  }
}

/**
 * Source of truth: ArgentinaDatos /indices/riesgo-pais/ultimo
 * Returns payload like: { "fecha": "YYYY-MM-DD", "valor": 977 }
 */
$ultimoUrl = 'https://api.argentinadatos.com/v1/finanzas/indices/riesgo-pais/ultimo';
$raw = http_get($ultimoUrl);
if ($raw === false) {
  http_response_code(502);
  echo json_encode(['error' => 'No se pudo obtener /ultimo de ArgentinaDatos']);
  exit;
}

$j = json_decode($raw, true);
if (!is_array($j) || !isset($j['valor'])) {
  http_response_code(502);
  echo json_encode(['error' => 'Respuesta inválida de ArgentinaDatos']);
  exit;
}

$valor = floatval($j['valor']);
$fecha = isset($j['fecha']) ? strval($j['fecha']) : null;

// Response for frontend consumption
echo json_encode([
  'indicador' => 'riesgo_pais',
  'unidad' => 'pb',
  'valor' => $valor,
  'fecha' => $fecha,
  'fuente' => 'api.argentinadatos.com/finanzas/indices/riesgo-pais/ultimo'
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
