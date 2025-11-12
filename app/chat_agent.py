# app/chat_agent.py
import json
import logging
import os
from typing import List, Dict, Any, Tuple, Optional

from pydantic import BaseModel
from openai import OpenAI

from app.pricing import estimate_offer
from app.knr import find_knr_items


_client: Optional[OpenAI] = None

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

MISSING_API_KEY_HINT = (
    "Wygląda na to, że środowisko serwera nie ma skonfigurowanego klucza OPENAI_API_KEY do "
    "rozmowy z GPT."
)


def _with_cost_note(text: str) -> str:
    text = (text or "").strip()
    if not text:
        return COST_NOTE
    return f"{text}\n\n{COST_NOTE}"


def _get_openai_client() -> OpenAI:
    """Lazy init to surface brakujący klucz API jako kontrolowany błąd."""

    global _client
    if _client is not None:
        return _client

    api_key = os.getenv("OPENAI_API_KEY")
    if not api_key:
        raise RuntimeError(
            "Brak zmiennej środowiskowej OPENAI_API_KEY potrzebnej do połączenia z GPT"
        )

    base_url = os.getenv("OPENAI_BASE_URL")
    if base_url:
        _client = OpenAI(api_key=api_key, base_url=base_url)
    else:
        _client = OpenAI(api_key=api_key)
    return _client


def _error_reply(exc: Exception, hint: Optional[str] = None) -> Dict[str, Any]:
    logger.error("Błąd podczas komunikacji z modelem GPT", exc_info=exc)
    message = FALLBACK_REPLY
    if hint:
        message = f"{hint}\n\n{message}"
    return {"reply": message}


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


def _format_pln(value: Optional[float]) -> str:
    if value is None:
        return "—"
    formatted = f"{value:,.2f}".replace(",", " ").replace(".", ",")
    return f"{formatted} PLN"


def _compose_quick_reply(history: List[ChatTurn], executed_tools: List[Tuple[str, Any]]) -> Optional[str]:
    estimate: Optional[Dict[str, Any]] = None
    knr_items: Optional[List[Dict[str, Any]]] = None

    for name, payload in executed_tools:
        if name == "estimate_offer" and isinstance(payload, dict):
            estimate = payload
        elif name == "get_knr_rate" and isinstance(payload, list):
            knr_items = payload

    sections: List[str] = []

    if estimate:
        sections.append(
            "\n".join(
                [
                    "Szacunkowe widełki kosztów:",
                    f"• Typ zlecenia: {estimate.get('typ_prac', '—')}",
                    f"• Powierzchnia: {estimate.get('powierzchnia_m2', '—')} m²",
                    f"• Robocizna: {_format_pln(estimate.get('robocizna_od'))} – "
                    f"{_format_pln(estimate.get('robocizna_do'))}",
                    f"• Materiały: {_format_pln(estimate.get('materiały_od'))} – "
                    f"{_format_pln(estimate.get('materiały_do'))}",
                    f"• Łącznie: {_format_pln(estimate.get('suma_od'))} – "
                    f"{_format_pln(estimate.get('suma_do'))}",
                    f"• VAT: {estimate.get('stawka_VAT', '—')}",
                ]
            )
        )

    if knr_items is not None:
        if not knr_items:
            sections.append("Nie znalazłem dopasowanych pozycji KNR dla podanego opisu.")
        else:
            lines = ["Najlepsze dopasowania KNR:"]
            for item in knr_items[:3]:
                kod = item.get("kod") or "—"
                nazwa = item.get("nazwa") or "—"
                jednostka = item.get("jednostka") or "—"
                rg_total = item.get("RG_total")
                if rg_total is not None:
                    rg_text = f", łączny nakład: {round(float(rg_total), 2)} RG"
                else:
                    rg_text = ""
                lines.append(f"• {kod}: {nazwa} ({jednostka}{rg_text})")
            sections.append("\n".join(lines))

    if not sections:
        return None

    last_user = next((t.content.strip() for t in reversed(history) if t.role == "user"), "")
    header = "Przygotowałem szybką odpowiedź na Twoje zgłoszenie."
    if last_user:
        trimmed = last_user if len(last_user) <= 120 else last_user[:117] + "..."
        header = f"Na podstawie wiadomości: \"{trimmed}\" przygotowałem podsumowanie."

    sections.insert(0, header)
    sections.append("Daj proszę znać, jeśli chcesz doprecyzować zakres lub potrzebujesz czegoś jeszcze.")

    return _with_cost_note("\n\n".join(sections))


class ChatTurn(BaseModel):
    role: str
    content: str


def run_chat_agent(history: List[ChatTurn]) -> Dict[str, Any]:
    try:
        client = _get_openai_client()
    except RuntimeError as exc:
        return _error_reply(exc, hint=MISSING_API_KEY_HINT)
    except Exception as exc:  # pragma: no cover - inne błędy inicjalizacji klienta
        return _error_reply(exc)

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

        quick_reply = _compose_quick_reply(history, executed_tools)
        if quick_reply is not None:
            return {"reply": quick_reply}

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
