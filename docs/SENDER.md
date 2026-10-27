# Sender mode (outbound forwarding to finonet.ir)

By default this module is a **receiver**: finopal/finonet.ir POSTs the
`signup` / `gateway` / `transaction` webhooks *into* your app. The **sender**
mode is the mirror image and is **opt-in**: when a person signs up **on your
host** carrying a `?finonet_ref=<code>` link parameter, your app forwards a
signup webhook **out** to the sales organization (`https://finonet.ir`), exactly
the way finopal would, so the referrer is credited there.

Use sender mode when your project is a **host product that refers its own users
into the finonet sales network** (Finolo and FinoCard both do this). Pure
sales-organization receivers can ignore everything below.

## The contract it mirrors

The outbound call is the *same* shape finopal sends inbound (see
[WEBHOOKS.md](WEBHOOKS.md)), POSTed to the finonet.ir endpoint:

```
POST https://finonet.ir/api/webhooks/finopal/signup
X-Finopal-Webhook-Secret: <FINONET_WEBHOOK_SECRET>
Content-Type: application/json

{
  "event": "user.registered",
  "referral_code": "<the finonet_ref the visitor arrived with>",
  "user": { "name": "...", "mobile": "09xxxxxxxxx", "national_id": "..." }
}
```

`finonet_ref` (the sales-org parameter in the referral link) is **not** finopal's
internal `code` parameter. It is fixed by the contract, not configurable.
`https://finopal.ir/register` is 404 — the link lands on `/login` and finopal
preserves `finonet_ref` through to the signup webhook.

## Configuration

Add to `.env` (config key `referral.forward`):

```dotenv
FINONET_FORWARD_SIGNUP_ENABLED=true
FINONET_SIGNUP_WEBHOOK_URL=https://finonet.ir/api/webhooks/finopal/signup
# OUTBOUND secret — SEPARATE from the inbound FINOPAL_WEBHOOK_SECRET.
# This is the secret the finonet.ir RECEIVER expects on /api/webhooks/finopal/*.
FINONET_WEBHOOK_SECRET=<provided by the finonet team>
FINONET_FORWARD_TIMEOUT=15
```

> **Never reuse the inbound secret for outbound.** Inbound guards *your*
> endpoints; outbound must match what *finonet.ir* expects. They are independent
> secrets. `secret` falls back to empty (forwarding then logs
> `referral.forward_skipped` and does nothing) — never hard-code a real value.

## Wiring the hook into your signup

The shipped job is `Modules\Referral\Jobs\ForwardSignupToFinonet`. Dispatch it
from the place where **your host creates a brand-new user**, passing the raw
`finonet_ref`. Follow the four pitfalls:

1. **Fire only when the code is non-empty.** An absent/blank `finonet_ref` must
   never send (you would credit the wrong/root referrer).
2. **New users only.** Re-forwarding an existing account on every login would
   double-credit; guard on "just created".
3. **Best-effort.** Wrap the dispatch in `try/catch`; a forward failure must
   never break registration. Inside the job, a **4xx is logged and not retried**
   (permanent: validation / unknown code) and a **5xx/network error rethrows** so
   the queue retries (`tries=3`, `backoff=30`).
4. **Send what the receiver needs.** The signup matcher at finonet resolves a
   person by `national_id` → `mobile` → `finopal_user_id`; include as many as you
   have (at minimum `mobile`).

```php
use Modules\Referral\Jobs\ForwardSignupToFinonet;

// After your user row is created (inside your signup/OTP-verify action):
$referralRef = trim((string) ($data['finonet_ref'] ?? ''));   // captured from ?finonet_ref
if ($isNewUser && $referralRef !== '') {
    try {
        ForwardSignupToFinonet::dispatch(
            $referralRef,
            $user->name,
            $mobile /* local 09xxxxxxxxx */,
            $nationalId /* nullable */,
        );
    } catch (\Throwable $e) {
        report($e);   // never break signup
    }
}
```

The job is queued — run a **queue worker** (`php artisan queue:work`) or set
`QUEUE_CONNECTION=sync` for tests. Dispatching **after** the DB transaction
commits avoids the job running before the user row exists.

## Scope: what a host forwards

A host product forwards **signup only**. The `gateway` and `transaction`
webhooks are emitted by finopal's **payment platform**, not by the referring
host, so the host normally does not send them. If (for a test, or a bespoke
integration) you want a billing record to reach finonet too, POST the same
`/gateway` then `/transaction` contract directly with the outbound secret — see
`examples/finonet-sender-smoke.sh`. Remember: gateway `amount` is **not** a
commission; commission is derived from the `profit` field on the transaction.

## Full real-flow verification

Because the outbound endpoint is a live production panel, verify in **three
layers** (see [TESTING.md](TESTING.md) Step 4 for the exact commands):

1. **PHPUnit** (`Http::fake`) — contract shape, header, and the four pitfalls:
   `tests/Feature/Modules/Referral/ForwardSignupToFinonetTest.php`.
2. **Live curl** — POST a signup to `https://finonet.ir/api/webhooks/finopal/signup`
   with a real referral code and the outbound secret; expect **200** and
   `user_created=true`.
3. **Playwright → log into finonet.ir** — confirm the referred user actually
   appears in the finonet team tree, and (if you also POSTed gateway +
   transaction) the sale/commission shows under **فروش و پورسانت**.
   `examples/finonet-sender-verify.spec.ts`.

### Reference test data (throwaway test panel, not production users)

| Item | Value |
| --- | --- |
| Sales-org panel | `https://finonet.ir` (login button: «ورود به حساب») |
| Referral code used in examples | `QM4VGJ7XBL` |
| Referral link format | `https://finopal.ir/login?finonet_ref=QM4VGJ7XBL` |
| Team tree | `/dashboard/<role>/team` (e.g. `senior-manager`) |
| Login credentials | provided **out of band** by the finonet team — inject via env; **never commit them** |

> **Attribution note.** The new user lands under whichever finonet account
> *owns* `referral_code`. Always confirm the owner of the code you send — a code
> that maps to a top-level account will credit that account, not the rep you
> intended. View the tree (or ask the finonet team) to confirm the parent before
> trusting the result.
