# app/pricing.py
from __future__ import annotations
from dataclasses import dataclass
from functools import lru_cache
from typing import Optional, List, Dict
import os

# Opcjonalne: pandas+openpyxl do wczytania KNR
try:
    import pandas as pd  # type: ignore
except Exception:
    pd = None  # jeśli brak, kalkulator zadziała na stawkach bazowych

KNR_PATH = os.getenv("KNR_XLSX_PATH", "data/knr.xlsx")

@dataclass
class KNRItem:
    code: str
    name: str
    unit: str
    rg_per_unit: float  # r-g na jednostkę
    mat_per_unit: float # materiały na jednostkę (PLN) lub współczynnik – zależnie od arkusza
    eq_per_unit: float  # sprzęt na jednostkę (PLN) – opcjonalnie

def _coerce_float(x) -> float:
    try:
        if isinstance(x, str):
            x = x.replace(",", ".")
        return float(x)
    except Exception:
        return 0.0

@lru_cache(maxsize=1)
def load_knr() -> List[KNRItem]:
    """Ładuje dane KNR z pliku XLSX (jeśli jest). Zwraca listę pozycji.
    Oczekiwane kolumny (nagłówki mogą się różnić – spróbujemy je wykryć):
      - code / Kod
      - name / Opis
      - unit / Jm
      - rg (r-g na jednostkę)
      - mat (PLN lub współczynnik)
      - eq (PLN, opcjonalne)
    Jeśli nie ma pliku albo brak pandas – zwracamy pustą listę.
    """
    if not pd:
        return []
    if not os.path.exists(KNR_PATH):
        return []
    try:
        df = pd.read_excel(KNR_PATH, engine="openpyxl")  # wymaga openpyxl
    except Exception:
        return []

    # Probujemy zmapować kolumny
    cols = {c.lower(): c for c in df.columns}
    def pick(*cands):
        for c in cands:
            for k,v in cols.items():
                if c in k:
                    return v
        return None

    c_code = pick("kod", "code", "knr")
    c_name = pick("opis", "name")
    c_unit = pick("jm", "unit")
    c_rg   = pick("r-g", "rg", "robocz", "robociz")
    c_mat  = pick("mat", "mater")
    c_eq   = pick("sprz", "equip", "eq")

    items: List[KNRItem] = []
    for _, row in df.iterrows():
        code = str(row.get(c_code, "")).strip() if c_code else ""
        name = str(row.get(c_name, "")).strip() if c_name else ""
        unit = str(row.get(c_unit, "")).strip() if c_unit else ""
        rg   = _coerce_float(row.get(c_rg, 0.0)) if c_rg else 0.0
        mat  = _coerce_float(row.get(c_mat, 0.0)) if c_mat else 0.0
        eq   = _coerce_float(row.get(c_eq, 0.0)) if c_eq else 0.0
        if code or name:
            items.append(KNRItem(code, name, unit, rg, mat, eq))
    return items

def suggest_vat(property_kind: str, total_area_m2: float) -> int:
    """Zwraca 8 lub 23 zgodnie z regułami."""
    kind = (property_kind or "").lower()
    # Mieszkalne domy/mieszkania – próg 8%
    if ("miesz" in kind or "dom" in kind or "lokal mieszkalny" in kind):
        # Przyjmujemy: mieszkania <=150, domy <=300 – 8%
        if "dom" in kind:
            return 8 if total_area_m2 <= 300 else 23
        return 8 if total_area_m2 <= 150 else 23
    # wszystko inne – 23%
    return 23

# Bazowe stawki robocizny OLLBUD (z netto/m2)
BASE_LABOR = {
    "blok":        1000.0,
    "kamienica":   1200.0,
    "deweloperka":  900.0
}

def pick_base_labor(building_type: str) -> float:
    return BASE_LABOR.get((building_type or "").lower(), 1000.0)

@dataclass
class EstimateResult:
    labor_min: float
    labor_max: float
    mat_min: float
    mat_max: float
    subtotal_min: float
    subtotal_max: float
    vat_rate: int
    total_min: float
    total_max: float

def estimate_ollbud(
    area_m2: float,
    building_type: str,               # "blok" | "kamienica" | "deweloperka"
    property_kind: str = "mieszkanie",# do VAT (np. "mieszkanie", "dom", "lokal użytkowy")
    total_area_m2: Optional[float] = None,
    use_knr: bool = True
) -> EstimateResult:
    """
    Liczenie wg zasad OLLBUD:
      - robocizna: baza * m2, widełki 100%..135%
      - materiały: 60%..150% robocizny
      - narzut 42,5% na robociznę i na materiały
      - VAT 8%/23% wg reguł
    Jeżeli dostępne KNR i będziemy chcieli w przyszłości korygować stawki,
    tu jest miejsce na ich uwzględnienie (np. korekty współczynników).
    """
    area_m2 = max(0.0, float(area_m2))
    total_area = float(total_area_m2 or area_m2)

    # 1) robocizna bazowa
    base = pick_base_labor(building_type)
    labor_min = base * area_m2                 # 100%
    labor_max = base * 1.35 * area_m2          # +35%

    # 2) materiały – 60%..150% robocizny
    mat_min = labor_min * 0.60
    mat_max = labor_max * 1.50

    # 3) narzut OLLBUD 42,5% – oddzielnie na robociznę i materiały
    narzut = 1.425
    labor_min *= narzut
    labor_max *= narzut
    mat_min   *= narzut
    mat_max   *= narzut

    # 4) suma netto
    subtotal_min = labor_min + mat_min
    subtotal_max = labor_max + mat_max

    # 5) VAT
    vat_rate = suggest_vat(property_kind, total_area)
    vat_mult  = 1 + vat_rate / 100
    total_min = subtotal_min * vat_mult
    total_max = subtotal_max * vat_mult

    return EstimateResult(
        labor_min=round(labor_min),
        labor_max=round(labor_max),
        mat_min=round(mat_min),
        mat_max=round(mat_max),
        subtotal_min=round(subtotal_min),
        subtotal_max=round(subtotal_max),
        vat_rate=vat_rate,
        total_min=round(total_min),
        total_max=round(total_max),
    )
