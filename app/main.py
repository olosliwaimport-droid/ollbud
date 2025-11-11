from fastapi import FastAPI, Request
from fastapi.middleware.cors import CORSMiddleware
from app.pricing import estimate_offer

app = FastAPI()

# --- Konfiguracja CORS ---
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # docelowo możesz ograniczyć do ["https://ollbud.pl"]
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# --- Testowy endpoint ---
@app.get("/api/ping")
def ping():
    return {"ok": True}

# --- Strona główna API ---
@app.get("/")
def root():
    return {"status": "OK", "service": "OLLBUD backend"}

# --- Główny endpoint do wycen ---
@app.post("/api/offer/estimate")
async def estimate(request: Request):
    """
    Przyjmuje dane w formacie JSON:
    {
        "area_m2": liczba,
        "standard": "standard" | "deweloperski" | "kamienica"
    }
    Zwraca: szacunkową wycenę z przedziałami kosztów.
    """
    data = await request.json()
    area_m2 = data.get("area_m2", 0)
    standard = data.get("standard", "standard")
    result = estimate_offer(area_m2, standard)
    return result
