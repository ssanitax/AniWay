let map, markerOri, markerDest, activo = null;
const rutas = new Map();
const opcionesRuta = new Map();
const COLOR_OPTIMA = '#22d3ee';
const COLOR_ALTER = '#f59e0b';
let campoArmado = false;
let countIda = 0;
let countVuelta = 0;

const SPAIN_BOUNDS = L.latLngBounds(window.ANIWAY.spainBounds);
// Península y Baleares. El recuadro completo (con Canarias) deja la península pequeña.
const SPAIN_VIEW = L.latLngBounds([35.95, -9.55], [43.85, 4.45]);

function encuadrarEspana() {
    map.invalidateSize();
    map.fitBounds(SPAIN_VIEW, { padding: [12, 12], animate: false });
}

function init() {
    map = L.map('map-container', {
        zoomControl: true,
        zoomSnap: 0.1,
        zoomDelta: 0.5,
        maxBounds: SPAIN_BOUNDS.pad(0.4),
        maxBoundsViscosity: 0.85,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
        maxZoom: 18,
    }).addTo(map);

    map.on('click', (e) => {
        if (!SPAIN_BOUNDS.contains(e.latlng)) {
            alert('AniWay solo funciona dentro de España.');
            return;
        }
        if (!campoArmado || !campoListo()) {
            const vacio = [...document.querySelectorAll('.lugar-input')].find((el) => !el.readOnly && !el.dataset.lat);
            if (vacio) {
                const card = vacio.closest('.grupo-card');
                setActivo(card.dataset.tipo, card.dataset.n, campoLugar(vacio));
            } else if (!campoListo()) {
                return;
            }
        }
        updatePoint(e.latlng.lat, e.latlng.lng);
    });

    encuadrarEspana();
    window.addEventListener('resize', () => map.invalidateSize());
}

function cambiarModoCoste() {
    const modo = document.getElementById('modo_coste').value;
    document.getElementById('coste_total_container').style.display = modo === 'total' ? 'block' : 'none';
    document.getElementById('coste_separado_container').style.display = modo === 'separado' ? 'block' : 'none';
}

function crearTramo(tipo) {
    let n;
    if (tipo === 'ida') {
        countIda++;
        n = countIda;
    } else {
        countVuelta++;
        n = countVuelta;
    }

    const div = document.createElement('div');
    div.className = 'grupo-card';
    div.dataset.tipo = tipo;
    div.dataset.n = String(n);

    div.innerHTML = `
<button type="button" class="btn-remove" onclick="eliminarTramo(this)">✕</button>
<p class="card-id">${tipo.toUpperCase()} #${n}</p>

<label>NOMBRES AMIGOS</label>
<input type="text" class="amigos-input" placeholder="Ana, Pepe, Luis..." required>

<label class="label-origen">ORIGEN</label>
<input type="text" class="ori-input lugar-input" id="${tipo}_ori_${n}" placeholder="Calle y número, o clic en el mapa" autocomplete="off" spellcheck="false">

<label>DESTINO</label>
<input type="text" class="dest-input lugar-input" id="${tipo}_dest_${n}" placeholder="Calle y número, o clic en el mapa" autocomplete="off" spellcheck="false">

<label>DISTANCIA (KM)</label>
<input type="number" step="0.1" id="${tipo}_km_${n}" class="km-input" readonly required>
<div class="ruta-opciones" id="${tipo}_opciones_${n}" hidden></div>
`;

    document.getElementById('listaTrayectos').appendChild(div);
    sincronizarCadena();
}

function tarjetas() {
    return [...document.querySelectorAll('#listaTrayectos .grupo-card')];
}

function escribirPunto(input, lat, lng, etiqueta) {
    input.dataset.lat = String(lat);
    input.dataset.lng = String(lng);
    const texto = etiqueta || `${Number(lat).toFixed(5)}, ${Number(lng).toFixed(5)}`;
    input.dataset.label = texto;
    input.value = texto;
}

function vaciarPunto(input) {
    delete input.dataset.lat;
    delete input.dataset.lng;
    delete input.dataset.label;
    input.value = '';
    cerrarSugerencias(input);
}

function copiarPunto(desde, hacia) {
    if (!desde.dataset.lat) {
        if (!hacia.dataset.lat && hacia.value === '') return false;
        vaciarPunto(hacia);
        return true;
    }
    if (desde.dataset.lat === hacia.dataset.lat && desde.dataset.lng === hacia.dataset.lng && hacia.value === desde.value) {
        return false;
    }
    escribirPunto(hacia, desde.dataset.lat, desde.dataset.lng, desde.value);
    return true;
}

