
'use strict';
const $ = (q)=>document.querySelector(q);
const $$ = (q)=>document.querySelectorAll(q);
const tipos = ['oficial','blue','bolsa','ccl','cripto','tarjeta','mayorista'];
const fmtARS = new Intl.NumberFormat('es-AR',{style:'currency',currency:'ARS',minimumFractionDigits:2});
const fmtPct = new Intl.NumberFormat('es-AR',{minimumFractionDigits:2, maximumFractionDigits:2});
let cotizaciones = {};

// --- Helpers ---
function isDebug(){ try{ return new URLSearchParams(location.search).get('debug') === '1'; }catch(_){ return false; } }
function log(){ if(isDebug()) console.log.apply(console, ['[DEBUG]'].concat([].slice.call(arguments))); }

// Build URL relative to current page + nocache propagation + cache-bust
function buildURL(url){
  try{
    const u = new URL(url, location.href);
    const pageNocache = new URLSearchParams(location.search).get('nocache') === '1';
    if(pageNocache) u.searchParams.set('nocache','1');
    u.searchParams.set('_ts', String(Date.now()));
    return u.toString();
  }catch(_){
    return url + (url.includes('?')?'&':'?') + '_ts='+Date.now();
  }
}

// Skeletons + fade-in
function addSkeletons(){
  $$('.value, .pct').forEach(el=>{ el.classList.add('skeleton'); el.textContent=''; });
}
function paint(el, text){
  if(!el) return;
  el.textContent = text;
  el.classList.remove('skeleton');
  el.classList.add('fade-in');
  setTimeout(()=> el.classList.remove('fade-in'), 250);
}

// Fetch JSON (resilient)
async function fetchJSON(url){
  const finalUrl = buildURL(url);
  const r = await fetch(finalUrl, {cache:'no-store'});
  log('fetch', finalUrl, r.status);
  if(!r.ok) throw new Error('HTTP '+r.status);
  const data = await r.json();
  log('json', finalUrl, data);
  return data;
}

// Variación con flecha dentro del %
// Regla: si no hay previo -> "—"; si % redondea a 0.00 pero |ΔARS| >= 1 -> mostrar 0.01% con flecha
function setTrend(tipo, current){
  try{
    const key = 'prev_'+tipo;
    const prevStr = localStorage.getItem(key);
    const prev = prevStr!=null ? parseFloat(prevStr) : null;
    const el = document.getElementById(`${tipo}-pct`);
    let cls = 'flat', sym = '', txt = '—';

    if(prev!=null && isFinite(prev) && prev>0 && current!=null && isFinite(current)){
      const diff = current - prev;
      let pct = (diff/prev)*100;
      const pctRounded = Math.round(pct * 100) / 100;
      if(pctRounded === 0 && Math.abs(diff) >= 1){
        pct = diff>0 ? 0.01 : -0.01;
      }else{
        pct = pctRounded;
      }
      if(pct > 0){ cls='up'; sym='▲'; }
      else if(pct < 0){ cls='down'; sym='▼'; }
      else { cls='flat'; sym=''; }
      txt = (cls==='flat') ? fmtPct.format(0)+'%' : `${sym} ${fmtPct.format(Math.abs(pct))}%`;
    }

    if(el){ el.className = 'pct '+cls; paint(el, txt); }
    if(current!=null && isFinite(current)) localStorage.setItem(key, String(current));
  }catch(e){ log('setTrend error', tipo, e); }
}


// --- Injected: computes % variation using server-side historico.php (prev/last) ---
// campo: 'compra' | 'venta'
async function setTrendFromHistorico(tipo, campo){
  try{
    campo = (campo==='venta') ? 'venta' : 'compra';
    const url = `./api/historico.php?tipo=${tipo}&campo=${campo}`;
    const resp = await fetch(buildURL(url), { cache:'no-store' });
    if(!resp.ok) throw new Error('HTTP '+resp.status);
    const data = await resp.json();
    const prev = Number(data?.prev);
    const last = Number(data?.last);
    const elPct = document.getElementById(`${tipo}-pct`);
    if(isFinite(prev) && prev !== 0 && isFinite(last)){
      const pct = ((last - prev) / prev) * 100;
      const pctRounded = Math.round(pct * 100) / 100;
      if(elPct){
        elPct.textContent = (pctRounded>=0? '+' : '') + fmtPct.format(pctRounded) + '%';
        elPct.classList.remove('up','down','flat');
        elPct.classList.add(pctRounded>0?'up':(pctRounded<0?'down':'flat'));
        elPct.classList.remove('skeleton');
      }
    } else {
      // If server couldn't provide two valid points, fallback to client trend
      setTrend(tipo, Number(campo==='venta' ? cotizaciones?.[tipo]?.venta : cotizaciones?.[tipo]?.compra));
    }
  }catch(e){
    log('setTrendFromHistorico error', tipo, e);
    setTrend(tipo, Number(campo==='venta' ? cotizaciones?.[tipo]?.venta : cotizaciones?.[tipo]?.compra));
  }
}

