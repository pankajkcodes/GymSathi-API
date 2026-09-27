import urllib.request
import time
import json
import gzip

admin_token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxIiwidHlwIjoiYWRtaW4iLCJpYXQiOjE3OTA0OTc3MzMsImV4cCI6MTc5MDU4NDEzM30.x4VVAQY__7--bAPFjj1N3pzSTRS2I2mjgYsmj60YV5Y"
user_token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxIiwidHlwIjoidXNlciIsImlhdCI6MTc5MDQ5OTExMSwiZXhwIjoxNzkzMDkxMTExfQ.F2Ee5zCwdhgJfBbTpbdGerh4wS5t1UBI2risAV6tmhU"
member_token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxIiwidHlwIjoibWVtYmVyIiwiaWF0IjoxNzkwNDk5MTExLCJleHAiOjE3OTMwOTExMTF9.-wUBvYvgefR1lHh400uba294UxcnpJJcEJRj4xFeGcc"

base_url = "http://127.0.0.1:8000"

test_suites = [
    ("Core & Public", [
        ("/api/v1/system/health.php", None, "System Health"),
        ("/api/v1/app-plans/list.php", None, "Public App Plans List"),
    ]),
    ("Superadmin Portal", [
        ("/api/v1/admin/dashboard.php", admin_token, "Admin Dashboard KPIs"),
        ("/api/v1/admin/gyms/list.php?page=1&limit=10", admin_token, "Gyms Directory"),
        ("/api/v1/admin/gyms/list.php?page=1&limit=6&filter=free", admin_token, "Expiring Gyms Widget"),
        ("/api/v1/admin/users/list.php?page=1&limit=15", admin_token, "Users & Owners (Batch)"),
        ("/api/v1/admin/members/list.php?page=1&limit=10", admin_token, "Platform Members List"),
        ("/api/v1/admin/subscriptions/list.php?page=1&limit=10", admin_token, "Subscriptions History"),
        ("/api/v1/admin/gyms/show.php?id=1", admin_token, "Gym Deep Profile"),
        ("/api/v1/admin/staff/list.php", admin_token, "Global Staff Directory"),
    ]),
    ("Gym Owner & Staff Portal", [
        ("/api/v1/auth/me.php", user_token, "User Profile (/auth/me)"),
        ("/api/v1/gyms/list.php", user_token, "My Gyms Switcher"),
        ("/api/v1/gyms/show.php?gym_id=GYM001", user_token, "Gym Settings & Details"),
        ("/api/v1/members/list.php?gym_id=GYM001&filter=all&page=1&limit=10", user_token, "Gym Members Filter"),
        ("/api/v1/payments/list.php?gym_id=GYM001&page=1&limit=10", user_token, "Gym Payments Log"),
        ("/api/v1/expenses/list.php?gym_id=GYM001", user_token, "Gym Expenses List"),
        ("/api/v1/batches/list.php?gym_id=GYM001", user_token, "Gym Batches"),
        ("/api/v1/gym-plans/list.php?gym_id=GYM001", user_token, "Gym Membership Plans"),
        ("/api/v1/attendance/daily.php?gym_id=GYM001&date=2026-09-27&filter=all", user_token, "Daily Attendance Sheet"),
        ("/api/v1/reports/summary.php?gym_id=GYM001", user_token, "Owner Dashboard Summary"),
        ("/api/v1/reports/chart.php?gym_id=GYM001&type=revenue", user_token, "6-Month Revenue Chart"),
        ("/api/v1/reports/chart.php?gym_id=GYM001&type=attendance", user_token, "30-Day Attendance Chart"),
        ("/api/v1/notifications/list.php?gym_id=GYM001", user_token, "Gym Notifications Feed"),
    ]),
    ("Mobile Member App", [
        ("/api/v1/member-app/me.php", member_token, "Member Profile (/me)"),
        ("/api/v1/member-app/attendance.php", member_token, "Member Attendance History"),
        ("/api/v1/member-app/payments.php", member_token, "Member Payment Receipts"),
    ])
]

print(f"{'Category / Endpoint':<55} | {'Status':<6} | {'Avg Latency':<12} | {'Payload (Gzip)':<14}")
print("=" * 95)

results = []

for category, endpoints in test_suites:
    print(f"\n--- {category} ---")
    for path, token, label in endpoints:
        url = base_url + path
        headers = {
            "Accept-Encoding": "gzip",
            "User-Agent": "GymSathi-PerfAudit/1.0"
        }
        if token:
            headers["Authorization"] = f"Bearer {token}"
            
        times = []
        status = 0
        size = 0
        for _ in range(3):
            t0 = time.time()
            try:
                req = urllib.request.Request(url, headers=headers)
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
        name = f"{label} ({path.split('?')[0].replace('/api/v1', '')})"
        print(f"{name:<55} | {status:<6} | {avg_ms:>8.1f} ms | {size:>10} B")
        results.append({
            "category": category,
            "label": label,
            "path": path,
            "status": status,
            "latency": avg_ms,
            "size": size
        })
