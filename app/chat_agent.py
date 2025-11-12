# app/chat_agent.py
import json
import logging
from typing import List, Dict, Any, Tuple

from pydantic import BaseModel
from openai import OpenAI

from app.pricing import estimate_offer
from app.knr import find_knr_items

client = OpenAI()

logger = logging.getLogger(__name__)

SYSTEM_PROMPT = (
    "Jesteś asystentem firmy OLLBUD. Rozmawiasz po polsku. "
    "Dopytujesz tylko o kluczowe informacje. "
    "Gdy użytkownik podaje konkretne prace (np. 'malowanie ścian 120 m2', 'montaż paneli 60 m2'), "
    "użyj narzędzia get_knr_rate, aby przytoczyć KNR (w tym RG i ewentualną jednostkę). "
    "Zawsze zwracaj łączny nakład robocizny (RG) jeśli podano ilość. "
    "Gdy masz metraż całego zlecenia i typ/standard (blok/kamienica/dom/deweloperski/budowa domu), "
    "wywołaj estimate_offer i przedstaw widełki. "
    "Na końcu przypominaj o kosztach przygotowania wyceny."
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
                        "enum": ["blok", "kamienica", "dom", "deweloperski", "budowa", "budowa domu"]
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
            "description": (
                "Wyszukaj pozycje KNR po opisie i zwróć top dopasowania z RG i jednostką. "
                "Jeśli podano ilość, policz RG łącznie."
            ),
            "parameters": {
                "type": "object",
                "properties": {
                    "query": {
                        "type": "string",
                        "description": "Opis prac, np. 'malowanie ścian'"
                    },
                    "ilosc": {
                        "type": "number",
                        "description": "Ilość w jednostkach z KNR (np. m2, m, szt.)",
                        "nullable": True
                    }
                },
                "required": ["query"]
            }
        }
    }
]

COST_NOTE = (
    "📍 *Koszt przygotowania wyceny:* "
    "\n– **499 PLN brutto** w strefie pomarańczowej," 
    "\n– **619 PLN brutto** w strefie czerwonej," 
    "\n– **929 PLN brutto** w strefie czarnej." 
    "\n\nW przypadku wycen dotyczących **budowy domu** obowiązuje dodatkowa stawka "
    "**615 PLN brutto**, doliczana do kwoty podstawowej." 
    "\n\nDziękujemy za uwagę i do zobaczenia!"
)

FALLBACK_REPLY = (
    "Przepraszam, mam teraz trudności z połączeniem z agentem GPT. "
    "Spróbuj proszę ponownie za kilka minut. Jeżeli problem się powtarza, "
    "daj nam znać na biuro@ollbud.pl lub pod numerem infolinii – sprawdzimy to od ręki."
)


def _with_cost_note(text: str) -> str:
    text = (text or "").strip()
    if not text:
        return COST_NOTE
    return f"{text}\n\n{COST_NOTE}"


def _error_reply(exc: Exception) -> Dict[str, Any]:
    logger.error("Błąd podczas komunikacji z modelem GPT", exc_info=exc)
    return {"reply": FALLBACK_REPLY}


