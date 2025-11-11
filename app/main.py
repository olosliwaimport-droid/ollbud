from fastapi import FastAPI, Request
from fastapi.middleware.cors import CORSMiddleware
from app.pricing import estimate_offer

app = FastAPI(
    title="OLLBUD API",
    description="Backend do szacowania kosztów robót budowlanych i wykończeniowych.",
    version="1.0.0"
)

# --- CORS (do połączenia z frontendem) ---
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # można zawęzić np. ["https://ollbud.pl"]
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

# --- Główny endpoint do wyceny ---
@app.post("/api/offer/estimate", tags=["offer"])
async def estimate(request: Request):
    """
    Szacuje koszt na podstawie powierzchni i standardu.
    
    **Body (JSON)**:
    ```
    {
        "area_m2": 45,
        "standard": "blok"
    }
    ```
    """
    data = await request.json()
    area_m2 = data.get("area_m2", 0)
    standard = data.get("standard", "standard")
    result = estimate_offer(area_m2, standard)
    return result
