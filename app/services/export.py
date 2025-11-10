from typing import Dict, Any, Tuple
from jinja2 import Environment, FileSystemLoader, select_autoescape
from datetime import datetime


try:
from weasyprint import HTML
WEASY_AVAILABLE = True
except Exception:
WEASY_AVAILABLE = False


env = Environment(
loader=FileSystemLoader("templates"),
autoescape=select_autoescape(["html", "xml"]),
)


def render_offer_pdf(payload) -> Tuple[bytes, str]:
template = env.get_template("offer.html")
html = template.render(summary=payload.summary, pricing=payload.pricing, generated_at=datetime.now())


if not WEASY_AVAILABLE:
# Fallback: zwróć prosty PDF-like w bajtach tekstowych (nieprawdziwy PDF) – dla dev
# Rekomendacja: zainstalować WeasyPrint w środowisku produkcyjnym.
return html.encode("utf-8"), f"OLL_BUD_oferta_{datetime.now().date()}.pdf"


pdf_bytes = HTML(string=html).write_pdf()
return pdf_bytes, f"OLL_BUD_oferta_{datetime.now().date()}.pdf"


def render_offer_txt(payload) -> Tuple[str, str]:
s, p = payload.summary, payload.pricing
content = (
"OLLBUD – szkic oferty (wstępny)\n\n"
f"Zakres: {s.get('scope')}\n"
f"Metraż: {s.get('area_m2')} m²\n"
f"Standard: {s.get('standard')}\n"
f"Lokalizacja: {s.get('location')}\n"
f"Termin: {s.get('deadline')}\n\n"
f"Wycena orientacyjna: {p.get('total')} {p.get('currency', 'PLN')} (w tym bufor: {p.get('buffer')})\n"
"Uwaga: dokument wygenerowany automatycznie – wymaga weryfikacji po wizji lokalnej.\n"
)
return content, f"OLL_BUD_szkic_{datetime.now().date()}.txt"