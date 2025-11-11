from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # docelowo możesz zawęzić do ["https://ollbud.pl"]
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

@app.get("/api/ping")
def ping():
    return {"ok": True}

@app.get("/")
def root():
    return {"status": "OK", "service": "OLLBUD backend"}