def _format_tool_fallback(executed_tools: List[Tuple[str, Any]]) -> str:
    if not executed_tools:
        return ""

    sections: List[str] = []
    for name, result in executed_tools:
        if name == "estimate_offer" and isinstance(result, dict):
            parts = [
                "Oto dane z kalkulacji, którą udało się policzyć:",
                f"• Typ prac: {result.get('typ_prac', '—')}",
                f"• Powierzchnia: {result.get('powierzchnia_m2', '—')} m²",
                f"• Koszt od: {result.get('suma_od', '—')} PLN",
                f"• Koszt do: {result.get('suma_do', '—')} PLN",
                f"• VAT: {result.get('stawka_VAT', '—')}",
            ]
            sections.append("\n".join(parts))
        elif name == "get_knr_rate" and isinstance(result, list):
            if not result:
                sections.append("Nie znaleziono pozycji KNR dla podanego zapytania.")
                continue

            lines = ["Najlepsze dopasowania KNR:"]
            for item in result[:3]:
                kod = item.get("kod") or "—"
                nazwa = item.get("nazwa") or "—"
                jednostka = item.get("jednostka") or "—"
                rg_total = item.get("RG_total")
                rg_text = (
                    f", łączny nakład: {rg_total} RG" if rg_total is not None else ""
                )
                lines.append(f"• {kod}: {nazwa} ({jednostka}{rg_text})")
            sections.append("\n".join(lines))

    if not sections:
        return ""

    sections.append(
        "Przepraszam, nie udało się jednak wygenerować pełnej odpowiedzi. "
        "Spróbuj proszę ponownie za chwilę lub skontaktuj się z nami – pomożemy."
    )
    return "\n\n".join(sections)


class ChatTurn(BaseModel):
    role: str
    content: str


def run_chat_agent(history: List[ChatTurn]) -> Dict[str, Any]:
    messages = [{"role": "system", "content": SYSTEM_PROMPT}] + [
        {"role": t.role, "content": t.content} for t in history
    ]

    try:
        resp = client.chat.completions.create(
            model="gpt-4o-mini",
            messages=messages,
            tools=TOOLS,
            tool_choice="auto",
            temperature=0.2
        )
    except Exception as exc:  # pragma: no cover - sieć może być niedostępna w testach
        return _error_reply(exc)

    msg = resp.choices[0].message

    # Obsługa wywołań narzędzi (KNR, wycena)
    if msg.tool_calls:
        tool_messages = []
        executed_tools: List[Tuple[str, Any]] = []
        for call in msg.tool_calls:
            name = call.function.name
            try:
                args = json.loads(call.function.arguments or "{}")
            except json.JSONDecodeError as exc:
                logger.error("Nie udało się sparsować argumentów narzędzia %s", name, exc_info=exc)
                return _error_reply(exc)

            if name == "estimate_offer":
                area = float(args.get("area_m2", 0))
                standard = (args.get("standard") or "blok").lower()
                try:
                    result = estimate_offer(area, standard)
                except Exception as exc:  # pragma: no cover - defensywne logowanie
                    return _error_reply(exc)
                executed_tools.append((name, result))
                tool_messages.append({
                    "role": "tool",
                    "tool_call_id": call.id,
                    "name": "estimate_offer",
                    "content": str(result)
                })

            elif name == "get_knr_rate":
                query = args.get("query") or ""
                ilosc = args.get("ilosc")
                try:
                    knrs = find_knr_items(query, top_n=5, ilosc=ilosc)
                except Exception as exc:  # pragma: no cover - np. brak pliku KNR
                    return _error_reply(exc)
                executed_tools.append((name, knrs))
                tool_messages.append({
                    "role": "tool",
                    "tool_call_id": call.id,
                    "name": "get_knr_rate",
                    "content": str(knrs)
                })

        # Druga runda – formatowanie końcowej odpowiedzi
        try:
            follow = client.chat.completions.create(
                model="gpt-4o-mini",
                messages=messages
                + [{"role": "assistant", "content": None, "tool_calls": msg.tool_calls}]
                + tool_messages,
                temperature=0.2
            )
            reply = (follow.choices[0].message.content or "").strip()
            return {"reply": _with_cost_note(reply)}
        except Exception as exc:  # pragma: no cover - fallback dla problemów sieciowych
            fallback = _format_tool_fallback(executed_tools)
            if fallback:
                return {"reply": _with_cost_note(fallback)}
            return _error_reply(exc)

        # return occurs above on success/fallback

    # Zwykła odpowiedź (bez wywołania narzędzi)
    reply = (msg.content or "").strip()
    return {"reply": _with_cost_note(reply)}
