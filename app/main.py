from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["https://ollbud.pl"],  # docelowo możesz zawęzić do ["https://ollbud.pl"]
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

@app.get("/api/ping")
def ping():
    return {"ok": True}

@app.get("/")
def root():
    return {"status": "OK", "service": "OLLBUD backend"}

from app.pricing import estimate_offer
from fastapi import Request

@app.post("/api/offer/estimate")
async def estimate(request: Request):
    data = await request.json()
    area_m2 = data.get("area_m2", 0)
    standard = data.get("standard", "standard")
    result = estimate_offer(area_m2, standard)
    return result
