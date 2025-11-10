from sqlalchemy import Column, Integer, String, Date, DateTime, Float, Text, JSON
from app.database import Base


class Quota(Base):
__tablename__ = "quota"
id = Column(Integer, primary_key=True, index=True)
client_id = Column(String, index=True)
date = Column(Date, index=True)
count = Column(Integer, default=0)
max = Column(Integer, default=3)


class Lead(Base):
__tablename__ = "leads"
id = Column(Integer, primary_key=True, index=True)
client_id = Column(String, index=True)
created_at = Column(DateTime)
scope = Column(Text)
area_m2 = Column(Float)
standard = Column(String)
location = Column(String)
deadline = Column(String)
estimate_total = Column(Integer)


class AuditLog(Base):
__tablename__ = "audit_log"
id = Column(Integer, primary_key=True, index=True)
ts = Column(DateTime)
client_id = Column(String, index=True)
event = Column(String)
meta = Column(JSON)