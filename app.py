import os
import requests
from flask import Flask, render_template, request, jsonify

app = Flask(__name__)

# Configuración de la clave para el HTML y para el servidor
LLAVE_API = os.environ.get("LOCATIONIQ_KEY", "")
app.config['LOCATIONIQ_KEY'] = LLAVE_API

def calcular_trayecto(grupos, coste_total):
    grupos_validos = [g for g in grupos if g["amigos"] and g["dist"] > 0]
    if not grupos_validos or coste_total <= 0:
        return []

    total_dist = sum(g["dist"] for g in grupos_validos)
    resultados = []

    for g in grupos_validos:
        coste_grupo = (g["dist"] / total_dist) * coste_total
        coste_individual = coste_grupo / len(g["amigos"])

        for amigo in g["amigos"]:
            resultados.append({
                "nombre": amigo.strip().title(),
                "coste": round(coste_individual, 2),
                "km": g["dist"]
            })
    return resultados

@app.route("/", methods=["GET", "POST"])
def index():
    resultados_finales = None
    if request.method == "POST":
        try:
            modo_coste = request.form.get("modo_coste")
            grupos_ida = []
            grupos_vuelta = []

            for tipo in ["ida", "vuelta"]:
                total_str = request.form.get(f"total_groups_{tipo}", "0")
                total = int(total_str) if total_str.isdigit() else 0

                for i in range(1, total + 1):
                    amigos_str = request.form.get(f"{tipo}_amigos_{i}", "")
                    dist_str = request.form.get(f"{tipo}_dist_{i}", "0")
                    dist = float(dist_str) if dist_str else 0

                    amigos = [a.strip() for a in amigos_str.split(",") if a.strip()]
                    if amigos and dist > 0:
                        if tipo == "ida":
                            grupos_ida.append({"amigos": amigos, "dist": dist})
                        else:
                            grupos_vuelta.append({"amigos": amigos, "dist": dist})

            if modo_coste == "total":
                coste_total = float(request.form.get("coste_total") or 0)
                grupos = grupos_ida + grupos_vuelta
                brutos = calcular_trayecto(grupos, coste_total)
            else:
                coste_ida = float(request.form.get("coste_ida") or 0)
                coste_vuelta = float(request.form.get("coste_vuelta") or 0)
                brutos = calcular_trayecto(grupos_ida, coste_ida) + calcular_trayecto(grupos_vuelta, coste_vuelta)

            totales = {}
            for r in brutos:
                n = r["nombre"]
                if n not in totales: totales[n] = {"coste": 0, "km": 0}
                totales[n]["coste"] += r["coste"]
                totales[n]["km"] += r["km"]

            resultados_finales = sorted([
                {"nombre": n, "coste": round(d["coste"], 2), "km": round(d["km"], 1)}
                for n, d in totales.items()
            ], key=lambda x: x["coste"], reverse=True)

        except Exception as e:
            print(f"Error en calculo: {e}")

    return render_template("index.html", resultados=resultados_finales)

@app.route("/route")
def route():
    try:
        # 1. Obtener coordenadas
        la1 = request.args.get("lat1")
        lo1 = request.args.get("lon1")
        la2 = request.args.get("lat2")
        lo2 = request.args.get("lon2")

        if not all([la1, lo1, la2, lo2]):
            return jsonify({"error": "Faltan coordenadas"}), 400

        # 2. Redondeo estricto a 6 decimales (Vital para evitar InvalidQuery)
        lat1, lon1 = round(float(la1), 6), round(float(lo1), 6)
        lat2, lon2 = round(float(la2), 6), round(float(lo2), 6)

        if not LLAVE_API:
            return jsonify({"error": "API Key vacía en el servidor"}), 500

        # 3. URL FORMATEADA AL MILÍMETRO
        # Algunos clusters de LocationIQ fallan si hay caracteres extraños.
        # Probamos el formato de URL más simple y directo:
        url = f"https://us1.locationiq.com/v1/directions/driving/{lon1},{lat1};{lon2},{lat2}?key={LLAVE_API}&format=json"

        # 4. Petición con User-Agent (Para evitar bloqueos de Vercel)
        headers = {"User-Agent": "AniWayApp/1.0"}
        resp = requests.get(url, headers=headers, timeout=10)
        
        if resp.status_code != 200:
            return jsonify({
                "error": f"Error {resp.status_code}", 
                "server_msg": resp.text,
                "url_enviada": url.replace(LLAVE_API, "OCULTA") # Para debug sin mostrar tu clave
            }), resp.status_code

        data = resp.json()
        
        # 5. Extracción segura del KM
        # La API puede devolver la distancia en la raíz o dentro de 'routes'
        distancia = 0
        if isinstance(data, list) and len(data) > 0:
            distancia = data[0].get("distance", 0)
        elif isinstance(data, dict) and "routes" in data:
            distancia = data["routes"][0].get("distance", 0)
        
        if distancia == 0:
            return jsonify({"error": "No se encontró ruta entre esos puntos"}), 404

        km = round(distancia / 1000, 1)
        return jsonify({"km": km})

    except Exception as e:
        return jsonify({"error": f"Error interno: {str(e)}"}), 500