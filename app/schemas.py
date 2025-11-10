from pydantic import BaseModel, Field
from typing import List, Dict, Any


class QuotaCheck(BaseModel):
client_id: str


class QuotaResponse(BaseModel):
client_id: str
date: str
count: int
max: int
remaining: int
reset_at: str


class EstimateRequest(BaseModel):
client_id: str
scope: str
area_m2: float = Field(ge=0)
standard: str # ekonomiczny | standard | premium
location: str
deadline: str | None = None


class EstimateResponse(BaseModel):
items: List[Dict[str, Any]]
subtotal: int
buffer: int
total: int
currency: str
notes: str


class ExportRequest(BaseModel):
client_id: str
summary: Dict[str, Any] # np. zakres, area, standard, location, deadline
pricing: Dict[str, Any] # subtotal, buffer, total, currency

---


## app/deps.py
```python
from app.database import SessionLocal


def get_db():
db = SessionLocal()
try:
yield db
finally:
db.close()