from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

app = FastAPI()

# 🔹 Pozwól na żądania z Twojej domeny (np. z frontendu)
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # lub ["https://ollbud.pl"] jeśli chcesz zawęzić
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# 🔹 Prosty endpoint testowy
@app.get("/api/ping")
def ping():
    return {"ok": True}

# 🔹 Przykładowy endpoint ofertowy (placeholder)
@app.post("/api/offer/estimate")
def estimate_offer(data: dict):
    """
    Przykładowe API, które w przyszłości może analizować dane
    i zwracać kosztorys lub opis prac.
    """
    area = data.get("area_m2", 0)
    standard = data.get("standard", "standard")
    price_per_m2 = 150 if standard == "standard" else 200
    return {
        "estimated_cost": area * price_per_m2,
        "currency": "PLN",
        "standard": standard
    }

# 🔹 Endpoint domyślny
@app.get("/")
def root():
    return {"status": "OK", "message": "OLLbud API działa 🚀"}
