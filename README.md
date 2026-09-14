# AniWay (PHP)

Calculadora de gastos de viaje con login, historial por cuenta (JSON) y rutas limitadas a **España**.

## Estructura

```
/AniWay
├── index.php              # App principal (requiere login)
├── login.php
├── register.php
├── logout.php
├── config.php
├── api/
│   ├── route.php          # Distancia (solo España)
│   └── trips.php          # Calcular + guardar viajes
├── includes/
│   ├── auth.php
│   ├── spain.php
│   └── trips.php
├── data/
│   ├── users.json         # Cuentas (local, no versionado)
│   └── trips/{userId}.json
└── static/
    ├── app.js
    ├── style.css
    ├── logo.png
    └── aniway_favicon.ico
```

## Requisitos

- PHP 8+ con extensiones `curl` y `json`
- Clave de [LocationIQ](https://locationiq.com/)

## Arranque local

```bash
# Windows (PowerShell)
$env:LOCATIONIQ_KEY="tu_clave"
php -S localhost:8000

# Linux / macOS
export LOCATIONIQ_KEY="tu_clave"
php -S localhost:8000
```

Abre `http://localhost:8000/login.php`, regístrate y calcula viajes.

## Qué hace

- **Login / registro**: cuentas en `data/users.json` (contraseñas hasheadas).
- **Últimos viajes**: cada cálculo se guarda en `data/trips/{id}.json` (máx. 10 por cuenta).
- **API / mapa**: solo coordenadas dentro de España (península, Baleares y Canarias).
