#!/usr/bin/env bash
# Read-only smoke test: calls every GET endpoint and the auth/validation failure paths of the
# POST endpoints (requests that are rejected before anything is written).
#
#   php -S 127.0.0.1:8099 -t public &          # or point BASE at a deployed server
#   BASE=http://127.0.0.1:8099/v1 \
#   UT=$(php tests/make-token.php user <owner user id>) \
#   AT=$(php tests/make-token.php admin <admin id>) \
#   GYM=GYM003 OTHER=GYM001 MEMBER=<member id in GYM> \
#   bash tests/smoke.sh
#
# UT must be the OWNER of GYM and have no access to OTHER.

BASE=${BASE:-http://127.0.0.1:8099/v1}
pass=0; fail=0

check() { # expected_status  label  method  path  [token]  [json body]
  local expected=$1 label=$2 method=$3 path=$4 token=$5 body=$6
  local args=(-s -o /tmp/gs_smoke_body -w '%{http_code}' -X "$method")
  [ -n "$token" ] && args+=(-H "Authorization: Bearer $token")
  [ -n "$body" ] && args+=(-H 'Content-Type: application/json' -d "$body")
  local code; code=$(curl "${args[@]}" "$BASE/$path")
  local msg; msg=$(php -r '$j=json_decode(file_get_contents("/tmp/gs_smoke_body"),true); echo is_array($j) ? mb_substr($j["message"] ?? "", 0, 60) : "NOT JSON";')
  if [ "$code" = "$expected" ]; then pass=$((pass+1)); printf "  ok   %-3s %-44s %s\n" "$code" "$label" "$msg"
  else fail=$((fail+1)); printf "  FAIL %-3s %-44s expected %s — %s\n" "$code" "$label" "$expected" "$msg"; fi
}

echo "== guards"
check 401 "no token"                      GET "members/list.php?gym_id=$GYM"
check 401 "forged token"                  GET "members/list.php?gym_id=$GYM" "a.b.c"
check 401 "admin token on owner endpoint" GET "members/list.php?gym_id=$GYM" "$AT"
check 401 "owner token on admin endpoint" GET "admin/dashboard.php" "$UT"
check 403 "gym without access"            GET "members/list.php?gym_id=$OTHER" "$UT"
check 405 "wrong method"                  GET "members/create.php" "$UT"

echo "== owner app (GET)"
check 200 "auth/me"                GET "auth/me.php" "$UT"
check 200 "gyms/list"              GET "gyms/list.php" "$UT"
check 200 "members/list"           GET "members/list.php?gym_id=$GYM&limit=5" "$UT"
check 200 "members/list unpaid"    GET "members/list.php?gym_id=$GYM&filter=unpaid" "$UT"
check 400 "members/list bad filter" GET "members/list.php?gym_id=$GYM&filter=nope" "$UT"
check 200 "members/show"           GET "members/show.php?gym_id=$GYM&member_id=$MEMBER" "$UT"
check 404 "members/show missing"   GET "members/show.php?gym_id=$GYM&member_id=999999999" "$UT"
for f in all present absent active expired; do
check 200 "attendance/daily $f"    GET "attendance/daily.php?gym_id=$GYM&filter=$f" "$UT"
done
check 400 "attendance/daily bad date" GET "attendance/daily.php?gym_id=$GYM&date=2026-99-99" "$UT"
check 200 "attendance/history"     GET "attendance/history.php?gym_id=$GYM&member_id=$MEMBER" "$UT"
check 200 "batches/list"           GET "batches/list.php?gym_id=$GYM" "$UT"
check 200 "gym-plans/list"         GET "gym-plans/list.php?gym_id=$GYM" "$UT"
check 200 "staff/list"             GET "staff/list.php?gym_id=$GYM" "$UT"
check 200 "payments/list"          GET "payments/list.php?gym_id=$GYM" "$UT"
check 200 "expenses/list"          GET "expenses/list.php?gym_id=$GYM" "$UT"
check 200 "app-plans/list public"  GET "app-plans/list.php"
check 200 "app-plans/list gym"     GET "app-plans/list.php?gym_id=$GYM" "$UT"
check 200 "app-plans/history"      GET "app-plans/history.php?gym_id=$GYM" "$UT"
check 200 "reports/summary"        GET "reports/summary.php?gym_id=$GYM" "$UT"
check 200 "reports/daily"          GET "reports/daily.php?gym_id=$GYM" "$UT"
check 200 "reports/chart revenue"  GET "reports/chart.php?gym_id=$GYM&type=revenue" "$UT"
check 200 "reports/chart attendance" GET "reports/chart.php?gym_id=$GYM&type=attendance" "$UT"
for t in members payments expenses attendance expiring; do
check 200 "reports/export $t"      GET "reports/export.php?gym_id=$GYM&type=$t" "$UT"
done
check 200 "notifications/list"     GET "notifications/list.php?gym_id=$GYM" "$UT"

echo "== owner app (POST, rejected before writing)"
check 400 "login bad email"            POST auth/login.php "" '{"email":"x","password":"y"}'
check 401 "login wrong"                POST auth/login.php "" '{"email":"nobody@example.invalid","password":"123456"}'
check 404 "forgot-password unknown"    POST auth/forgot-password.php "" '{"email":"nobody@example.invalid"}'
check 400 "reset-password no otp"      POST auth/reset-password.php "" '{"email":"nobody@example.invalid","new_password":"abcdef"}'
check 400 "register missing"           POST signup/register.php "" '{}'
check 400 "members/create no phone"    POST members/create.php "$UT" "{\"gym_id\":\"$GYM\",\"name\":\"x\"}"
check 400 "members/update bad status"  POST members/update.php "$UT" "{\"gym_id\":\"$GYM\",\"member_id\":$MEMBER,\"status\":\"hacked\"}"
check 400 "members/update other plan"  POST members/update.php "$UT" "{\"gym_id\":\"$GYM\",\"member_id\":$MEMBER,\"plan_id\":999999}"
check 400 "members/set-status bad"     POST members/set-status.php "$UT" "{\"gym_id\":\"$GYM\",\"member_id\":$MEMBER,\"status\":\"deleted\"}"
check 400 "payments/create negative"   POST payments/create.php "$UT" "{\"gym_id\":\"$GYM\",\"member_id\":$MEMBER,\"amount\":-5}"
check 400 "gym-plans/create 0 months"  POST gym-plans/create.php "$UT" "{\"gym_id\":\"$GYM\",\"plan_name\":\"x\",\"price\":10,\"duration_months\":0}"
check 400 "batches/create bad time"    POST batches/create.php "$UT" "{\"gym_id\":\"$GYM\",\"batch_name\":\"x\",\"start_time\":\"25:00\",\"end_time\":\"x\"}"
check 400 "expenses/create bad date"   POST expenses/create.php "$UT" "{\"gym_id\":\"$GYM\",\"title\":\"x\",\"amount\":5,\"expense_date\":\"2026-13-01\"}"
check 403 "staff/create other gym"     POST staff/create.php "$UT" "{\"gym_ids\":[\"$OTHER\"],\"email\":\"a@example.invalid\",\"name\":\"x\"}"
check 400 "staff/create owner role"    POST staff/create.php "$UT" "{\"gym_ids\":[\"$GYM\"],\"email\":\"a@example.invalid\",\"name\":\"x\",\"role\":\"owner\"}"
check 403 "gyms/delete other gym"      POST gyms/delete.php "$UT" "{\"gym_id\":\"$OTHER\"}"
check 400 "app-plans buy trial"        POST app-plans/purchase-order.php "$UT" "{\"gym_id\":\"$GYM\",\"plan_id\":4}"
check 400 "online-verify bad sig"      POST payments/online-verify.php "$UT" '{"razorpay_order_id":"order_x","razorpay_payment_id":"pay_x","razorpay_signature":"bad"}'
check 400 "paid-verify bad sig"        POST signup/paid-verify.php "" '{"razorpay_order_id":"order_x","razorpay_payment_id":"pay_x","razorpay_signature":"bad","owner_name":"x","email":"a@example.invalid","password":"abcdef"}'
check 401 "cron without secret"        POST "cron/trigger.php?task=systemCleanup" "$UT"
check 401 "broadcast as owner"         POST admin/notifications/broadcast-daily.php "$UT" '{}'

echo "== admin (GET)"
check 200 "admin/dashboard"          GET "admin/dashboard.php" "$AT"
check 200 "admin/period-stats"       GET "admin/period-stats.php?period=last_30_days" "$AT"
check 400 "admin/period-stats bad"   GET "admin/period-stats.php?period=nope" "$AT"
check 200 "admin/gyms/list"          GET "admin/gyms/list.php?limit=5" "$AT"
check 200 "admin/gyms/list search"   GET "admin/gyms/list.php?search=gym&filter=free" "$AT"
check 200 "admin/gyms/list new"      GET "admin/gyms/list.php?filter=new&period=This%20Year" "$AT"
check 200 "admin/gyms/show"          GET "admin/gyms/show.php?gym_id=$GYM" "$AT"
check 200 "admin/members/list"       GET "admin/members/list.php?limit=5" "$AT"
check 200 "admin/staff/list"         GET "admin/staff/list.php" "$AT"
check 200 "admin/staff/list gym"     GET "admin/staff/list.php?gym_id=$GYM" "$AT"
check 200 "admin/app-plans/list"     GET "admin/app-plans/list.php" "$AT"
check 200 "admin/subscriptions/list" GET "admin/subscriptions/list.php?filter=revenue&period=this_year" "$AT"
check 401 "admin/login wrong"        POST admin/login.php "" '{"username":"nobody_x","password":"nope"}'

echo
echo "passed: $pass   failed: $fail"
[ "$fail" -eq 0 ]
