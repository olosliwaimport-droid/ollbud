# app/chat_agent.py
from typing import List, Dict, Any, Optional
from pydantic import BaseModel
from openai import OpenAI
from app.pricing import estimate_offer

client = OpenAI()  # używa OPENAI_API_KEY z env

SYSTEM_PROMPT = (
    "Jesteś asystentem firmy OLLBUD. "
    "Prowadzisz uprzejmą rozmowę o szybkiej wycenie prac remontowych/wykończeniowych. "
    "Dopytujesz krótko tylko o potrzebne rzeczy (typ obiektu: blok/kamienica/dom/deweloperski, powierzchnia m², zakres), "
    "a gdy masz metraż i standard, wywołujesz narzędzie estimate_offer. "
    "Mów po polsku, zwięźle, bez kwot brutto (tylko netto + stawka VAT)."
)

# Narzędzie („function”) widoczne dla modelu
TOOLS = [
    {
        "type": "function",
        "function": {
            "name": "estimate_offer",
            "description": "Policz orientacyjny koszt na podstawie powierzchni i standardu.",
            "parameters": {
                "type": "object",
                "properties": {
                    "area_m2": {"type": "number", "description": "Powierzchnia w m2"},
                    "standard": {
                        "type": "string",
                        "enum": ["blok", "kamienica", "dom", "deweloperski"],
                        "description": "Typ/standard prac"
                    }
                },
                "required": ["area_m2", "standard"]
            }
        }
    }
]

class ChatTurn(BaseModel):
    role: str
    content: str

def run_chat_agent(history: List[ChatTurn]) -> Dict[str, Any]:
    """
    Przyjmuje historię czatu (user/assistant), zwraca:
    - gdy model odpowie tekstem -> {"reply": "..."}
    - gdy model wezwie narzędzie -> uruchamia estimate_offer() i zwraca wynik w odpowiedzi
    """
    # budujemy wiadomości (doklejamy system prompt na czele)
    messages = [{"role": "system", "content": SYSTEM_PROMPT}] + [
        {"role": t.role, "content": t.content} for t in history
    ]

    # 1. Zapytaj model z tools
    resp = client.chat.completions.create(
        model="gpt-4o-mini",
        messages=messages,
        tools=TOOLS,
        tool_choice="auto",
        temperature=0.3
    )

    choice = resp.choices[0]
    msg = choice.message

    # 2. Jeżeli model chce wywołać narzędzie
    if msg.tool_calls:
        for tool in msg.tool_calls:
            if tool.function.name == "estimate_offer":
                import json
                args = json.loads(tool.function.arguments or "{}")
                area_m2 = float(args.get("area_m2", 0))
                standard = (args.get("standard") or "blok").lower()

                # lokalne wywołanie funkcji (bez requestu HTTP)
                result = estimate_offer(area_m2, standard)

                # 3. Daj modelowi wynik narzędzia, by ładnie sformułował odpowiedź
                messages += [
                    {"role": "assistant", "content": None, "tool_calls": [tool.dict()]},
                    {
                        "role": "tool",
                        "tool_call_id": tool.id,
                        "name": "estimate_offer",
                        "content": str(result)
                    },
                ]

followup = client.chat.completions.create(
    model="gpt-4o-mini",
    messages=messages,
    temperature=0.3
)

reply_text = followup.choices[0].message.content.strip()

# Dopisz informację końcową o wizji lokalnej
reply_text += (
    "\n\n📍 *Dokładna wycena możliwa jest po wizji lokalnej.* "
    "Koszt wizji lokalnej wynosi **od 400 do 1250 zł netto**, "
    "w zależności od zakresu inwestycji.\n"
    "Dziękujemy za uwagę i do zobaczenia!"
)

return {"reply": reply_text, "raw": result}

    # 4. Zwykła odpowiedź
    return {"reply": msg.content}
