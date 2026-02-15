import os
import time
import json
import requests

API_key = "3269_68af4001d049f"
PW = "123456"

params = {
    "timestamp": "2026-02-15T14:50:00.000Z",
    "id": "EQ8109074",
    "lat": 13.4975533,
    "lon": -88.9647466,
    "kmph": 0.0,
    "heading": 265.0,
    "event": 1,
    "gps": "true"
}

header = {'Content-type': 'application/json'}

Upload = requests.post(
    'https://staging.gps.gt/api/point',
    headers=header,
    auth=(API_key, PW),
    json=params
)

print(Upload.status_code)
print(Upload.text)
