# app/knr.py
from __future__ import annotations
import os
from dataclasses import dataclass, asdict
from typing import List, Optional, Tuple
import pandas as pd
from rapidfuzz import process, fuzz

_KNR_DF: Optional[pd.DataFrame] = None

# DOPASUJ TU NAZWY KOLUMN DO SWOJEGO XLSX:
# Minimalnie potrzebujemy: 'nazwa', 'jednostka', 'R' (roboczogodziny / jednostkę).
# Opcjonalnie: 'kod', 'M', 'S', 'Cena_jedn'
COLUMN_MAP = {
    "kod": "kod",                # np. "KNR 2-15 0101-01"
    "nazwa": "nazwa",            # opis pozycji
    "jednostka": "jednostka",    # np. "m2", "m", "szt."
    "R": "R",                    # roboczogodziny na jednostkę
    "M": "M",                    # materiały (opcjonalnie)
    "S": "S",                    # sprzęt (opcjonalnie)
    "Cena_jedn": "Cena_jedn",    # cena jednostkowa RMS (opcjonalnie)
}

KNR_PATH = os.getenv("KNR_PATH", "data/knr.xlsx")  # możesz nadpisać w ENV
RG_RATE_MIN = 100.0  # PLN netto / RG
RG_RATE_MAX = 180.0
NARZUT = 0.425


def _calc_cost_range(rg_value: Optional[float]) -> Tuple[Optional[float], Optional[float]]:
    if rg_value is None:
        return None, None
    net_min = float(rg_value) * RG_RATE_MIN
    net_max = float(rg_value) * RG_RATE_MAX
    multiplier = 1 + NARZUT
    return net_min * multiplier, net_max * multiplier

@dataclass
class KNRItem:
    kod: Optional[str]
    nazwa: str
    jednostka: Optional[str]
    R: Optional[float]
    M: Optional[float]
    S: Optional[float]
    Cena_jedn: Optional[float]
    score: float                # trafność dopasowania 0-100
    RG_total: Optional[float]   # RG po uwzględnieniu ilości (jeśli podano)
    ilosc: Optional[float]
    koszt_od: Optional[float]
    koszt_do: Optional[float]
    koszt_jedn_od: Optional[float]
    koszt_jedn_do: Optional[float]

    def to_dict(self):
        d = asdict(self)
        # Porządkuj liczby do 4 miejsc po przecinku
        for k in [
            "R",
            "M",
            "S",
            "Cena_jedn",
            "RG_total",
            "ilosc",
            "koszt_od",
            "koszt_do",
            "koszt_jedn_od",
            "koszt_jedn_do",
        ]:
            if d.get(k) is not None:
                d[k] = round(float(d[k]), 4)
        return d

def _load_knr() -> pd.DataFrame:
    global _KNR_DF
    if _KNR_DF is not None:
        return _KNR_DF
    if not os.path.exists(KNR_PATH):
        raise FileNotFoundError(f"Nie znaleziono pliku KNR pod ścieżką: {KNR_PATH}")
    df = pd.read_excel(KNR_PATH)

    # Normalizacja nazw kolumn -> zgodnie z COLUMN_MAP
    rename_map = {}
    for std_name, real_name in COLUMN_MAP.items():
        if real_name in df.columns:
            rename_map[real_name] = std_name
    df = df.rename(columns=rename_map)

    # Minimalna walidacja
    for req in ["nazwa", "jednostka", "R"]:
        if req not in df.columns:
            raise ValueError(f"Brakuje wymaganej kolumny '{req}' w pliku KNR ({KNR_PATH}).")

    # Ujednolicenia
    df["nazwa"] = df["nazwa"].astype(str)
    df["jednostka"] = df["jednostka"].astype(str).str.strip().str.lower()
    for col in ["R", "M", "S", "Cena_jedn"]:
        if col in df.columns:
            df[col] = pd.to_numeric(df[col], errors="coerce")

    _KNR_DF = df
    return _KNR_DF

def find_knr_items(query: str, top_n: int = 5, ilosc: Optional[float] = None) -> List[dict]:
    """
    Fuzzy-match po 'nazwa' i zwróć najlepsze trafienia wraz z RG_total (jeśli jest 'ilosc').
    """
    df = _load_knr()
    choices = df["nazwa"].tolist()
    matches = process.extract(
        query, choices, scorer=fuzz.WRatio, limit=top_n
    )
    results: List[KNRItem] = []
    for name, score, idx in matches:
        row = df.iloc[idx]
        R = row.get("R")
        RG_total = float(R) * float(ilosc) if (R is not None and ilosc is not None) else None
        koszt_od, koszt_do = _calc_cost_range(RG_total)
        koszt_jedn_od, koszt_jedn_do = _calc_cost_range(float(R) if R is not None else None)

        item = KNRItem(
            kod=row.get("kod") if "kod" in df.columns else None,
            nazwa=row["nazwa"],
            jednostka=row.get("jednostka"),
            R=float(R) if R is not None else None,
            M=float(row.get("M")) if "M" in df.columns else None,
            S=float(row.get("S")) if "S" in df.columns else None,
            Cena_jedn=float(row.get("Cena_jedn")) if "Cena_jedn" in df.columns else None,
            score=float(score),
            RG_total=RG_total,
            ilosc=float(ilosc) if ilosc is not None else None,
            koszt_od=koszt_od,
            koszt_do=koszt_do,
            koszt_jedn_od=koszt_jedn_od,
            koszt_jedn_do=koszt_jedn_do,
        )
        results.append(item)

    return [r.to_dict() for r in results]
