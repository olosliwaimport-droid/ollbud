from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import List
from app.pricing import estimate_offer
from app.chat_agent import run_chat_agent, ChatTurn

app = FastAPI()

# --- CORS ---
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # docelowo: ["https://ollbud.pl"]
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


# --- AGENT GPT /api/chat ---
class ChatPayload(BaseModel):
    history: List[ChatTurn]

@app.post("/api/chat", tags=["chat"])
def api_chat(payload: ChatPayload):
    """
    Odpowiada agent GPT. Gdy ma komplet danych, sam wywoła estimate_offer.
    """
    out = run_chat_agent(payload.history)
    return out


@app.get("/")
def root():
    return {"status": "OK", "service": "OLLBUD backend"}
