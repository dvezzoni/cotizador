<?php
// index.php - Landing dark mode: Dólar en Argentina (Underc0de)
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Cotización del dólar en Argentina y criptos | Underc0de</title>
  <meta name="description" content="Landing en modo dark que muestra la cotización actual del dólar (Blue, Oficial, MEP, CCL, Cripto, Tarjeta) y un gráfico histórico del dólar blue. Actualizado al refrescar.">
  <meta property="og:title" content="Cotización del dólar en Argentina — Underc0de">
  <meta property="og:description" content="Blue, Oficial, MEP, CCL, Cripto, Tarjeta + gráfico histórico del dólar blue.">
  <meta property="og:type" content="website">
  <meta property="og:image" content="assets/img/og.png">
  <meta name="theme-color" content="#0b0f14">
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js" defer></script>
  <script src="assets/js/app.js" defer></script>
</head>
<body>
  <header class="container">
    <div class="brand">
      <div>
        <h1>Cotización Online</h1>
      </div>
    </div>
    <div class="updated">
      <span id="last-updated">Actualizado: —</span>
      <button id="btn-reload" class="btn btn-ghost" onclick="location.reload()">Refrescar</button>
    </div>
  </header>

  <main class="container">
    <section class="cards">
      <!-- Cards de cotizaciones -->
      <article class="card" data-tipo="oficial">
        <h3>Dólar Oficial</h3>
        <div class="row"><span>Compra:</span><strong id="oficial-compra" class="value">—</strong></div>
        <div class="row"><span>Venta:</span><strong id="oficial-venta" class="value">—</strong></div>
        <div class="row gap"><span>Variación:</span><span class="pct" id="oficial-pct" class="pct">—</span></div>
      </article>

      <article class="card" data-tipo="blue">
        <h3>Dólar Blue</h3>
        <div class="row"><span>Compra:</span><strong id="blue-compra" class="value">—</strong></div>
        <div class="row"><span>Venta:</span><strong id="blue-venta" class="value">—</strong></div>
        <div class="row gap"><span>Variación:</span><span class="pct" id="blue-pct" class="pct">—</span></div>
      </article>

      <article class="card" data-tipo="mayorista">
        <h3>Dólar Mayorista</h3>
        <div class="row"><span>Compra:</span><strong id="mayorista-compra" class="value">—</strong></div>
        <div class="row"><span>Venta:</span><strong id="mayorista-venta" class="value">—</strong></div>
      </article>

      <article class="card" data-tipo="bolsa">
        <h3>Dólar MEP (Bolsa)</h3>
        <div class="row"><span>Compra:</span><strong id="bolsa-compra" class="value">—</strong></div>
        <div class="row"><span>Venta:</span><strong id="bolsa-venta" class="value">—</strong></div>
      </article>

      <article class="card" data-tipo="ccl">
        <h3>Dólar CCL</h3>
        <div class="row"><span>Compra:</span><strong id="ccl-compra" class="value">—</strong></div>
        <div class="row"><span>Venta:</span><strong id="ccl-venta" class="value">—</strong></div>
      </article>

      <article class="card" data-tipo="cripto">
        <h3>Dólar Cripto</h3>
        <div class="row"><span>Compra:</span><strong id="cripto-compra" class="value">—</strong></div>
        <div class="row"><span>Venta:</span><strong id="cripto-venta" class="value">—</strong></div>
      </article>

      <article class="card" data-tipo="tarjeta">
        <h3>Dólar Tarjeta</h3>
        <div class="row"><span>Compra:</span><strong id="tarjeta-compra" class="value">—</strong></div>
        <div class="row"><span>Venta:</span><strong id="tarjeta-venta" class="value">—</strong></div>
      </article>
    
      <article class="card" data-card="riesgo-pais">
        <h3>Riesgo País</h3>
        <div class="row"><span>Valor:</span><strong id="riesgo-valor" class="value">—</strong></div>
        <div class="row gap"><span>Variación:</span><span class="pct" id="riesgo-pct" class="pct">—</span></div>
        </article>

    </section><section class="tools">
        </div>
      </div>
      <br />
      <div class="tool card">
        <h3>Evolución del dólar Blue</h3>
        <div class="controls">
          <label>Rango
            <select id="range">
              <option value="7">7 días</option>
              <option value="15">15 días</option>
              <option value="30">1 mes</option>
              <option value="90" selected>3 meses</option>
              <option value="365">1 año</option>
              <option value="1095">3 años</option>
              <option value="1825">5 años</option>
              <option value="3650">10 años</option>
            </select>
          </label>
        </div>
        <div class="chart-wrap"><canvas id="blueChart"></canvas></div>
      </div>
    </section>
  
    <!-- Sección de criptomonedas (USD) -->
    <section class="cards" id="crypto-cards">
      <article class="card" data-coin="btc">
        <h3>Bitcoin</h3>
        <div class="row"><span>Precio:</span><strong id="btc-usd" class="value">—</strong></div>
        <div class="row gap"><span>Variación:</span><span class="pct" id="btc-pct">—</span></div>
      </article>
      <article class="card" data-coin="eth">
        <h3>ETH</h3>
        <div class="row"><span>Precio:</span><strong id="eth-usd" class="value">—</strong></div>
        <div class="row gap"><span>Variación:</span><span class="pct" id="eth-pct">—</span></div>
      </article>
      <article class="card" data-coin="usdt">
        <h3>USDT</h3>
        <div class="row"><span>Precio:</span><strong id="usdt-usd" class="value">—</strong></div>
        <div class="row gap"><span>Variación:</span><span class="pct" id="usdt-pct">—</span></div>
      </article>
      <article class="card" data-coin="usdc">
        <h3>USDC</h3>
        <div class="row"><span>Precio:</span><strong id="usdc-usd" class="value">—</strong></div>
        <div class="row gap"><span>Variación:</span><span class="pct" id="usdc-pct">—</span></div>
      </article>
    </section>

  </main>

  <footer class="container footer">
    <p class="muted">Powered by <strong>Underc0de</strong></p>
  </footer>
</body>
</html>