// Carga de cotizaciones

async function cargarCotizaciones(){
  try{
    const lu = $('#last-updated'); if(lu) lu.textContent = 'Actualizado: '+new Date().toLocaleString('es-AR');
    for(const t of tipos){
      const elc = document.getElementById(`${t}-compra`);
      const elv = document.getElementById(`${t}-venta`);
      try{
        // 1) Obtener histórico ANTES para capturar el 'last' previo al fetch actual
        const data = await fetchJSON('./api/proxy.php?tipo='+t);
        cotizaciones[t] = data || {};
        const buy = (data && (data.compra ?? data.buy)) ?? null;
        const sell = (data && (data.venta  ?? data.sell)) ?? null;
        paint(elc, (buy!=null && isFinite(buy)) ? fmtARS.format(Number(buy)) : '—');
        paint(elv, (sell!=null && isFinite(sell)) ? fmtARS.format(Number(sell)) : '—');
        await setTrendFromHistorico(t, 'venta');

      }catch(e){
        log('cargarCotizaciones error', t, e);
        paint(elc, '—'); paint(elv, '—');
      }
    }
  }catch(e){ log('cargarCotizaciones fatal', e); }
}

// Conversor (si existe en DOM)
function getVenta(tipo){ return cotizaciones?.[tipo]?.venta ?? null; }
function bindConversor(){
  const tipoSel = $('#conv-tipo'), arsIn = $('#conv-ars'), usdIn = $('#conv-usd');
  const btnA2U = $('#btn-ars-to-usd'), btnU2A = $('#btn-usd-to-ars');
  if(!tipoSel || !arsIn || !usdIn || !btnA2U || !btnU2A) return;
  btnA2U.addEventListener('click', ()=>{ const v=getVenta(tipoSel.value), ars=parseFloat(arsIn.value||'0'); usdIn.value = (!v||!ars)?'':(ars/v).toFixed(2); });
  btnU2A.addEventListener('click', ()=>{ const v=getVenta(tipoSel.value), usd=parseFloat(usdIn.value||'0'); arsIn.value = (!v||!usd)?'':(usd*v).toFixed(2); });
}

// Histórico Blue (si existe canvas y Chart)
let blueChart;
async function cargarHistorico(dias=90){
  const canvas = document.getElementById('blueChart');
  if(!canvas || typeof Chart==='undefined') return;
  try{
    const data = await fetchJSON('./api/historico-blue.php?range='+dias);
    const labels = data.map(d=>d.fecha);
    const serie = data.map(d=>d.venta);
    const serieBuy = data.map(d=>d.compra);
    if(blueChart) blueChart.destroy();
    const ctx = canvas.getContext('2d');
    blueChart = new Chart(ctx, {
      type:'line',
      data:{ labels, datasets:[
        {label:'Venta (Blue)', data:serie, borderWidth:2, tension:.25, pointRadius:0},
        {label:'Compra (Blue)', data:serieBuy, borderWidth:2, borderDash:[4,4], tension:.25, pointRadius:0}
      ]},
      options:{
        responsive:true, maintainAspectRatio:false, interaction:{mode:'index',intersect:false},
        plugins:{ legend:{labels:{boxWidth:12}}}
      }
    });
  }catch(e){ log('historico error', e); }
}
function bindRange(){
  const range = $('#range'); if(!range) return;
  range.addEventListener('change', ()=>{ const v = parseInt(range.value,10)||90; cargarHistorico(v); });
}

