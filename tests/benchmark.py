import urllib.request
import time
import json

token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxIiwidHlwIjoiYWRtaW4iLCJpYXQiOjE3OTA0OTc3MzMsImV4cCI6MTc5MDU4NDEzM30.x4VVAQY__7--bAPFjj1N3pzSTRS2I2mjgYsmj60YV5Y"
base_url = "http://127.0.0.1:8000"

endpoints = [
    ("/api/v1/system/health.php", "System Health Check"),
    ("/api/v1/admin/dashboard.php", "Admin Dashboard Stats"),
    ("/api/v1/admin/gyms/list.php?page=1&limit=10", "Gyms List (10 rows)"),
    ("/api/v1/admin/gyms/list.php?page=1&limit=6&filter=free", "Expiring Gyms Widget"),
    ("/api/v1/admin/users/list.php?page=1&limit=15", "Users & Owners List (15 rows)"),
    ("/api/v1/admin/members/list.php?page=1&limit=10", "Members List (10 rows)"),
    ("/api/v1/admin/subscriptions/list.php?page=1&limit=10", "Subscriptions List (10 rows)"),
    ("/api/v1/admin/gyms/show.php?id=1", "Gym Deep Profile (GYM001)"),
    ("/api/v1/admin/staff/list.php", "Staff Directory List"),
]

header = f"{'Endpoint':<52} | {'Status':<6} | {'Avg Latency':<12} | {'Payload Size':<12}"
print(header)
print("-" * len(header))

for path, label in endpoints:
    url = base_url + path
    req = urllib.request.Request(url, headers={
        "Authorization": f"Bearer {token}",
        "Accept-Encoding": "gzip",
        "User-Agent": "Benchmark/1.0"
    })
    times = []
    status = 0
    size = 0
    for _ in range(3):
        t0 = time.time()
        try:
            with urllib.request.urlopen(req) as resp:
                status = resp.status
                body = resp.read()
                size = len(body)
                elapsed = (time.time() - t0) * 1000
                times.append(elapsed)
        except Exception as e:
            status = getattr(e, "code", 500)
            times.append(9999)
    avg_ms = sum(times) / len(times)
    print(f"{path:<52} | {status:<6} | {avg_ms:>8.1f} ms | {size:>8} B")
