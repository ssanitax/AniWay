from flask import Flask, render_template, request
import os

app = Flask(__name__)

def calcular_trayecto(grupos, coste_total):
    grupos_validos = [g for g in grupos if g["amigos"] and g["dist"] > 0]
    if not grupos_validos: return []
    
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
            coste_total = float(request.form.get("coste_total", 0))
            grupos = []
            for tipo in ["ida", "vuelta"]:
                total = int(request.form.get(f"total_grupos_{tipo}", 0))
                for i in range(1, total + 1):
                    amigos_str = request.form.get(f"{tipo}_amigos_{i}", "")
                    dist = float(request.form.get(f"{tipo}_dist_{i}", 0))
                    amigos = [a.strip() for a in amigos_str.split(",") if a.strip()]
                    if amigos and dist > 0:
                        grupos.append({"amigos": amigos, "dist": dist})
            
            brutos = calcular_trayecto(grupos, coste_total)
            totales = {}
            for r in brutos:
                n = r["nombre"]
                if n not in totales: totales[n] = {"coste": 0, "km": 0}
                totales[n]["coste"] += r["coste"]
                totales[n]["km"] += r["km"]
            
            resultados_finales = sorted([
                {"nombre": n, "coste": round(d["coste"], 2), "km": round(d["km"], 1)}
                for n, d in totales.items()
            ], key=lambda x: x['coste'], reverse=True)
        except Exception as e:
            print(f"Error en calculo: {e}")

    return render_template("index.html", resultados=resultados_finales)

if __name__ == "__main__":
    port = int(os.environ.get("PORT", 5000))
    app.run(host="0.0.0.0", port=port, debug=True)
