# AniWay (PHP)

Calculadora de gastos de viaje. Las rutas se limitan a **España**. No guarda cuentas ni historial.

## Estructura

```
/AniWay
├── index.php              # Calculadora
├── config.php
├── api/
│   ├── route.php          # Distancia (solo España)
│   └── trips.php          # Calcular el reparto
├── includes/
│   ├── spain.php
│   └── trips.php
└── static/
    ├── app.js
    ├── style.css
    ├── logo.png
    └── aniway_favicon.ico
```

## Requisitos

- PHP 8+ con extensiones `curl` y `json`
- Clave de [LocationIQ](https://locationiq.com/)

## Clave LocationIQ

En el servidor, en la raíz del proyecto:

```bash
cp .env.example .env
nano .env   # LOCATIONIQ_KEY=tu_clave_real
```

Apache no suele pasar variables de entorno a PHP; por eso se usa `.env`.

## Arranque local

```bash
# Windows (PowerShell)
$env:LOCATIONIQ_KEY="tu_clave"
php -S localhost:8000

# Linux / macOS
export LOCATIONIQ_KEY="tu_clave"
php -S localhost:8000
```

Abre `http://localhost:8000/` y calcula viajes.

Con XAMPP: `http://localhost/AniWay/`.

## Qué hace

- **Reparto**: calcula el coste por persona a partir de los kilómetros de cada tramo.
- **API / mapa**: solo coordenadas dentro de España (península, Baleares y Canarias).
