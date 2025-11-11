def estimate_offer(area_m2: float, standard: str):
    """
    Szacuje koszt robót dla danego metrażu i standardu.
    """

    # --- stawki bazowe ---
    if standard.lower() == "kamienica":
        base_rate = 1200
    elif standard.lower() == "deweloperski":
        base_rate = 900
    elif standard.lower() == "dom":
        base_rate = 1000
    else:
        base_rate = 1000

    # --- robocizna ---
    labor_min = area_m2 * base_rate
    labor_max = labor_min * 1.35

    # --- narzut 42.5% ---
    labor_min *= 1.425
    labor_max *= 1.425

    # --- materiały ---
    materials_min = labor_min * 0.3 * 1.425
    materials_max = labor_min * 1.5 * 1.425

    # --- suma ---
    total_min = labor_min + materials_min
    total_max = labor_max + materials_max

    # --- VAT ---
    std = standard.lower()
    if std == "dom":
        vat_rate = "8%" if area_m2 <= 300 else "23%"
    else:
        vat_rate = "8%" if area_m2 <= 150 else "23%"

    # --- wynik ---
    return {
        "powierzchnia_m2": area_m2,
        "standard": standard,
        "robocizna_od": round(labor_min, 2),
        "robocizna_do": round(labor_max, 2),
        "materiały_od": round(materials_min, 2),
        "materiały_do": round(materials_max, 2),
        "suma_od": round(total_min, 2),
        "suma_do": round(total_max, 2),
        "stawka_VAT": vat_rate
    }
