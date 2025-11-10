from datetime import datetime, timedelta


# Zakładamy czas lokalny – prosto, bez pytz/zoneinfo


def today_tz():
return datetime.now()


def reset_at_midnight_iso():
now = datetime.now()
tomorrow = (now + timedelta(days=1)).replace(hour=0, minute=0, second=0, microsecond=0)
return tomorrow.isoformat()