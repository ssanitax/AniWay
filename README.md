# AniWay (PHP)

Calculadora de gastos de viaje en coche. Las rutas se limitan a **España** (península, Baleares y Canarias). No guarda cuentas ni historial.

## Requisitos

- PHP 8.0 o superior, con las extensiones `curl`, `openssl` y `json` (en XAMPP suelen venir activadas)
- Una clave gratuita de [LocationIQ](https://locationiq.com/) (rutas por carretera)
- Las direcciones con número de portal se resuelven con [CartoCiudad](https://www.cartociudad.es/) (IGN); no hace falta clave

## Instalación en local

### 1. Clonar o copiar el proyecto

```bash
git clone https://github.com/TU_USUARIO/AniWay.git
cd AniWay
```

O descarga el ZIP del repositorio y descomprímelo donde quieras.

### 2. Crear la clave LocationIQ

1. Regístrate en [https://locationiq.com/](https://locationiq.com/)
2. En el panel, copia tu **Access Token** (empieza por `pk.`)

### 3. Configurar el `.env`

En la raíz del proyecto (junto a `index.php`):

```bash
# Windows (PowerShell)
Copy-Item .env.example .env

# Linux / macOS
cp .env.example .env
```

Abre `.env` y pon tu clave:

```
LOCATIONIQ_KEY=pk.tu_clave_real
```

No subas el archivo `.env` a Git (ya está en `.gitignore`).

### 4. Arrancar el servidor

#### Opción A — PHP incluido (rápido)

Necesitas tener `php` en el PATH.

```bash
# Windows (PowerShell), desde la carpeta del proyecto
php -S localhost:8000

# Linux / macOS
php -S localhost:8000
```

Abre en el navegador: [http://localhost:8000/](http://localhost:8000/)

#### Opción B — XAMPP (recomendado en Windows)

1. Instala [XAMPP](https://www.apachefriends.org/) (Apache + PHP)
2. Copia o enlaza el proyecto dentro de `htdocs`, por ejemplo:
   - `C:\xampp\htdocs\AniWay`
3. En el panel de XAMPP, arranca **Apache**
4. Abre: [http://localhost/AniWay/](http://localhost/AniWay/)

Enlace simbólico (opcional, para no duplicar la carpeta):

```powershell
# PowerShell como administrador
New-Item -ItemType Junction -Path "C:\xampp\htdocs\AniWay" -Target "C:\ruta\a\AniWay"
```

## Comprobar que funciona

1. Abre la calculadora en el navegador
2. Añade un tramo de ida
3. Escribe una dirección con número (por ejemplo `Calle de Alcalá 42, Madrid`) o haz clic en el mapa
4. Al marcar origen y destino debe aparecer la distancia en km (ruta por carretera)

Si falla la ruta, revisa:

- Que exista `.env` en la raíz y `LOCATIONIQ_KEY` esté bien escrita
- Que PHP tenga `curl` activo (`php -m` debe listar `curl`)
- En XAMPP: que Apache esté en marcha y la URL sea la de `htdocs`

## Estructura

```
/AniWay
├── index.php              # Calculadora
├── config.php             # Carga .env y límites de España
├── .env.example           # Plantilla de la clave
├── api/
│   ├── geocode.php        # Direcciones (CartoCiudad + LocationIQ)
│   ├── route.php          # Ruta en coche (LocationIQ)
│   └── trips.php          # Reparto del coste
├── includes/
│   ├── spain.php
│   └── trips.php
└── static/
    ├── app.js
    ├── style.css
    ├── logo.png
    └── aniway_favicon.ico
```

## Qué hace

- **Mapa y direcciones**: elige origen/destino escribiendo la calle y el número, o pulsando en el mapa
- **Ruta**: calcula kilómetros por carretera (sentido único, giros, etc.); si hay dos opciones parecidas, puedes elegir
- **Reparto**: reparte el coste del viaje según los kilómetros de cada tramo y los pasajeros
