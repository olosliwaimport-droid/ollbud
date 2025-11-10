import json
from datetime import datetime, date, timedelta
from flask import Flask, request, jsonify, Response
from pathlib import Path

# ====== PROSTA "Baza" plikowa (SQLite można dołożyć później) ======
DATA_DIR = Path(__file__).parent / "_data"
DATA_DIR.mkdir(exist_ok=True)
QUOTA_FILE = DATA_DIR / "quota.json"
LEADS_FILE = DATA_DIR / "leads.json"

def _load_json(p, default):
    if p.exists():
        try:
            return json.loads(p.read_text("utf-8"))
        except Exception:
            return default
    return default

def _save_json(p, data):
    p.write_text(json.dumps(data, ensure_ascii=False, indent=2), "utf-8")

# ====== Logika limitu dziennego ======
DEFAULT_DAILY_MAX = 3

def _today():
    return date.today().isoformat()

def quota_check_impl(client_id: str):
    data = _load_json(QUOTA_FILE, {})
    today = _today()
    q = data.get(client_id, {}).get(today, {"count": 0, "max": DEFAULT_DAILY_MAX})
    remaining = max(q["max"] - q["count"], 0)
    reset_at = (datetime.now() + timedelta(days=1)).replace(hour=0, minute=0, second=0, microsecond=0).isoformat()
    return {"client_id": client_id, "date": today, "count": q["count"], "max": q["max"], "remaining": remaining, "reset_at": reset_at}

def quota_consume_impl(client_id: str):
    data = _load_json(QUOTA_FILE, {})
    today = _today()
    user_q = data.setdefault(client_id, {})
    q = user_q.get(today, {"count": 0, "max": DEFAULT_DAILY_MAX})
    if q["count"] < q["max"]:
        q["count"] += 1
    user_q[today] = q
    data[client_id] = user_q
    _save_json(QUOTA_FILE, data)
    return quota_check_impl(client_id)

# ====== Szacunek kosztów (tak jak w prototypie) ======
BASE_RATES = {
    "malowanie": 45,
    "podłogi": 120,
    "łazienka": 1800,
    "kuchnia": 1500,
    "remont kompleksowy": 700,
}
STANDARD_MUL = {"ekonomiczny": 0.9, "standard": 1.0, "premium": 1.25}

def estimate_offer(scope: str, area_m2: float, standard: str, location: str):
    s = (scope or "").lower()
    m2 = float(area_m2 or 0)
    rate = 0
    items = []
    if "malow" in s:
        items.append({"name": "Malowanie", "unit": "m²", "qty": m2, "rate": BASE_RATES["malowanie"]})
        rate += BASE_RATES["malowanie"]
    if "podł" in s or "podl" in s:
        items.append({"name": "Układanie podłogi", "unit": "m²", "qty": m2, "rate": BASE_RATES["podłogi"]})
        rate += BASE_RATES["podłogi"]
    if "łaz" in s:
        items.append({"name": "Remont łazienki", "unit": "m²", "qty": m2, "rate": BASE_RATES["łazienka"]})
        rate += BASE_RATES["łazienka"]
    if "kuch" in s:
        items.append({"name": "Remont kuchni", "unit": "m²", "qty": m2, "rate": BASE_RATES["kuchnia"]})
        rate += BASE_RATES["kuchnia"]
    if "kompleks" in s:
        items = [{"name": "Remont kompleksowy", "unit": "m²", "qty": m2, "rate": BASE_RATES["remont kompleksowy"]}]
        rate = BASE_RATES["remont kompleksowy"]

    std_mul = STANDARD_MUL.get(standard, 1.0)
    loc_mul = 1.0 if ("kraków" in location.lower() or "krakow" in location.lower()) else 1.1

    subtotal = round(rate * m2 * std_mul * loc_mul)
    buffer = round(subtotal * 0.08)
    total = subtotal + buffer

    return {"items": items, "subtotal": subtotal, "buffer": buffer, "total": total, "currency": "PLN",
            "notes": "Wycena orientacyjna. Finalna cena po wizji lokalnej."}

# ====== Prosty export TXT (HTML/PDF można dorzucić później) ======
def export_txt(summary: dict, pricing: dict) -> tuple[str, str]:
    content = (
        "OLLBUD – szkic oferty (wstępny)\n\n"
        f"Zakres: {summary.get('scope')}\n"
        f"Metraż: {summary.get('area_m2')} m²\n"
        f"Standard: {summary.get('standard')}\n"
        f"Lokalizacja: {summary.get('location')}\n"
        f"Termin: {summary.get('deadline')}\n\n"
        f"Wycena orientacyjna: {pricing.get('total')} {pricing.get('currency','PLN')} "
        f"(w tym bufor: {pricing.get('buffer')})\n"
        "Uwaga: dokument wygenerowany automatycznie – wymaga weryfikacji po wizji lokalnej.\n"
    )
    filename = f"OLL_BUD_szkic_{date.today().isoformat()}.txt"
    return content, filename

# ====== Flask (WSGI) ======
application = Flask(__name__)

@application.get("/api/ping")
def ping():
    return jsonify({"ok": True, "ts": datetime.utcnow().isoformat()})

@application.post("/api/quota/check")
def quota_check():
    data = request.get_json(force=True)
    return jsonify(quota_check_impl(data["client_id"]))

@application.post("/api/quota/consume")
def quota_consume():
    data = request.get_json(force=True)
    return jsonify(quota_consume_impl(data["client_id"]))

@application.post("/api/offer/estimate")
def offer_estimate():
    payload = request.get_json(force=True)
    est = estimate_offer(
        scope=payload.get("scope",""),
        area_m2=payload.get("area_m2", 0),
        standard=payload.get("standard","standard"),
        location=payload.get("location",""),
    )
    # zapis leada do pliku
    leads = _load_json(LEADS_FILE, [])
    leads.append({
        "client_id": payload.get("client_id"),
        "created_at": datetime.utcnow().isoformat(),
        "scope": payload.get("scope"),
        "area_m2": payload.get("area_m2"),
        "standard": payload.get("standard"),
        "location": payload.get("location"),
        "deadline": payload.get("deadline"),
        "estimate_total": est["total"],
    })
    _save_json(LEADS_FILE, leads)
    return jsonify(est)

@application.post("/api/offer/export/txt")
def offer_export_txt():
    payload = request.get_json(force=True)
    content, filename = export_txt(payload.get("summary",{}), payload.get("pricing",{}))
    return Response(
        content,
        mimetype="text/plain; charset=utf-8",
        headers={"Content-Disposition": f"attachment; filename={filename}"}
    )