function sincronizarCadena() {
    const cards = tarjetas();
    cards.forEach((card, i) => {
        const ori = card.querySelector('.ori-input');
        const label = card.querySelector('.label-origen');
        const encadenado = i > 0;
        ori.classList.toggle('origen-encadenado', encadenado);
        ori.readOnly = encadenado;
        label.textContent = encadenado ? 'ORIGEN (tramo anterior)' : 'ORIGEN';
        ori.placeholder = encadenado
            ? 'Se copia del destino anterior'
            : 'Calle y número, o clic en el mapa';
        if (encadenado) cerrarSugerencias(ori);

        if (!encadenado) return;

        const cambio = copiarPunto(cards[i - 1].querySelector('.dest-input'), ori);
        if (!cambio) return;
        if (!ori.dataset.lat) {
            card.querySelector('.km-input').value = '';
        }
        calcularRuta(card.dataset.tipo, card.dataset.n);
    });
}

function eliminarTramo(btn) {
    const card = btn.closest('.grupo-card');
    if (activo && activo.tipo === card.dataset.tipo && String(activo.n) === card.dataset.n) {
        activo = null;
    }
    quitarRuta(card.dataset.tipo, card.dataset.n);
    card.remove();
    sincronizarCadena();
}

function claveRuta(tipo, n) {
    return `${tipo}-${n}`;
}

function quitarRuta(tipo, n) {
    const key = claveRuta(tipo, n);
    (rutas.get(key) || []).forEach((capa) => map.removeLayer(capa));
    rutas.delete(key);
    opcionesRuta.delete(key);
    const caja = document.getElementById(`${tipo}_opciones_${n}`);
    if (caja) {
        caja.hidden = true;
        caja.innerHTML = '';
    }
}

function aplicarOpcion(tipo, n, indice, encuadrar) {
    const opciones = opcionesRuta.get(claveRuta(tipo, n)) || [];
    const elegida = opciones[indice];
    if (!elegida) return;
    document.getElementById(`${tipo}_km_${n}`).value = elegida.km;
    pintarEleccion(tipo, n, opciones, indice);
    dibujarOpciones(tipo, n, opciones, indice, encuadrar);
}

function pintarEleccion(tipo, n, opciones, elegida) {
    const caja = document.getElementById(`${tipo}_opciones_${n}`);
    if (!caja) return;
    caja.innerHTML = '';
    if (opciones.length < 2) {
        caja.hidden = true;
        return;
    }
    caja.hidden = false;
    opciones.forEach((op, i) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ruta-eleccion' + (i === elegida ? ' elegida' : '');
        const color = i === 0 ? COLOR_OPTIMA : COLOR_ALTER;
        const nombre = i === 0 ? 'Óptima' : 'Alternativa';
        const via = op.via ? ` · ${op.via}` : '';
        btn.innerHTML = `<span class="ruta-punto" style="background:${color}"></span><span>${nombre} · ${op.min} min · ${op.km} km${via}</span>`;
        if (i === elegida) btn.style.borderColor = color;
        btn.addEventListener('click', () => aplicarOpcion(tipo, n, i, false));
        caja.appendChild(btn);
    });
}

function dibujarOpciones(tipo, n, opciones, elegida, encuadrar) {
    const key = claveRuta(tipo, n);
    (rutas.get(key) || []).forEach((capa) => map.removeLayer(capa));
    const capas = [];
    const orden = opciones.map((_, i) => i).sort((a, b) => (a === elegida) - (b === elegida));
    orden.forEach((i) => {
        const op = opciones[i];
        if (!Array.isArray(op.geometry) || op.geometry.length < 2) return;
        const activa = i === elegida;
        const linea = L.polyline(op.geometry.map(([lon, lat]) => [lat, lon]), {
            color: i === 0 ? COLOR_OPTIMA : COLOR_ALTER,
            weight: activa ? 7 : 4,
            opacity: activa ? 0.95 : 0.7,
            dashArray: activa ? null : '8 8',
        }).addTo(map);
        const via = op.via ? ` · ${op.via}` : '';
        linea.bindTooltip(`${i === 0 ? 'Óptima' : 'Alternativa'}: ${op.min} min · ${op.km} km${via}`, { sticky: true });
        linea.on('click', (ev) => {
            L.DomEvent.stopPropagation(ev);
            aplicarOpcion(tipo, n, i, false);
        });
        if (activa) linea.bringToFront();
        capas.push(linea);
    });
    rutas.set(key, capas);
    if (encuadrar && capas.length) {
        map.fitBounds(L.featureGroup(capas).getBounds(), { padding: [36, 36], maxZoom: 16 });
    }
}

