from fastapi import FastAPI, Depends, HTTPException, status
reset_at=reset_at_midnight_iso(),
)


@app.post("/api/quota/consume", response_model=schemas.QuotaResponse)
def quota_consume(payload: schemas.QuotaCheck, db: Session = Depends(get_db)):
today = today_tz().date()
q = (
db.query(models.Quota)
.filter(models.Quota.client_id == payload.client_id, models.Quota.date == today)
.first()
)
if not q:
q = models.Quota(client_id=payload.client_id, date=today, count=0, max=DEFAULT_DAILY_MAX)
db.add(q)
if q.count >= q.max:
db.commit()
remaining = 0
return schemas.QuotaResponse(
client_id=payload.client_id,
date=str(today),
count=q.count,
max=q.max,
remaining=remaining,
reset_at=reset_at_midnight_iso(),
)
q.count += 1
db.commit()
db.refresh(q)
return schemas.QuotaResponse(
client_id=payload.client_id,
date=str(today),
count=q.count,
max=q.max,
remaining=max(q.max - q.count, 0),
reset_at=reset_at_midnight_iso(),
)


@app.post("/api/offer/estimate", response_model=schemas.EstimateResponse)
def offer_estimate(payload: schemas.EstimateRequest, db: Session = Depends(get_db)):
# Tu można dorzucić dodatkowe limity per IP / ReCaptcha
est = estimate_offer(payload)


lead = models.Lead(
client_id=payload.client_id,
scope=payload.scope,
area_m2=payload.area_m2,
standard=payload.standard,
location=payload.location,
deadline=payload.deadline,
estimate_total=est.total,
created_at=datetime.utcnow(),
)
db.add(lead)
db.add(models.AuditLog(client_id=payload.client_id, event="estimate_requested", meta={"total": est.total}))
db.commit()


return schemas.EstimateResponse(
items=est.items,
subtotal=est.subtotal,
buffer=est.buffer,
total=est.total,
currency="PLN",
notes="Wycena orientacyjna. Finalna cena po wizji lokalnej.",
)


@app.post("/api/offer/export/pdf")
def offer_export_pdf(payload: schemas.ExportRequest):
pdf_bytes, filename = render_offer_pdf(payload)
from fastapi.responses import Response
return Response(content=pdf_bytes, media_type="application/pdf", headers={
"Content-Disposition": f"attachment; filename={filename}"
})


@app.post("/api/offer/export/txt")
def offer_export_txt(payload: schemas.ExportRequest):
content, filename = render_offer_txt(payload)
from fastapi.responses import PlainTextResponse
return PlainTextResponse(content, headers={
"Content-Disposition": f"attachment; filename={filename}"
})