# Inbound Webhooks

finopal/finonet.ir calls **your** endpoints when a referred user signs up,
opens a gateway, or completes a transaction. This module only **receives** —
it never calls finopal back.

## Authentication (all three endpoints)

Every webhook is guarded by `VerifyFinopalWebhook`. Send the shared secret
(`FINOPAL_WEBHOOK_SECRET`) in one of these headers:

```
X-Finopal-Webhook-Secret: <secret>
```
or, as a fallback:
```
Authorization: Bearer <secret>
```

The comparison uses `hash_equals`. A **missing or mismatched** secret — or an
**empty configured secret** — returns **401**.

Base URLs (registered by the provider under the `api` middleware group):

| Event | Method | Path |
| --- | --- | --- |
| Signup | `POST` | `/api/webhooks/finopal/signup` |
| Gateway | `POST` | `/api/webhooks/finopal/gateway` |
| Transaction | `POST` | `/api/webhooks/finopal/transaction` |

> Always send `Accept: application/json` so validation failures return a **422
> JSON** body instead of a **302 redirect** (Laravel redirects non-JSON requests).

---

## 1. Signup

Creates a representative under a referral code, or skips if the user already
exists.

**Request**

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `event` | string ≤64 | No | Free-form event name. |
| `referral_code` | string ≤64 | **Yes** | The referrer's code (echoed from `?finonet_ref=`). |
| `user.name` | string ≤255 | **Yes** | |
| `user.mobile` | string ≤20 | **Yes** | Secondary match key. |
| `user.national_id` | string ≤32 | **Yes** | Primary match key. Never written to the host `users` table. |
| `user.email` | email ≤255 | No | |
| `user.birth_date` | `YYYY-MM-DD` | No | |
| `user.sheba` | string ≤40 | No | |
| `user.finopal_user_id` | string ≤64 | No | Tertiary match key. |

**Behavior**

1. Match an existing account by **`national_id` → `mobile` → `finopal_user_id`**.
   If found: **200** with `user_created=false`, `user_skipped=true`; the profile
   is **not** changed and the user is **not** moved in the tree.
2. If new, resolve `referral_code` to a parent account. Unknown code → **422**.
3. Create a `arrived_via_finonet=true` account under the parent, append the
   ancestor chain, and queue the welcome message (when enabled).

**Response (created)**

```json
{ "success": true, "message": "…", "user_id": "uuid", "user_created": true, "user_skipped": false, "welcome_sms_queued": true }
```

**Response (duplicate)**

```json
{ "success": true, "message": "…", "user_id": "uuid", "user_created": false, "user_skipped": true, "welcome_sms_queued": false }
```

`user_id` is the **module's** account UUID. (The docs' examples show integers;
this module uses UUIDs — see [Integration](INTEGRATION.md#identifiers).)

**Status codes:** `200` created / duplicate · `401` bad secret · `422` missing
fields or invalid `referral_code` for a new user.

---

## 2. Gateway

Records a gateway "sale" for an **already registered** representative. The
gateway is stored with `share_percent = 100` and the account's manager path.

**Request**

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `event` | string ≤64 | No | |
| `idempotency_key` | string ≤120 | No | Guard against replaying a *different* gateway. |
| `user.national_id` | string ≤32 | **Yes** | Owner lookup. |
| `user.mobile` | string ≤20 | **Yes** | Owner lookup (fallback). |
| `user.finopal_user_id` | string ≤64 | No | Owner lookup. |
| `user.name` | string ≤255 | No | |
| `gateway.external_id` | string ≤64 | Required* | *one of `external_id` / `merchant_code` |
| `gateway.merchant_code` | string ≤64 | Required* | Dedup key. |
| `gateway.name` | string ≤255 | No | |
| `gateway.amount` | numeric ≥0 | No | |
| `gateway.website` | url ≤255 | No | |
| `gateway.callback_url` | url ≤255 | No | |

**Behavior**

- Owner not registered yet → **422** ("send the signup webhook first").
- Same `external_id`/`merchant_code` seen before → **200** with
  `gateway_created=false` (dedup).
- Reused `idempotency_key` for a **different** gateway → **422**.
- Otherwise create the gateway row.

**Response**

```json
{ "success": true, "message": "…", "user_id": "uuid", "gateway_id": "uuid", "gateway_sale_id": "uuid", "gateway_created": true, "gateway_skipped": false }
```

`gateway_id` and `gateway_sale_id` both map to the same stored row.

**Status codes:** `200` created / duplicate · `401` bad secret · `422` owner
missing, validation, or idempotency conflict.

---

## 3. Transaction

Records a transaction for an existing gateway and derives the **commission from
`profit`** (never the gateway `amount`), attaching the gateway's manager chain.

**Request**

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `event` | string ≤64 | No | |
| `merchant_id` | string ≤64 | **Yes** | Matches a stored gateway's merchant code. |
| `profit` | numeric ≥0 | **Yes** | Becomes the commission base. |
| `amount` | numeric ≥0 | No | Recorded for reference; not used for commission. |
| `transaction_id` | string ≤120 | No | External transaction id. |
| `idempotency_key` | string ≤120 | No | Dedup key. |
| `currency` | string ≤10 | No | |

**Behavior**

- Unknown `merchant_id` → **422**.
- Same transaction id / `idempotency_key` → **200** with
  `transaction_created=false` (idempotent).
- Otherwise record and set `commission = profit`.

**Response**

```json
{ "success": true, "message": "…", "gateway_id": "uuid", "transaction_id": "uuid", "commission": 50000, "transaction_created": true }
```

**Status codes:** `200` created / duplicate · `401` bad secret · `422` unknown
merchant or validation.

---

## Audit log

Every received webhook (regardless of outcome) writes a row to
`finopal_webhook_events` (`event`, `idempotency_key`, `payload`, `status`).
Failures in the audit write are swallowed so they never break the response.
