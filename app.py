from flask import Flask, render_template, request
import os

app = Flask(__name__)

def calcular_trayecto(grupos, coste_total):
    """Calcula el coste proporcional por persona basado en la distancia total."""
    grupos_validos = [g for g in grupos if g["amigos"] and g["dist"] > 0]
    if not grupos_validos:
        return []

    total_dist = sum(g["dist"] for g in grupos_validos)
    resultados = []

    for g in grupos_validos:
        # El coste del grupo es proporcional a su distancia sobre el total
        coste_grupo = (g["dist"] / total_dist) * coste_total
        # Se divide entre los amigos que iban en ese tramo
        coste_individual = coste_grupo / len(g["amigos"])
        
        for amigo in g["amigos"]:
            resultados.append({
                "nombre": amigo,
                "coste": round(coste_individual, 2),
                "km": g["dist"]
            })
    return resultados

@app.route("/", methods=["GET", "POST"])
def index():
    # Obtenemos la API KEY de las variables de entorno para mayor seguridad
    # En Render, deberás configurar 'GOOGLE_API_KEY' en la sección Environment Variables
    google_api_key = os.environ.get("GOOGLE_API_KEY", "")

    if request.method == "POST":
        try:
            coste_total = float(request.form.get("coste_total", 0))
        except ValueError:
            coste_total = 0

        grupos = []

        # Procesamos ambos tipos de trayectos (ida y vuelta)
        for tipo in ["ida", "vuelta"]:
            total_grupos = int(request.form.get(f"total_grupos_{tipo}", 0))
            for i in range(1, total_grupos + 1):
                amigos_str = request.form.get(f"{tipo}_amigos_{i}", "")
                try:
                    dist = float(request.form.get(f"{tipo}_dist_{i}", 0))
                except ValueError:
                    dist = 0
                
                amigos = [a.strip() for a in amigos_str.split(",") if a.strip()]
                if amigos and dist > 0:
                    grupos.append({"amigos": amigos, "dist": dist})

        # Realizar el cálculo lógico
        resultados_brutos = calcular_trayecto(grupos, coste_total)

        # Consolidar resultados: si alguien viaja en varios grupos, sumamos sus costes y km
        totales = {}
        for r in resultados_brutos:
            nombre = r["nombre"].title() # Capitalizamos nombres
            if nombre not in totales:
                totales[nombre] = {"coste": 0, "km": 0}
            totales[nombre]["coste"] += r["coste"]
            totales[nombre]["km"] += r["km"]

        resultados_finales = [
            {"nombre": n, "coste": round(d["coste"], 2), "km": round(d["km"], 1)}
            for n, d in totales.items()
        ]

        # Ordenar por coste de mayor a menor
        resultados_finales = sorted(resultados_finales, key=lambda x: x['coste'], reverse=True)

        return render_template("index.html", 
                               resultados=resultados_finales, 
                               google_api_key=google_api_key)

    # Carga inicial de la página
    return render_template("index.html", google_api_key=google_api_key)

if __name__ == "__main__":
    # Configuración para producción (Render) y local
    # Render asigna un puerto dinámico mediante la variable de entorno PORT
    port = int(os.environ.get("PORT", 5000))
    app.run(host="0.0.0.0", port=port, debug=False)
