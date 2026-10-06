let map, markerOri, markerDest, activo = null;
let countIda = 0;
let countVuelta = 0;

const SPAIN_BOUNDS = L.latLngBounds(window.ANIWAY.spainBounds);

function init() {
    map = L.map('map-container', {
        zoomControl: false,
        maxBounds: SPAIN_BOUNDS.pad(0.05),
        maxBoundsViscosity: 1.0,
        minZoom: 5,
    }).fitBounds(SPAIN_BOUNDS);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
        bounds: SPAIN_BOUNDS,
    }).addTo(map);

    L.rectangle(SPAIN_BOUNDS, {
        color: '#06b6d4',
        weight: 1,
        fillOpacity: 0.03,
        interactive: false,
    }).addTo(map);

    map.on('click', (e) => {
        if (!activo) return;
        if (!SPAIN_BOUNDS.contains(e.latlng)) {
            alert('AniWay solo funciona dentro de España.');
            return;
        }
        updatePoint(e.latlng.lat, e.latlng.lng);
    });

    setTimeout(() => map.invalidateSize(), 500);
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

<label>ORIGEN (click en mapa · España)</label>
<input type="text" id="${tipo}_ori_${n}" onfocus="setActivo('${tipo}',${n},'ori')" readonly>

<label>DESTINO (click en mapa · España)</label>
<input type="text" id="${tipo}_dest_${n}" onfocus="setActivo('${tipo}',${n},'dest')" readonly>

<label>DISTANCIA (KM)</label>
<input type="number" step="0.1" id="${tipo}_km_${n}" class="km-input" readonly required>
`;

    document.getElementById('listaTrayectos').appendChild(div);
}

function eliminarTramo(btn) {
    btn.closest('.grupo-card').remove();
}

function setActivo(tipo, n, campo) {
    activo = { tipo, n, campo };
}

async function updatePoint(lat, lng) {
    const { tipo, n, campo } = activo;
    const input = document.getElementById(`${tipo}_${campo}_${n}`);

    input.dataset.lat = lat;
    input.dataset.lng = lng;
    input.value = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;

    if (campo === 'ori') {
        if (markerOri) map.removeLayer(markerOri);
        markerOri = L.marker([lat, lng]).addTo(map);
    } else {
        if (markerDest) map.removeLayer(markerDest);
        markerDest = L.marker([lat, lng]).addTo(map);
    }

    map.panTo([lat, lng]);
    calcularRuta(tipo, n);
}

async function calcularRuta(tipo, n) {
    const ori = document.getElementById(`${tipo}_ori_${n}`);
    const dest = document.getElementById(`${tipo}_dest_${n}`);

    if (!(ori.dataset.lat && dest.dataset.lat)) return;

    try {
        const res = await fetch(
            `api/route.php?lat1=${ori.dataset.lat}&lon1=${ori.dataset.lng}&lat2=${dest.dataset.lat}&lon2=${dest.dataset.lng}`
        );
        const data = await res.json();

        if (data.km) {
            document.getElementById(`${tipo}_km_${n}`).value = data.km;
        } else {
            alert(data.error || 'No se pudo calcular la ruta');
            console.error('route.php', res.status, data);
        }
    } catch (e) {
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

function renderHistory(trips) {
    const list = document.getElementById('history-list');
    if (!trips.length) {
        list.innerHTML = '<p class="history-empty">Aún no hay viajes guardados.</p>';
        return;
    }

    list.innerHTML = trips.map((trip) => {
        const fecha = trip.saved_at
            ? new Date(trip.saved_at).toLocaleString('es-ES', { dateStyle: 'short', timeStyle: 'short' })
            : '';
        const rows = (trip.resultados || []).map((r) =>
            `<div class="result-row"><span>${escapeHtml(r.nombre)}</span><strong>${r.coste} € (${r.km} km)</strong></div>`
        ).join('');
        return `
<div class="history-item">
<div class="history-meta">
<span>${escapeHtml(fecha)}</span>
<span>${(trip.resultados || []).length} personas</span>
</div>
${rows}
</div>`;
    }).join('');
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
        renderHistory(data.trips || []);
    } catch (err) {
        alert('Error de red al guardar el viaje');
    }
});

window.onload = init;
