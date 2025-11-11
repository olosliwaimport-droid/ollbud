from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from app.pricing import estimate_offer

app = FastAPI()

# --- CORS ---
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # docelowo możesz zawęzić do ["https://ollbud.pl"]
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# --- MODEL DANYCH ---
class OfferRequest(BaseModel):
    area_m2: float
    standard: str


# --- ENDPOINTY ---
@app.get("/api/ping")
def ping():
    return {"ok": True}


@app.post("/api/offer/estimate")
def offer_estimate(data: OfferRequest):
    try:
        result = estimate_offer(data.area_m2, data.standard)
        return result
    except Exception as e:
        import traceback
        print("=== BŁĄD W ESTIMATE ===")
        traceback.print_exc()
        return {"error": str(e)}


@app.get("/")
def root():
    return {"status": "OK", "service": "OLLBUD backend"}

# app/main.py (DODATKOWA CZĘŚĆ)

from pydantic import BaseModel
from typing import List
from app.chat_agent import run_chat_agent, ChatTurn

class ChatPayload(BaseModel):
    # prosto: wysyłamy całą krótką historię (frontend ją trzyma)
    history: List[ChatTurn]

@app.post("/api/chat", tags=["chat"])
def api_chat(payload: ChatPayload):
    """
    Odpowiada agent GPT. Gdy ma komplet danych, sam wywoła estimate_offer.
    """
    out = run_chat_agent(payload.history)
    return out
