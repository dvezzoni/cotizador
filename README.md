
# Cotización del dólar — Landing Dark (PHP/HTML/CSS)

Landing minimalista en **modo dark** que muestra:
- Dólar **Oficial**, **Blue**, **MEP/Bolsa**, **CCL**, **Cripto** y **Tarjeta** (compra/venta).
- **Brecha** vs. Oficial (para Blue/MEP/CCL/Cripto/Tarjeta).
- **Conversor rápido** ARS ↔ USD (usa *venta* del tipo seleccionado).
- **Gráfico histórico del Dólar Blue** con selector de rango (7, 15, 30, 90, 365, 1095, 1825, 3650 días).
- **Última actualización visible** (se actualiza al refrescar la página).
- **Mini footer** con marca **Underc0de**.

> **Stack**: PHP sin Composer (vanilla), HTML, CSS, JS + Chart.js (CDN).
>
> **APIs**: [DolarAPI](https://dolarapi.com/) para cotizaciones actuales y [Bluelytics](https://api.bluelytics.com.ar/) para histórico del Blue.
>
> **Hosting**: probado en XAMPP local; apto para subir a DonWeb u otros hostings compartidos.

---

## ▶️ Cómo ejecutar (local con XAMPP)

1. Copiá el folder `dolar-landing` completo dentro de tu `htdocs` (ej: `C:\xampp\htdocs\dolar-landing`).
2. Iniciá Apache (no hace falta MySQL).
3. Abrí en el navegador: `http://localhost/dolar-landing/`.
4. Para ver histórico, el sitio hace requests desde `api/historico-blue.php` hacia Bluelytics; requiere salida a Internet.

> Si tu hosting requiere permisos de escritura, asegurate de que la carpeta `cache/` sea escribible (se usa para cachear respuestas).

---

## 📁 Estructura

```
dolar-landing/
├─ index.php
├─ api/
│  ├─ proxy.php              # Proxy a DolarAPI (actual)
│  └─ historico-blue.php     # Histórico Blue (Bluelytics)
├─ assets/
│  ├─ css/style.css
│  ├─ js/app.js
│  └─ img/{logo.svg, og.png}
└─ cache/                    # Archivos de cache (creado en runtime)
```

---

## 🧩 Personalización

- **TTL cache** actual: 60s para cotizaciones (api/proxy.php) y 6h para histórico (api/historico-blue.php).
- **Rango por defecto** del gráfico: 90 días (3 meses).
- **Brecha**: `(venta_tipo / venta_oficial - 1) * 100` (se muestra con 2 decimales).
- **Formato**: `es-AR` con separador de miles `.` y decimales `,`.

---

## 🔒 Notas técnicas

- Se usa un **proxy PHP** para evitar CORS y unificar el formato JSON devuelto por las APIs.
- Si la API externa falla, el proxy intenta servir el **cache más reciente** si existe.
- No requiere Composer ni extensiones raras: con `allow_url_fopen` habilitado es suficiente (alternativamente podés reemplazar `file_get_contents` por cURL).

---

## 🧪 Endpoints internos

- `GET /api/proxy.php?tipo=blue|oficial|bolsa|ccl|cripto|tarjeta`
  - Respuesta: `{ tipo, moneda:"ARS", compra, venta, fuente, fecha }`
- `GET /api/historico-blue.php?range=N_DIAS`
  - Respuesta: `[{ fecha:"YYYY-MM-DD", compra, venta }]`

---

Hecho con 💙 para **Underc0de**.

