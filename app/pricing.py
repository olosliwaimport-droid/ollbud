def estimate_offer(area_m2: float, standard: str):
    """
    Szacuje koszt robót dla danego metrażu i standardu.
    Zasady:
    - robocizna bazowa zależna od typu (blok, kamienica, deweloperski, budowa domu)
    - narzut 42.5%
    - VAT 8% dla mieszkań ≤150 m² i domów ≤300 m², inaczej 23%
    """
    std = standard.lower()
    # --- stawki bazowe (robocizna netto, bez narzutów) ---
    if "budowa" in std or "dom" in std:
        base_rate = 1900  # z materiałami, stan surowy otwarty z dachem
        type_name = "budowa domu"
    elif "kamienica" in std:
        base_rate = 1200
        type_name = "kamienica"
    elif "deweloperski" in std:
        base_rate = 900
        type_name = "deweloperski"
    else:
        base_rate = 1000
        type_name = "blok"

    # --- robocizna ---
    labor_min = area_m2 * base_rate
    labor_max = labor_min * 1.30  # +30% w górę

    # --- narzut 42.5% ---
    labor_min *= 1.425
    labor_max *= 1.425

    # --- materiały ---
    if "budowa" in std or "dom" in std:
        # budowa: stawka już zawiera materiały
        materials_min = 0
        materials_max = 0
    else:
        materials_min = labor_min * 0.6 * 1.425
        materials_max = labor_min * 1.5 * 1.425

    # --- suma ---
    total_min = labor_min + materials_min
    total_max = labor_max + materials_max

    # --- VAT ---
    if "budowa" in std or "dom" in std:
        vat_rate = "8%" if area_m2 <= 300 else "23%"
    else:
        vat_rate = "8%" if area_m2 <= 150 else "23%"

    # ← ZWRACAMY wynik
    return {
        "typ_prac": type_name,
        "powierzchnia_m2": area_m2,
        "robocizna_od": round(labor_min, 2),
        "robocizna_do": round(labor_max, 2),
        "materiały_od": round(materials_min, 2),
        "materiały_do": round(materials_max, 2),
        "suma_od": round(total_min, 2),
        "suma_do": round(total_max, 2),
        "stawka_VAT": vat_rate
    }
