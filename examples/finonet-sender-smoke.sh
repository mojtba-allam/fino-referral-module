#!/usr/bin/env bash
#
# Live SENDER smoke: forward a referred signup (and optionally a gateway +
# transaction) OUT to the real finonet.ir sales-organization panel, exactly the
# way this module's ForwardSignupToFinonet job does at runtime. Proves the
# outbound secret + contract are accepted by finonet before you wire the job.
#
# Requires: curl, jq. Nothing here writes to your own DB.
#
#   FINONET_SIGNUP_WEBHOOK_URL=https://finonet.ir/api/webhooks/finopal/signup \
#   FINONET_WEBHOOK_SECRET=<outbound secret> \
#   FINONET_REFERRAL_CODE=QM4VGJ7XBL \
#   FINONET_RECORD_SALE=true \
#   bash examples/finonet-sender-smoke.sh
#
set -euo pipefail

SIGNUP_URL="${FINONET_SIGNUP_WEBHOOK_URL:-https://finonet.ir/api/webhooks/finopal/signup}"
SECRET="${FINONET_WEBHOOK_SECRET:-}"
CODE="${FINONET_REFERRAL_CODE:-QM4VGJ7XBL}"
RECORD_SALE="${FINONET_RECORD_SALE:-false}"

# Derive sibling endpoints from the signup url (…/signup -> …/gateway|/transaction).
BASE="${SIGNUP_URL%/signup}"
GATEWAY_URL="${BASE}/gateway"
TXN_URL="${BASE}/transaction"

if [[ -z "$SECRET" ]]; then
  echo "ERROR: set FINONET_WEBHOOK_SECRET (the outbound secret finonet.ir expects)." >&2
  exit 1
fi

STAMP="$(date +%s)$$"
MOBILE="09$(shuf -i 100000000 -n 1 2>/dev/null || echo $((RANDOM%900000000+100000000)))"
NATIONAL="0$((RANDOM%900000000+100000000))0"
pass=0; fail=0
check() { # name expected actual
  if [[ "$2" == "$3" ]]; then echo "  PASS  $1 ($3)"; pass=$((pass+1));
  else echo "  FAIL  $1 expected=$2 got=$3"; fail=$((fail+1)); fi
}

hdr=(-H "X-Finopal-Webhook-Secret: $SECRET" -H "Content-Type: application/json" -H "Accept: application/json")

echo "== 1) signup -> $SIGNUP_URL (code=$CODE, mobile=$MOBILE) =="
signup_body=$(jq -nc --arg c "$CODE" --arg n "FinoSmoke $(date +%H%M%S)" --arg m "$MOBILE" --arg id "$NATIONAL" \
  '{event:"user.registered", referral_code:$c, user:{name:$n, mobile:$m, national_id:$id}}')
signup_resp=$(curl -sS -w $'\n%{http_code}' -X POST "$SIGNUP_URL" "${hdr[@]}" -d "$signup_body")
signup_code=$(echo "$signup_resp" | tail -n1); signup_json=$(echo "$signup_resp" | sed '$d')
echo "  $signup_json"
check "signup http" "200" "$signup_code"
created=$(echo "$signup_json" | jq -r '.user_created // empty')
echo "  user_created=$created"

if [[ "$RECORD_SALE" == "true" ]]; then
  echo "== 2) gateway -> $GATEWAY_URL =="
  ext="smoke-gw-$STAMP"; mrc="SMK-$STAMP"
  gw_body=$(jq -nc --arg m "$MOBILE" --arg id "$NATIONAL" --arg e "$ext" --arg c "$mrc" \
    '{event:"gateway.registered", idempotency_key:$e, user:{mobile:$m, national_id:$id},
      gateway:{external_id:$e, merchant_code:$c, name:"Smoke Shop", amount:1500000}}')
  gw_resp=$(curl -sS -w $'\n%{http_code}' -X POST "$GATEWAY_URL" "${hdr[@]}" -d "$gw_body")
  gw_code=$(echo "$gw_resp" | tail -n1); gw_json=$(echo "$gw_resp" | sed '$d'); echo "  $gw_json"
  check "gateway http" "200" "$gw_code"

  echo "== 3) transaction -> $TXN_URL (profit -> commission) =="
  tx_body=$(jq -nc --arg c "$mrc" --arg t "SMK-TXN-$STAMP" '{transaction_id:$t, merchant_id:$c, profit:250000, amount:1000000}')
  tx_resp=$(curl -sS -w $'\n%{http_code}' -X POST "$TXN_URL" "${hdr[@]}" -d "$tx_body")
  tx_code=$(echo "$tx_resp" | tail -n1); tx_json=$(echo "$tx_resp" | sed '$d'); echo "  $tx_json"
  check "transaction http" "200" "$tx_code"
fi

echo "== negative: wrong secret must be 401 =="
bad=$(curl -sS -o /dev/null -w '%{http_code}' -X POST "$SIGNUP_URL" \
  -H "X-Finopal-Webhook-Secret: definitely-wrong" -H "Content-Type: application/json" -H "Accept: application/json" \
  -d "{\"event\":\"user.registered\",\"referral_code\":\"$CODE\",\"user\":{\"mobile\":\"0900000000\"}}")
check "wrong-secret http" "401" "$bad"

echo; echo "RESULT: $pass passed, $fail failed"
echo "Now open the finonet team tree and search for mobile $MOBILE to confirm it landed."
[[ "$fail" -eq 0 ]]
