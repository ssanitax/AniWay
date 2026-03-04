import os
import requests
from flask import Flask, render_template, request, jsonify

app = Flask(__name__)

# Configuramos la clave para que el HTML la detecte
# Vercel lee esto de tus Environment Variables
LLAVE_API = os.environ.get("LOCATIONIQ_KEY", "")
app.config['LOCATIONIQ_KEY'] = LLAVE_API

@app.route("/", methods=["GET", "POST"])
def index():
    # Tu lógica de cálculo de reparto aquí (la que ya tienes funciona bien)
    return render_template("index.html")

@app.route("/route")
def route():
    try:
        lat1 = request.args.get("lat1")
        lon1 = request.args.get("lon1")
        lat2 = request.args.get("lat2")
        lon2 = request.args.get("lon2")

        if not all([lat1, lon1, lat2, lon2]):
            return jsonify({"error": "Faltan coordenadas"}), 400

        # Usamos la clave cargada del entorno
        if not LLAVE_API:
            return jsonify({"error": "API Key no configurada en Vercel"}), 500

        # LocationIQ: lon,lat;lon,lat
        url = f"https://us1.locationiq.com/v1/directions/driving/{lon1},{lat1};{lon2},{lat2}"
        params = {"key": LLAVE_API, "format": "json"}

        # Hacemos la llamada a la API externa
        resp = requests.get(url, params=params, timeout=10)
        
        if resp.status_code != 200:
            return jsonify({"error": f"Error API {resp.status_code}", "detalle": resp.text}), 500
            
        data = resp.json()
        km = round(data[0]["distance"] / 1000, 1)
        return jsonify({"km": km})

    except Exception as e:
        # Esto nos dirá el error real en la pestaña 'Response' de Network
        return jsonify({"error": str(e)}), 500