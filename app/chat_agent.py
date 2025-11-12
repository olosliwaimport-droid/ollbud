# app/chat_agent.py (fragmenty kluczowe)
from typing import List, Dict, Any
from pydantic import BaseModel
from openai import OpenAI
from app.pricing import estimate_offer
from app.knr import find_knr_items

client = OpenAI()

SYSTEM_PROMPT = (
    "Jesteś asystentem firmy OLLBUD. Rozmawiasz po polsku. "
    "Dopytujesz tylko o kluczowe informacje. "
    "Gdy użytkownik podaje konkretne prace (np. 'malowanie ścian 120 m2', 'montaż paneli 60 m2'), "
    "użyj narzędzia get_knr_rate, aby przytoczyć KNR (w tym RG i ewentualną jednostkę). "
    "Zawsze zwracaj łączny nakład robocizny (RG) jeśli podano ilość. "
    "Gdy masz metraż całego zlecenia i typ/standard (blok/kamienica/dom/deweloperski/budowa domu), "
    "wywołaj estimate_offer i przedstaw widełki. "
    "Na końcu przypominaj o wizji lokalnej (400–1250 zł netto)."
)

TOOLS = [
    {
        "type": "function",
        "function": {
            "name": "estimate_offer",
            "description": "Policz orientacyjny koszt całego zlecenia (area_m2, standard).",
            "parameters": {
                "type": "object",
                "properties": {
                    "area_m2": {"type": "number"},
                    "standard": {
                        "type": "string",
                        "enum": ["blok","kamienica","dom","deweloperski","budowa","budowa domu"]
                    }
                },
                "required": ["area_m2", "standard"]
            }
        }
    },
    {
        "type": "function",
        "function": {
            "name": "get_knr_rate",
            "description": "Wyszukaj pozycje KNR po opisie i zwróć top dopasowania z RG i jednostką. Jeśli podano ilość, policz RG łącznie.",
            "parameters": {
                "type": "object",
                "properties": {
                    "query": {"type":"string", "description":"Opis prac, np. 'malowanie ścian'"},
                    "ilosc": {"type":"number", "description":"Ilość w jednostkach z KNR (np. m2, m, szt.)", "nullable": True}
                },
                "required": ["query"]
            }
        }
    }
]

class ChatTurn(BaseModel):
    role: str
    content: str

def run_chat_agent(history: List[ChatTurn]) -> Dict[str, Any]:
    messages = [{"role":"system","content":SYSTEM_PROMPT}] + [
        {"role":t.role, "content":t.content} for t in history
    ]

    resp = client.chat.completions.create(
        model="gpt-4o-mini",
        messages=messages,
        tools=TOOLS,
        tool_choice="auto",
        temperature=0.2
    )

    msg = resp.choices[0].message

    # Obsługa tool calls (może być kilka)
    if msg.tool_calls:
        tool_messages = []
        for call in msg.tool_calls:
            name = call.function.name
            import json
            args = json.loads(call.function.arguments or "{}")

            if name == "estimate_offer":
                area = float(args.get("area_m2", 0))
                standard = (args.get("standard") or "blok").lower()
                result = estimate_offer(area, standard)
                tool_messages.append({
                    "role":"tool",
                    "tool_call_id": call.id,
                    "name": "estimate_offer",
                    "content": str(result)
                })

            elif name == "get_knr_rate":
                query = args.get("query") or ""
                ilosc = args.get("ilosc")
                knrs = find_knr_items(query, top_n=5, ilosc=ilosc)
                tool_messages.append({
                    "role":"tool",
                    "tool_call_id": call.id,
                    "name": "get_knr_rate",
                    "content": str(knrs)
                })

        # Daj modelowi wyniki narzędzi do sformatowania w zwięzłą odpowiedź
        follow = client.chat.completions.create(
            model="gpt-4o-mini",
            messages=messages + [{"role":"assistant","content":None,"tool_calls":msg.tool_calls}] + tool_messages,
            temperature=0.2
        )
        reply = (follow.choices[0].message.content or "").strip()
        # Dopisek o wizji lokalnej (stały)
        reply += (
            "\n\n📍 *Dokładna wycena możliwa jest po wizji lokalnej.* "
            "Koszt wizji lokalnej: **400–1250 zł netto**.\n"
            "Dziękujemy za uwagę i do zobaczenia!"
        )
        return {"reply": reply}

    # Zwykła odpowiedź
    reply = (msg.content or "").strip()
reply += (
    "\n\n📍 *Koszt przygotowania wyceny:* "
    "\n– **499 PLN brutto** w strefie pomarańczowej,"
    "\n– **619 PLN brutto** w strefie czerwonej,"
    "\n– **929 PLN brutto** w strefie czarnej."
    "\n\nW przypadku wycen dotyczących **budowy domu** obowiązuje dodatkowa stawka "
    "**615 PLN brutto**, doliczana do kwoty podstawowej."
    "\n\nDziękujemy za uwagę i do zobaczenia!"
)
    return {"reply": reply}