function campoListo() {
    if (!activo) return false;
    const input = document.getElementById(`${activo.tipo}_${activo.campo}_${activo.n}`);
    return !!input && !input.readOnly;
}

function setActivo(tipo, n, campo) {
    const card = document.querySelector(`.grupo-card[data-tipo="${tipo}"][data-n="${n}"]`);
    if (campo === 'ori' && tarjetas()[0] !== card) {
        card.querySelector('.ori-input').blur();
        return;
    }
    activo = { tipo, n, campo };
    campoArmado = true;
    document.querySelectorAll('.input-active').forEach((el) => el.classList.remove('input-active'));
    document.getElementById(`${tipo}_${campo}_${n}`).classList.add('input-active');
}

async function updatePoint(lat, lng, etiqueta) {
    if (!activo) return;
    const { tipo, n, campo } = activo;
    const input = document.getElementById(`${tipo}_${campo}_${n}`);
    cerrarSugerencias(input);

    escribirPunto(input, lat, lng, etiqueta);

    if (campo === 'ori') {
        if (markerOri) map.removeLayer(markerOri);
        markerOri = L.marker([lat, lng]).addTo(map);
    } else {
        if (markerDest) map.removeLayer(markerDest);
        markerDest = L.marker([lat, lng]).addTo(map);
    }

    const zoomCasa = /\d/.test(String(etiqueta || '')) ? 18 : 16;
    map.setView([lat, lng], Math.max(map.getZoom(), zoomCasa));
    calcularRuta(tipo, n);
    if (campo === 'dest') sincronizarCadena();
    campoArmado = false;

    if (etiqueta) return;

    const texto = await direccionInversa(lat, lng);
    if (!texto) return;
    if (input.dataset.lat !== String(lat) || input.dataset.lng !== String(lng)) return;
    escribirPunto(input, lat, lng, texto);
    if (campo === 'dest') sincronizarCadena();
}

async function direccionInversa(lat, lng) {
    try {
        const res = await fetch(`api/geocode.php?lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lng)}`);
        const data = await res.json();
        return data.label || '';
    } catch (e) {
        return '';
    }
}

const busquedaTimer = new WeakMap();
const busquedaAbort = new WeakMap();
const sugerencias = document.createElement('div');
sugerencias.id = 'sugerencias';
sugerencias.hidden = true;
document.body.appendChild(sugerencias);
let sugerenciasInput = null;

function cerrarSugerencias(input) {
    if (input && sugerenciasInput && input !== sugerenciasInput) return;
    sugerencias.hidden = true;
    sugerencias.innerHTML = '';
    sugerenciasInput = null;
}

function colocarSugerencias() {
    if (!sugerenciasInput || sugerencias.hidden) return;
    const rect = sugerenciasInput.getBoundingClientRect();
    const margen = 6;
    const ancho = `${Math.round(rect.width)}px`;
    const izquierda = `${Math.round(rect.left)}px`;
    sugerencias.hidden = false;
    const alto = Math.min(sugerencias.scrollHeight, 220);
    const espacioAbajo = window.innerHeight - rect.bottom;
    const top = espacioAbajo < alto + margen && rect.top > espacioAbajo
        ? Math.max(8, rect.top - alto - margen)
        : rect.bottom + margen;
    const arriba = `${Math.round(top)}px`;
    if (sugerencias.style.width !== ancho) sugerencias.style.width = ancho;
    if (sugerencias.style.left !== izquierda) sugerencias.style.left = izquierda;
    if (sugerencias.style.top !== arriba) sugerencias.style.top = arriba;
}

