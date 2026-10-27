# Testing

This module is verified in **escalating layers**. The test files ship in
`tests/` (PHPUnit) and `examples/` (curl + Playwright). Because the tests extend
your host `Tests\TestCase` and use the module factories, run them **from inside a
project that has installed the module** — they are provided as working reference.

Two independent sides are tested:

- **Receiver** (the module's core): finopal POSTs webhooks *into* your app
  (Steps 1–3 below).
- **Sender** (optional): your host forwards a referred signup *out* to
  `finonet.ir` (Step 4, only if you enabled `referral.forward` — see
  [SENDER.md](SENDER.md)).

## Prerequisites for the test environment

```dotenv
FINOPAL_WEBHOOK_SECRET=finopal-test-secret   # any value; must match what the tests send
FINOPAL_WELCOME_SMS_ENABLED=true
FINOPAL_WEBHOOK_SIGNUP_ENABLED=true
FINOPAL_WEBHOOK_GATEWAY_ENABLED=true
FINOPAL_WEBHOOK_TRANSACTION_ENABLED=true
QUEUE_CONNECTION=sync
OTP_DRIVER=array          # Finolo example; stub SMS in your own host

# Only for Step 4 (sender). Keep OUTBOUND separate from INBOUND.
FINONET_FORWARD_SIGNUP_ENABLED=false   # turn true only for the live sender test
FINONET_SIGNUP_WEBHOOK_URL=https://finonet.ir/api/webhooks/finopal/signup
FINONET_WEBHOOK_SECRET=                # provided out-of-band by the finonet team; never commit
```

For deterministic tests, pin the link base explicitly, e.g.
`config()->set('referral.referral.link_base', 'https://finopal.ir/login')`, so a
sandbox/production env value never changes the assertion. **Always** keep
`FINONET_FORWARD_SIGNUP_ENABLED=false` (or `Http::fake`) in your suite so a unit
run never POSTs to the real panel.

## Step 1 — PHPUnit (receiver)

```bash
php artisan test --compact tests/Feature/Modules/Referral
php artisan test --compact --filter "Referral|Webhook"
```

Covered scenarios (ported from the finopal Postman collection):

- Signup happy path → `200`, `user_created=true`, welcome SMS queued, relation
  edge + audit row written.
- Signup duplicate (same national id / mobile) → `200`, `user_created=false`, no move.
- Signup invalid `referral_code` → `422`.
- Gateway after signup → `200`, `gateway_created=true`, `gateway_id` present.
- Gateway before signup → `422`.
- Gateway repeat (same external/merchant id) → `200`, `gateway_created=false`;
  reused idempotency key on a different gateway → `422`.
- Transaction valid `merchant_id` + `profit` → `200`, commission from `profit`;
  unknown merchant → `422`; duplicate transaction → idempotent skip.
- Wrong / missing secret → `401` on all three endpoints.
- `GET /referral/me` — finonet-arrived user → `show_referral_option=false`;
  normal user → `true` with a generated code/link.
- Unit: `ProvisionReferralCode` uniqueness/unambiguity and link building.

## Step 2 — curl smoke (receiver)

`examples/finopal-referral-smoke.sh` hits a **live** server and asserts HTTP
status + JSON fields across the whole sequence (signup → dedup → gateway → dedup
→ transaction → gateway-before-signup 422 → wrong secret 401 → `GET /me`).

```bash
BASE_URL=http://127.0.0.1:8000 \
FINOPAL_WEBHOOK_SECRET=finopal-test-secret \
bash examples/finopal-referral-smoke.sh
```

Requires `curl` + `jq` and a seeded parent code (the script provisions one via
`tinker` — adjust that line for your host's model/commands).

## Step 3 — Playwright full flow (receiver)

`examples/finopal-referral-flow.spec.ts` runs one serial, ordered spec that
chains the lifecycle **A → B → C → D**:

- **A** — a user signs up with no `finonet_ref`; the referral option shows and a
  code + `?finonet_ref={code}` link appear.
- **B** — a second person arrives **with** that `finonet_ref`; option hidden; the
  signup webhook registers them (200, `user_created=true`), a repeat is skipped.
- **C** — the gateway webhook completes for B (200, `gateway_created=true`),
  idempotent re-send skipped.
- **D** — the transaction webhook is received (200, commission along the chain),
  duplicate skipped.

Negative cases (401 bad secret, 422 gateway-before-signup) are asserted via the
`request` context. Always send `Accept: application/json` in API calls so
validation errors are JSON, not redirects.

```bash
npx playwright test finopal-referral-flow
```

Adjust the base URL / registration selectors to your host's frontend.

## Step 4 — Sender: forward to finonet.ir and verify in the panel

Only applies if the host acts as a **sender** ([SENDER.md](SENDER.md)). Verify
the outbound path in three escalating layers.

### 4a. PHPUnit (Http::fake) — no network

`tests/Feature/Modules/Referral/ForwardSignupToFinonetTest.php` asserts the exact
contract (POST `{event, referral_code, user{...}}` with the `X-Finopal-Webhook-Secret`
header) and all four pitfalls: skips when disabled or secret missing, drops empty
fields, **swallows 4xx** (no retry), and **rethrows 5xx** (queue retries).

```bash
php artisan test --compact tests/Feature/Modules/Referral/ForwardSignupToFinonetTest.php
```

### 4b. Live curl — POST to the real panel

`examples/finonet-sender-smoke.sh` forwards a referred signup (and, with
`FINONET_RECORD_SALE=true`, a gateway + transaction) straight to finonet and
asserts `200` / `user_created=true` + the wrong-secret `401`. It prints the random
mobile it used so you can find it in the UI next.

```bash
FINONET_WEBHOOK_SECRET=<outbound secret> \
FINONET_REFERRAL_CODE=QM4VGJ7XBL \
FINONET_RECORD_SALE=true \
bash examples/finonet-sender-smoke.sh
```

### 4c. Playwright — log into finonet.ir and confirm the record

`examples/finonet-sender-verify.spec.ts` does 4b **and then** drives the browser:
clicks «ورود به حساب», logs in, opens the **سازمان و تیم** tree
(`/dashboard/<role>/team`), searches the forwarded mobile, and asserts it is
visible (with `FINONET_RECORD_SALE=true` you can also check **فروش و پورسانت →
تراکنش‌ها / پورسانت** for the sale + commission).

```bash
FINONET_WEBHOOK_SECRET=<outbound secret> \
FINONET_REFERRAL_CODE=QM4VGJ7XBL \
FINONET_TEST_MOBILE=<finonet test account> \
FINONET_TEST_PASSWORD=<its password, from your secret store> \
FINONET_RECORD_SALE=true \
npx playwright test finonet-sender-verify
```

**Credentials are read only from the environment and must never be committed.**
The panel is a throwaway test server; a `QM4VGJ7XBL` signup creates a real test
representative — clean it up (block/remove) afterwards.

> **Attribution check.** The created user must appear under the rep who *owns*
> the code. If it lands at the top level instead, the code you sent belongs to a
> higher account — confirm the code's owner (see the note in SENDER.md).

## Formatting

Run Laravel Pint before committing:

```bash
vendor/bin/pint --dirty
```
