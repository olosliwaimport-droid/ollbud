def estimate_offer(area_m2: float, standard: str):
    """Przygotuj widełki kosztów z materiałami."""

    std = standard.lower()
    is_house = "budowa" in std or "dom" in std

    # --- stawki bazowe (netto, przed narzutem) ---
    if is_house:
        base_rate = 1900  # stawka całkowita za m² stanu surowego otwartego z dachem
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

    # --- materiały + suma ---
    if is_house:
        # Stawka bazowa zawiera materiały – rozdzielamy koszty przy założeniu,
        # że ~55% przypada na materiały dla SSO.
        total_min = labor_min
        total_max = labor_max
        material_share = 0.55
        materials_min = total_min * material_share
        materials_max = total_max * material_share
        labor_min = total_min - materials_min
        labor_max = total_max - materials_max
    else:
        materials_min = labor_min * 0.6 * 1.425
        materials_max = labor_min * 1.5 * 1.425
        total_min = labor_min + materials_min
        total_max = labor_max + materials_max

    # --- VAT ---
    if is_house:
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