function pintarSugerencias(input, resultados, mensaje) {
    if (document.activeElement !== input) return;
    sugerenciasInput = input;
    sugerencias.innerHTML = '';
    if (!resultados.length) {
        sugerencias.innerHTML = `<div class="sug-empty">${escapeHtml(mensaje || 'Sin resultados en España')}</div>`;
    } else {
        resultados.forEach((item, index) => {
            const row = document.createElement('button');
            row.type = 'button';
            row.className = 'sug-item' + (index === 0 ? ' sug-active' : '');
            row.dataset.lat = String(item.lat);
            row.dataset.lng = String(item.lng);
            row.dataset.label = item.label;
            row.textContent = item.label;
            sugerencias.appendChild(row);
        });
    }
    sugerencias.hidden = false;
    colocarSugerencias();
}

function programarBusqueda(input) {
    clearTimeout(busquedaTimer.get(input));
    const q = input.value.trim();
    if (q.length < 3) {
        cerrarSugerencias(input);
        return;
    }
    busquedaTimer.set(input, setTimeout(() => buscarDirecciones(input, q), 280));
}

async function buscarDirecciones(input, q) {
    busquedaAbort.get(input)?.abort();
    const ctrl = new AbortController();
    busquedaAbort.set(input, ctrl);
    try {
        const res = await fetch(`api/geocode.php?q=${encodeURIComponent(q)}`, { signal: ctrl.signal });
        const data = await res.json();
        if (input.value.trim() !== q) return;
        if (!res.ok) {
            pintarSugerencias(input, [], data.error || 'No se pudo buscar');
            return;
        }
        pintarSugerencias(input, data.results || []);
    } catch (e) {
        if (e.name === 'AbortError') return;
        pintarSugerencias(input, [], 'No se pudo buscar');
    }
}

function numeroEscrito(q) {
    const re = /(?:^|[\s,])(\d+)(?:\s*(bis)|([a-zA-Z]))?(?=[\s,]|$)/gi;
    let m;
    let ultimo = '';
    while ((m = re.exec(String(q))) !== null) {
        const extra = (m[2] || m[3] || '').toLowerCase();
        ultimo = m[1] + (extra === 'bis' ? 'bis' : extra);
    }
    return ultimo;
}

function conservaNumero(etiqueta, numero) {
    if (!numero) return true;
    return String(etiqueta).toLowerCase().replace(/\s/g, '').includes(numero);
}

function elegirSugerencia(input, item) {
    const card = input.closest('.grupo-card');
    const campo = input.classList.contains('ori-input') ? 'ori' : 'dest';
    setActivo(card.dataset.tipo, card.dataset.n, campo);
    updatePoint(Number(item.dataset.lat), Number(item.dataset.lng), item.dataset.label);
}

async function confirmarDireccion(input) {
    const q = input.value.trim();
    clearTimeout(busquedaTimer.get(input));
    busquedaAbort.get(input)?.abort();
    cerrarSugerencias(input);
    if (q.length < 3) return;
    try {
        const res = await fetch(`api/geocode.php?q=${encodeURIComponent(q)}`);
        const data = await res.json();
        const item = (data.results || [])[0];
        if (!item) return;
        const card = input.closest('.grupo-card');
        setActivo(card.dataset.tipo, card.dataset.n, campoLugar(input));
        updatePoint(Number(item.lat), Number(item.lng), item.label);
    } catch (e) {
        /* se deja el texto escrito, con el número */
    }
}

function moverResaltado(direccion) {
    const items = [...sugerencias.querySelectorAll('.sug-item')];
    if (!items.length) return;
    const actual = items.findIndex((item) => item.classList.contains('sug-active'));
    const siguiente = actual < 0 ? 0 : (actual + direccion + items.length) % items.length;
    items.forEach((item) => item.classList.remove('sug-active'));
    const item = items[siguiente];
    item.classList.add('sug-active');
    const arriba = item.offsetTop;
    const abajo = arriba + item.offsetHeight;
    if (arriba < sugerencias.scrollTop) {
        sugerencias.scrollTop = arriba;
    } else if (abajo > sugerencias.scrollTop + sugerencias.clientHeight) {
        sugerencias.scrollTop = abajo - sugerencias.clientHeight;
    }
}

function campoLugar(input) {
    return input.classList.contains('ori-input') ? 'ori' : 'dest';
}

function alEscribirLugar(input) {
    if (input.readOnly) return;
    const card = input.closest('.grupo-card');
    setActivo(card.dataset.tipo, card.dataset.n, campoLugar(input));
    if (input.value.trim() !== (input.dataset.label || '')) {
        delete input.dataset.lat;
        delete input.dataset.lng;
        delete input.dataset.label;
        card.querySelector('.km-input').value = '';
        if (campoLugar(input) === 'dest') sincronizarCadena();
    }
    programarBusqueda(input);
}

