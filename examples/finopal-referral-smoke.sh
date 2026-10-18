#!/usr/bin/env bash
# Finopal / finonet.ir referral webhook smoke checks (inbound signup/gateway/transaction).
# Requires: curl, jq, and php artisan access (to seed a parent referral code).
# Env: BASE_URL (or APP_URL), FINOPAL_WEBHOOK_SECRET
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/../.." && pwd)"

DEFAULT_APP_URL="$(grep -E '^APP_URL=' "${ROOT_DIR}/.env" 2>/dev/null | head -1 | cut -d= -f2- || true)"
DEFAULT_SECRET="$(grep -E '^FINOPAL_WEBHOOK_SECRET=' "${ROOT_DIR}/.env" 2>/dev/null | head -1 | cut -d= -f2- || true)"

BASE_URL="${BASE_URL:-${APP_URL:-${DEFAULT_APP_URL:-http://127.0.0.1:8000}}}"
SECRET="${FINOPAL_WEBHOOK_SECRET:-${DEFAULT_SECRET:-finopal-local-webhook-secret}}"
BASE_URL="${BASE_URL%/}"

FAIL=0
pass() { echo "PASS: $1"; }
fail() { echo "FAIL: $1" >&2; FAIL=1; }

if ! command -v curl >/dev/null 2>&1; then echo "ERROR: curl required" >&2; exit 1; fi
if ! command -v jq >/dev/null 2>&1; then echo "ERROR: jq required" >&2; exit 1; fi

STAMP="$(date +%s)"
NATIONAL="9${STAMP}"
MOBILE="09${STAMP}"
GW_EXT="smoke-gw-${STAMP}"
GW_MRC="MRC-${STAMP}"

# Seed a parent referral code the signup webhook can attach under.
PARENT_CODE="$(cd "${ROOT_DIR}" && php artisan tinker --execute="
\$a = \Modules\Referral\Models\ReferralAccount::query()->where('referral_code','SMOKEPARENT')->first();
if (!\$a) { \$a = \Modules\Referral\Models\ReferralAccount::create(['referral_code'=>'SMOKEPARENT','arrived_via_finonet'=>false,'name'=>'Smoke Parent','status'=>'active']); }
echo \$a->referral_code;
" 2>/dev/null | tail -1)"
if [ "${PARENT_CODE}" != "SMOKEPARENT" ]; then
  echo "ERROR: could not seed parent referral code (got '${PARENT_CODE}'). Is the app migrated?" >&2
  exit 1
fi

hdr=(-H "Content-Type: application/json" -H "Accept: application/json" -H "X-Finopal-Webhook-Secret: ${SECRET}")

post() { # $1 path, $2 json
  curl -sS -o /tmp/ref_body -w "%{http_code}" -X POST "${BASE_URL}$1" "${hdr[@]}" -d "$2"
}

echo "Finopal referral smoke against ${BASE_URL}"

SIGNUP="{\"event\":\"user.registered\",\"referral_code\":\"${PARENT_CODE}\",\"user\":{\"name\":\"Smoke User\",\"mobile\":\"${MOBILE}\",\"national_id\":\"${NATIONAL}\"}}"

# 1) signup -> 200 user_created=true
code="$(post /api/webhooks/finopal/signup "${SIGNUP}")"
if [ "$code" = "200" ] && [ "$(jq -r .user_created /tmp/ref_body)" = "true" ]; then pass "signup created"; else fail "signup (got $code: $(cat /tmp/ref_body))"; fi

# 2) signup repeat -> 200 user_created=false
code="$(post /api/webhooks/finopal/signup "${SIGNUP}")"
if [ "$code" = "200" ] && [ "$(jq -r .user_created /tmp/ref_body)" = "false" ]; then pass "signup dedup"; else fail "signup dedup (got $code)"; fi

# 3) gateway after signup -> 200 gateway_created=true
GW="{\"user\":{\"mobile\":\"${MOBILE}\",\"national_id\":\"${NATIONAL}\"},\"gateway\":{\"external_id\":\"${GW_EXT}\",\"merchant_code\":\"${GW_MRC}\",\"name\":\"Smoke Shop\"}}"
code="$(post /api/webhooks/finopal/gateway "${GW}")"
if [ "$code" = "200" ] && [ "$(jq -r .gateway_created /tmp/ref_body)" = "true" ]; then pass "gateway created"; else fail "gateway (got $code: $(cat /tmp/ref_body))"; fi

# 4) gateway repeat -> 200 gateway_created=false
code="$(post /api/webhooks/finopal/gateway "${GW}")"
if [ "$code" = "200" ] && [ "$(jq -r .gateway_created /tmp/ref_body)" = "false" ]; then pass "gateway dedup"; else fail "gateway dedup (got $code)"; fi

# 5) transaction -> 200 commission == profit
TXN="{\"merchant_id\":\"${GW_MRC}\",\"transaction_id\":\"TXN-${STAMP}\",\"profit\":250000,\"amount\":1000000}"
code="$(post /api/webhooks/finopal/transaction "${TXN}")"
if [ "$code" = "200" ] && [ "$(jq -r .commission /tmp/ref_body)" = "250000" ]; then pass "transaction commission"; else fail "transaction (got $code: $(cat /tmp/ref_body))"; fi

# 6) gateway before signup (unknown user) -> 422
ORPHAN="{\"user\":{\"mobile\":\"09000000000\",\"national_id\":\"0000000000\"},\"gateway\":{\"external_id\":\"orphan-gw-${STAMP}\",\"merchant_code\":\"ORPHAN-${STAMP}\"}}"
code="$(post /api/webhooks/finopal/gateway "${ORPHAN}")"
if [ "$code" = "422" ]; then pass "gateway-before-signup 422"; else fail "gateway-before-signup (got $code)"; fi

# 7) wrong secret -> 401
code="$(curl -sS -o /dev/null -w "%{http_code}" -X POST "${BASE_URL}/api/webhooks/finopal/signup" -H "Content-Type: application/json" -H "X-Finopal-Webhook-Secret: wrong" -d "${SIGNUP}")"
if [ "$code" = "401" ]; then pass "wrong secret 401"; else fail "wrong secret (got $code)"; fi

echo
if [ "${FAIL}" -eq 0 ]; then echo "ALL PASSED"; else echo "SOME FAILED" >&2; exit 1; fi
