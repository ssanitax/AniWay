from flask import Flask, render_template, request, jsonify
import os
import requests

app = Flask(__name__)

# Intentar cargar la clave de las variables de entorno de Vercel
# Usamos os.environ.get para que no de error si no existe aún
LLAVE = os.environ.get("LOCATIONIQ_KEY")
app.config['LOCATIONIQ_KEY'] = LLAVE

def calcular_trayecto(grupos, coste_total):
    grupos_validos = [g for g in grupos if g["amigos"] and g["dist"] > 0]
    if not grupos_validos or coste_total <= 0:
        return []

    total_dist = sum(g["dist"] for g in grupos_validos)
    resultados = []

    for g in grupos_validos:
        coste_group = (g["dist"] / total_dist) * coste_total
        coste_individual = coste_group / len(g["amigos"])

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
                brutos_ida = calcular_trayecto(grupos_ida, coste_ida)
                brutos_vuelta = calcular_trayecto(grupos_vuelta, coste_vuelta)
                brutos = brutos_ida + brutos_vuelta

            totales = {}
            for r in brutos:
                n = r["nombre"]
                if n not in totales: totales[n] = {"coste": 0, "km": 0}
                totales[n]["coste"] += r["coste"]
                totales[n]["km"] += r["km"]

            resultados_finales = sorted([
                {
                    "nombre": n,
                    "coste": round(d["coste"], 2),
                    "km": round(d["km"], 1)
                }
                for n, d in totales.items()
            ], key=lambda x: x["coste"], reverse=True)

        except Exception as e:
            print(f"Error en calculo: {e}")

    return render_template("index.html", resultados=resultados_finales)

@app.route("/route")
def route():
    # Extraemos los datos que envía el JS
    lat1 = request.args.get("lat1")
    lon1 = request.args.get("lon1")
    lat2 = request.args.get("lat2")
    lon2 = request.args.get("lon2")

    if not all([lat1, lon1, lat2, lon2]):
        return jsonify({"error": "Faltan coordenadas"}), 400

    # Usamos la clave que ya sabemos que funciona
    key = os.environ.get("LOCATIONIQ_KEY")
    
    # IMPORTANTE: LocationIQ usa lon,lat;lon,lat
    # Construimos la URL con el orden correcto
    url = f"https://us1.locationiq.com/v1/directions/driving/{lon1},{lat1};{lon2},{lat2}?key={key}&format=json"

    try:
        # Añadimos un User-Agent para que la API no nos bloquee
        headers = {"User-Agent": "AniWay-App"}
        resp = requests.get(url, headers=headers, timeout=10)
        
        # Si la API responde con error, queremos ver qué dice exactamente
        if resp.status_code != 200:
            return jsonify({"error": f"API Error {resp.status_code}", "detalle": resp.text}), 500

        data = resp.json()
        
        # Extraemos la distancia (viene en metros) y pasamos a KM
        distancia_metros = data[0]["distance"]
        km = round(distancia_metros / 1000, 1)
        
        return jsonify({"km": km})

    except Exception as e:
        # Si algo falla en Python, esto nos dirá qué es
        return jsonify({"error": "Excepción en servidor", "mensaje": str(e)}), 500

if __name__ == "__main__":
    app.run(debug=True)