// Riesgo País
async function cargarRiesgoPais(){
  try{
    // 1) Traer el valor actual desde nuestro endpoint (que a su vez consulta ArgentinaDatos)
    const data = await fetchJSON('./api/riesgo-pais.php');
    const val = (data && data.valor!=null) ? Number(data.valor) : null;
    const elv = document.getElementById('riesgo-valor');
    paint(elv, (val!=null && isFinite(val)) ? new Intl.NumberFormat('es-AR').format(val)+' pb' : '—');

    // 2) Variación: usar histórico del servidor para obtener prev/last confiables
    try{
      const resp = await fetch(buildURL('./api/historico-riesgo.php'), { cache: 'no-store' });
      if(!resp.ok) throw new Error('HTTP '+resp.status);
      const hist = await resp.json();
      const prev = Number(hist?.prev);
      const last = Number(hist?.last);
      const elPct = document.getElementById('riesgo-pct');
      if(isFinite(prev) && prev!==0 && isFinite(last)){
        const pct = ((last - prev) / prev) * 100;
        const pctRounded = Math.round(pct * 100) / 100;
        if(elPct){
          elPct.textContent = (pctRounded>=0? '+' : '') + fmtPct.format(pctRounded) + '%';
          elPct.classList.remove('up','down','flat','skeleton');
          elPct.classList.add(pctRounded>0?'up':(pctRounded<0?'down':'flat'));
        }
      } else {
        // Fallback a localStorage si por alguna razón no hay histórico
        setTrend('riesgo', val);
      }
    }catch(err){
      log('riesgo-historico error', err);
      setTrend('riesgo', val);
    }
  }catch(e){ log('riesgo-pais error', e); }
}


// --- Crypto (BTC, ETH, USDT, USDC) in USD ---
const fmtUSD = new Intl.NumberFormat('en-US',{style:'currency',currency:'USD',minimumFractionDigits:2});
let prevCrypto = {};

async function fetchCrypto(){
  try{
    const url = 'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin,ethereum,tether,usd-coin&vs_currencies=usd&include_24hr_change=true';
    const res = await fetch(url, { cache:'no-store' });
    if(!res.ok) throw new Error('HTTP '+res.status);
    const data = await res.json();

    const map = {
      btc: { id:'bitcoin', elPrice:'#btc-usd', elPct:'#btc-pct' },
      eth: { id:'ethereum', elPrice:'#eth-usd', elPct:'#eth-pct' },
      usdt:{ id:'tether', elPrice:'#usdt-usd', elPct:'#usdt-pct' },
      usdc:{ id:'usd-coin', elPrice:'#usdc-usd', elPct:'#usdc-pct' }
    };

    Object.keys(map).forEach(k=>{
      const co = map[k];
      const rec = data[co.id];
      const price = rec?.usd;
      const change = rec?.usd_24h_change;
      // Price
      paint($(co.elPrice), (price!=null && isFinite(price)) ? fmtUSD.format(price) : '—');

      // Change badge
      if(change==null || !isFinite(change)){
        paint($(co.elPct), '—'); $(co.elPct)?.classList.remove('up','down','flat');
      }else{
        const pct = (Math.abs(change) < 0.005) ? 0 : change; // treat tiny as flat
        const el = $(co.elPct);
        el.textContent = (pct>=0? '+' : '') + fmtPct.format(pct) + '%';
        el.classList.remove('up','down','flat');
        el.classList.add(pct>0?'up':pct<0?'down':'flat');
      }

      // Up/Down trend marker relative to previous poll
      const prev = prevCrypto[k];
      const elPrice = $(co.elPrice);
      if(prev!=null && price!=null){
        const trend = price>prev ? 'up' : (price<prev ? 'down':'flat');
        setInlineTrend(elPrice, trend);
      }else{
        setInlineTrend(elPrice, 'flat');
      }
      if(price!=null) prevCrypto[k]=price;
    });
  }catch(e){
    log('crypto error', e);
  }
}

// Helpers to show inline ▲▼ next to strong.value (without altering layout)
function setInlineTrend(strongEl, trend){
  try{
    if(!strongEl) return;
    // Remove existing
    const sib = strongEl.parentElement?.querySelector('.trend');
    if(sib) sib.remove();
    const span = document.createElement('span');
    span.className = 'trend ' + (trend||'flat');
    span.textContent = trend==='up' ? '▲' : (trend==='down' ? '▼' : '—');
    strongEl.after(span);
  }catch(_){}
}

// Init
document.addEventListener('DOMContentLoaded', async ()=>{
  try{
    addSkeletons();
    bindConversor();
    bindRange();
    await cargarCotizaciones();
    await cargarHistorico(parseInt($('#range')?.value||'90',10));
    await cargarRiesgoPais();
    await fetchCrypto();
    setInterval(fetchCrypto, 60000);
}catch(e){ log('init fatal', e); }
});