async function calcularRuta(tipo, n) {
    const ori = document.getElementById(`${tipo}_ori_${n}`);
    const dest = document.getElementById(`${tipo}_dest_${n}`);

    if (!(ori.dataset.lat && dest.dataset.lat)) {
        quitarRuta(tipo, n);
        return;
    }

    try {
        const res = await fetch(
            `api/route.php?lat1=${ori.dataset.lat}&lon1=${ori.dataset.lng}&lat2=${dest.dataset.lat}&lon2=${dest.dataset.lng}`
        );
        const data = await res.json();

        if (Array.isArray(data.routes) && data.routes.length) {
            opcionesRuta.set(claveRuta(tipo, n), data.routes);
            aplicarOpcion(tipo, n, 0, true);
        } else {
            quitarRuta(tipo, n);
            alert(data.error || 'No se pudo calcular la ruta');
            console.error('route.php', res.status, data);
        }
    } catch (e) {
        quitarRuta(tipo, n);
        alert('Error calculando ruta');
    }
}

function collectGrupos(tipo) {
    const grupos = [];
    document.querySelectorAll(`.grupo-card[data-tipo="${tipo}"]`).forEach((card) => {
        const n = card.dataset.n;
        const amigosStr = card.querySelector('.amigos-input').value || '';
        const dist = parseFloat(document.getElementById(`${tipo}_km_${n}`).value) || 0;
        const amigos = amigosStr.split(',').map((a) => a.trim()).filter(Boolean);
        if (amigos.length && dist > 0) {
            grupos.push({ amigos, dist });
        }
    });
    return grupos;
}

function renderResults(resultados) {
    const box = document.getElementById('results');
    const list = document.getElementById('results-list');
    list.innerHTML = '';
    resultados.forEach((r) => {
        const row = document.createElement('div');
        row.className = 'result-row';
        row.innerHTML = `<span>${escapeHtml(r.nombre)}</span><strong>${r.coste} € (${r.km} km)</strong>`;
        list.appendChild(row);
    });
    box.style.display = 'block';
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

document.getElementById('trip-form').addEventListener('submit', async (e) => {
    e.preventDefault();

    const payload = {
        modo_coste: document.getElementById('modo_coste').value,
        coste_total: parseFloat(document.getElementById('coste_total').value) || 0,
        coste_ida: parseFloat(document.getElementById('coste_ida').value) || 0,
        coste_vuelta: parseFloat(document.getElementById('coste_vuelta').value) || 0,
        grupos_ida: collectGrupos('ida'),
        grupos_vuelta: collectGrupos('vuelta'),
    };

    try {
        const res = await fetch('api/trips.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();

        if (!res.ok) {
            alert(data.error || 'Error al calcular');
            return;
        }

        renderResults(data.resultados);
    } catch (err) {
        alert('Error de red al calcular el viaje');
    }
});

const lista = document.getElementById('listaTrayectos');

lista.addEventListener('focusin', (e) => {
    const input = e.target.closest('.lugar-input');
    if (!input || input.readOnly) return;
    const card = input.closest('.grupo-card');
    setActivo(card.dataset.tipo, card.dataset.n, campoLugar(input));
});

lista.addEventListener('input', (e) => {
    const input = e.target.closest('.lugar-input');
    if (!input) return;
    alEscribirLugar(input);
});

lista.addEventListener('keydown', (e) => {
    const input = e.target.closest('.lugar-input');
    if (!input || input.readOnly) return;
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        moverResaltado(1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        moverResaltado(-1);
    } else if (e.key === 'Escape') {
        cerrarSugerencias(input);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        confirmarDireccion(input);
    }
});

sugerencias.addEventListener('mousedown', (e) => {
    const item = e.target.closest('.sug-item');
    if (!item || !sugerenciasInput) return;
    e.preventDefault();
    if (!conservaNumero(item.dataset.label || '', numeroEscrito(sugerenciasInput.value))) {
        confirmarDireccion(sugerenciasInput);
        return;
    }
    elegirSugerencia(sugerenciasInput, item);
});

document.getElementById('sidebar').addEventListener('scroll', () => {
    colocarSugerencias();
}, { passive: true });

document.addEventListener('mousedown', (e) => {
    if (e.target.closest('.lugar-input') || e.target.closest('#sugerencias')) return;
    cerrarSugerencias();
});

window.onload = init;
