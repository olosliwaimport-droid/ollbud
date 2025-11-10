from dataclasses import dataclass
from typing import List, Dict, Any
from app import schemas


@dataclass
class Estimation:
items: List[Dict[str, Any]]
subtotal: int
buffer: int
total: int


BASE_RATES = {
"malowanie": 45,
"podłogi": 120,
"łazienka": 1800,
"kuchnia": 1500,
"remont kompleksowy": 700,
}


STANDARD_MUL = {
"ekonomiczny": 0.9,
"standard": 1.0,
"premium": 1.25,
}


def estimate_offer(req: schemas.EstimateRequest) -> Estimation:
s = req.scope.lower()
m2 = float(req.area_m2 or 0)


rate = 0
items = []
if "malow" in s:
items.append({"name": "Malowanie", "unit": "m²", "qty": m2, "rate": BASE_RATES["malowanie"]})
rate += BASE_RATES["malowanie"]
if "podł" in s or "podl" in s:
items.append({"name": "Układanie podłogi", "unit": "m²", "qty": m2, "rate": BASE_RATES["podłogi"]})
rate += BASE_RATES["podłogi"]
if "łaz" in s:
items.append({"name": "Remont łazienki", "unit": "m²", "qty": m2, "rate": BASE_RATES["łazienka"]})
rate += BASE_RATES["łazienka"]
if "kuch" in s:
items.append({"name": "Remont kuchni", "unit": "m²", "qty": m2, "rate": BASE_RATES["kuchnia"]})
rate += BASE_RATES["kuchnia"]
if "kompleks" in s:
items = [{"name": "Remont kompleksowy", "unit": "m²", "qty": m2, "rate": BASE_RATES["remont kompleksowy"]}]
rate = BASE_RATES["remont kompleksowy"]


std_mul = STANDARD_MUL.get(req.standard, 1.0)
loc_mul = 1.0 if ("kraków" in req.location.lower() or "krakow" in req.location.lower()) else 1.1


subtotal = round(rate * m2 * std_mul * loc_mul)
buffer = round(subtotal * 0.08)
total = subtotal + buffer


# (opcjonalnie) dodać domyślne pozycje: gładzie, listwy, utylizacja itd.


return Estimation(items=items, subtotal=subtotal, buffer=buffer, total=total)