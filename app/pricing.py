import pandas as pd
import os

def estimate_offer(area_m2, standard):
    """Oblicza szacunkową wycenę na podstawie KNR lub widełek."""
    knr_path = os.path.join("data", "knr.xlsx")
    robocizna_min = 900
    robocizna_mid = 1000
    robocizna_high = 1200

    # --- Wybór stawek wg standardu ---
    if standard.lower() == "deweloperski":
        base_rate = robocizna_min
    elif standard.lower() == "kamienica":
        base_rate = robocizna_high
    else:
        base_rate = robocizna_mid

    robocizna = base_rate * area_m2

    # --- Widełki materiałowe ---
    mat_min = robocizna * 0.6
    mat_max = robocizna * 1.5

    # --- Narzut ---
    narzut = 1.425
    total_min = (robocizna + mat_min) * narzut
    total_max = (robocizna + mat_max) * narzut

    # --- VAT ---
    vat = "8%" if area_m2 <= 150 else "23%"

    # --- Jeśli plik KNR istnieje, wczytaj dane (na przyszłość) ---
    if os.path.exists(knr_path):
        try:
            df = pd.read_excel(knr_path)
        except Exception as e:
            print(f"Błąd odczytu KNR: {e}")

    return {
        "powierzchnia_m2": area_m2,
        "standard": standard,
        "robocizna": round(robocizna),
        "materiały_od": round(mat_min),
        "materiały_do": round(mat_max),
        "suma_od": round(total_min),
        "suma_do": round(total_max),
        "stawka_VAT": vat
    }
