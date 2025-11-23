from flask import Flask, render_template, request

app = Flask(__name__)

def calcular_trayecto(grupos, coste_total):
    grupos_validos = [g for g in grupos if g["amigos"] and g["dist"]]
    if not grupos_validos:
        return []

    total_dist = sum(g["dist"] for g in grupos_validos)
    resultados = []

    for g in grupos_validos:
        coste_grupo = (g["dist"] / total_dist) * coste_total
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
    if request.method == "POST":
        coste_total = float(request.form.get("coste_total", 0))

        grupos = []

        # --- Grupos de ida ---
        total_grupos_ida = int(request.form.get("total_grupos_ida", 0))
        for i in range(1, total_grupos_ida + 1):
            amigos_str = request.form.get(f"ida_amigos_{i}", "")
            dist = float(request.form.get(f"ida_dist_{i}", 0))
            amigos = [a.strip() for a in amigos_str.split(",") if a.strip()]
            if amigos:
                grupos.append({"amigos": amigos, "dist": dist})

        # --- Grupos de vuelta ---
        total_grupos_vuelta = int(request.form.get("total_grupos_vuelta", 0))
        for i in range(1, total_grupos_vuelta + 1):
            amigos_str = request.form.get(f"vuelta_amigos_{i}", "")
            dist = float(request.form.get(f"vuelta_dist_{i}", 0))
            amigos = [a.strip() for a in amigos_str.split(",") if a.strip()]
            if amigos:
                grupos.append({"amigos": amigos, "dist": dist})

        # Calcular coste y kilómetros por persona
        resultados = calcular_trayecto(grupos, coste_total)

        # Acumular coste y kilómetros por persona
        totales = {}
        for r in resultados:
            nombre = r["nombre"]
            if nombre not in totales:
                totales[nombre] = {"coste": 0, "km": 0}
            totales[nombre]["coste"] += r["coste"]
            totales[nombre]["km"] += r["km"]

        resultados_finales = [
            {"nombre": n, "coste": round(d["coste"], 2), "km": round(d["km"], 1)}
            for n, d in totales.items()
        ]

        return render_template("index.html", resultados=resultados_finales)

    return render_template("index.html")


if __name__ == "__main__":
    app.run(debug=True)


