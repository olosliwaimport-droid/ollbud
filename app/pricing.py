def estimate_offer(area_m2: float, standard: str):
    """
    Szacuje koszt robót dla danego metrażu i standardu.
    Zasady:
    - robocizna bazowa zależna od typu (blok, kamienica, deweloperski)
    - narzut 42.5%
    - materiały = 60–150% wartości robocizny
    - robocizna widełki +35%
    - VAT 8% dla mieszkań ≤150 m² i domów ≤300 m², inaczej 23%
    """

    # --- stawki bazowe (robocizna netto, bez narzutów) ---
    if standard.lower() == "kamienica":
        base_rate = 1200
    elif standard.lower() == "deweloperski":
        base_rate = 900
    else:
        base_rate = 1000  # blok lub inne

    # --- robocizna ---
    labor_min = area_m2 * base_rate
    labor_max = labor_min * 1.35  # widełki w górę

    # --- narzut 42.5% ---
    labor_min *= 1.425
    labor_max *= 1.425

    # --- materiały ---
    materials_min = labor_min * 0.6 * 1.425
    materials_max = labor_min * 1.5 * 1.425

    # --- suma ---
    total_min = labor_min + materials_min
    total_max = labor_max + materials_max

    # --- VAT ---
    if area_m2 <= 150:
        vat_rate = "8%"
    elif area_m2 <= 300:
        vat_rate = "8%"
    else:
        vat_rate = "23%"

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
