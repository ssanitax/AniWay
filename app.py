from flask import Flask, render_template, request
import os

app = Flask(__name__)

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
                total = int(request.form.get(f"total_grupos_{tipo}", 0))

                for i in range(1, total + 1):
                    amigos_str = request.form.get(f"{tipo}_amigos_{i}", "")
                    dist = float(request.form.get(f"{tipo}_dist_{i}", 0))

                    amigos = [a.strip() for a in amigos_str.split(",") if a.strip()]

                    if amigos and dist > 0:
                        if tipo == "ida":
                            grupos_ida.append({"amigos": amigos, "dist": dist})
                        else:
                            grupos_vuelta.append({"amigos": amigos, "dist": dist})

            if modo_coste == "total":
                coste_total = float(request.form.get("coste_total", 0))
                grupos = grupos_ida + grupos_vuelta
                brutos = calcular_trayecto(grupos, coste_total)

            else:
                coste_ida = float(request.form.get("coste_ida", 0))
                coste_vuelta = float(request.form.get("coste_vuelta", 0))

                brutos_ida = calcular_trayecto(grupos_ida, coste_ida)
                brutos_vuelta = calcular_trayecto(grupos_vuelta, coste_vuelta)

                brutos = brutos_ida + brutos_vuelta

            totales = {}

            for r in brutos:
                n = r["nombre"]
                if n not in totales:
                    totales[n] = {"coste": 0, "km": 0}

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


if __name__ == "__main__":
    port = int(os.environ.get("PORT", 5000))
    app.run(host="0.0.0.0", port=port, debug=True)
