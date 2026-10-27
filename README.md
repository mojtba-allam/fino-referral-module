# Fino Referral Module

A portable, self-contained **Laravel module** (`Modules/Referral`) that adds a
referral subsystem and acts as the receiving "sales organization" side of the
**finopal / finonet.ir** integration. It **inbound-receives** the three
documented webhooks — `signup`, `gateway`, and `transaction` — and exposes a
small authenticated referral API so users can see and share their own referral
code.

The module owns its **own database tables** keyed by a *polymorphic owner*, so
it never touches your `users` table and never depends on your user model. Your
project plugs in through **three small contracts** (identity, resolver,
notifier). Copy the folder into any Laravel app that follows the same layered
module convention and it works.

> Core is a **receiver**: finopal is configured (on its side) to POST the
> webhooks to your endpoints, signed with a shared secret. An **optional sender**
> mode lets your host forward a referred signup out to `finonet.ir` (opt-in via
> `referral.forward`) when a user arrives with `?finonet_ref=<code>` — see
> [docs/SENDER.md](docs/SENDER.md).

---

## Features

- **Inbound webhooks** (secret-guarded, per the finopal contract):
  - `POST /api/webhooks/finopal/signup` — create a referred representative under
    a referral code, queue a welcome message, or skip if the user already exists.
  - `POST /api/webhooks/finopal/gateway` — record a gateway "sale" (100% share)
    for an already-registered representative.
  - `POST /api/webhooks/finopal/transaction` — record a transaction; commission
    is derived from `profit` (never the gateway `amount`) and split along the
    representative's manager chain.
- **Referral API** for your logged-in users:
  - `GET /api/v1/referral/me` — referral status, code and share link.
  - `POST /api/v1/referral/activate` — lazily provision a code/link.
- **Optional sender mode** — forward a referred signup from your host out to the
  finonet.ir sales organization (`Modules\Referral\Jobs\ForwardSignupToFinonet`),
  best-effort and secret-guarded, when a user arrives with `?finonet_ref=<code>`.
- **Per-project webhook toggles** — enable/disable each inbound endpoint via
  config (a PMS can disable `gateway` + `transaction`; sales projects keep all three).
- **Sandbox-first** — the shared referral link and integration host point at the
  finopal sandbox by default and switch to production with one env change.
- **Idempotent & audited** — dedup by national id / mobile / external ids,
  idempotency keys, and a `finopal_webhook_events` audit log.
- **Host-agnostic** — Repository-First layering, form-request validation, API
  resources, backed enums, queued jobs, factories, seeder, and tests.

## Requirements

- PHP **8.2+**
- Laravel **11 / 12** (uses the slim `bootstrap/app.php` + `bootstrap/providers.php` structure)
- A queue worker (the welcome SMS job and other jobs are queued; `sync` also works)
- `auth:sanctum` (or your guard) for the referral API
- **Sanctum** is referenced by the referral API routes; adjust if you use a different guard (see [Integration](docs/INTEGRATION.md))

## Installation (copy-in)

1. **Copy the module folder** into your project so it lives at
   `Modules/Referral` (preserving the internal structure).

2. **Make it autoloadable.** Add a PSR-4 mapping in your root `composer.json`
   (this repo's [composer.json](composer.json) shows the mapping):

   ```json
   {
     "autoload": {
       "psr-4": {
         "Modules\\Referral\\": "Modules/Referral/"
       }
     }
   }
   ```

   Then run `composer dump-autoload`.

3. **Register the provider** in `bootstrap/providers.php`:

   ```php
   return [
       // ...
       Modules\Referral\ReferralServiceProvider::class,
   ];
   ```

   The provider self-registers its config, migrations, routes and translations —
   you do **not** need to publish anything or edit `routes/*.php`.

4. **Bind your host adapters** to the three contracts in the provider (the
   shipped `Adapters/` are a working **Finolo example**). See
   [Integration](docs/INTEGRATION.md).

5. **Configure the environment** — copy the variables from
   [docs/CONFIGURATION.md](docs/CONFIGURATION.md) into your `.env` and set a
   strong `FINOPAL_WEBHOOK_SECRET`. Then:

   ```bash
   php artisan config:clear
   php artisan migrate
   ```

## Quickstart

Hand a representative their code + link (from the referral API), then let
finopal call your webhook:

```bash
curl -X POST http://your-app.test/api/webhooks/finopal/signup \
  -H "X-Finopal-Webhook-Secret: <your-secret>" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "referral_code": "AB12CD",
    "user": {
      "name": "Jane Doe",
      "mobile": "09120000000",
      "national_id": "0012345678"
    }
  }'
```

```json
{ "success": true, "message": "...", "user_id": "uuid", "user_created": true, "user_skipped": false, "welcome_sms_queued": true }
```

A wrong or missing secret returns **401**; an unknown `referral_code` for a new
user returns **422**.

## Documentation

| Doc | What it covers |
| --- | --- |
| [Configuration](docs/CONFIGURATION.md) | Every `config/referral.php` key and env var, webhook toggles, sandbox vs production |
| [Webhooks](docs/WEBHOOKS.md) | The three inbound endpoints: headers, request fields, responses, status codes, matching + idempotency rules |
| [Referral API](docs/REFERRAL_API.md) | `GET /referral/me` and `POST /referral/activate` |
| [Integration](docs/INTEGRATION.md) | The contracts, the example adapters, provider bindings, and the post-signup hook |
| [Sender mode](docs/SENDER.md) | Opt-in outbound forwarding of a referred signup to finonet.ir: config, wiring, pitfalls, verification |
| [Architecture](docs/ARCHITECTURE.md) | Layer-by-layer structure and the database schema |
| [Testing](docs/TESTING.md) | The PHPUnit suite, curl smoke script, and Playwright flow in `examples/` |

## Data model

All tables are module-owned (never the host `users` table):

| Table | Purpose |
| --- | --- |
| `referral_accounts` | One row per representative (polymorphic `owner_type`/`owner_id`), code, `national_id`, `mobile`, arrival flag, materialized `referral_path` |
| `referral_relations` | Tree edges `referrer → referred` with `depth` |
| `finopal_gateways` | Recorded gateway sales (100% share, manager path) |
| `finopal_transactions` | Inbound transaction/commission ledger (commission from `profit`) |
| `finopal_webhook_events` | Audit / idempotency log of every received webhook |

## Portability caveats (read before using in a new project)

The module core is host-agnostic, but the shipped glue is written for **Finolo**.
When copying into a different project you must adjust:

- `Adapters/*` (reference `Modules\User`, `Modules\Otp`) → replace with your own adapters and rebind in the provider.
- `Http/Controllers/**` extend `App\Http\Controllers\Controller` → point at your base controller.
- `routes/api.php` uses `auth:sanctum` → use your guard.
- Any `__('api.errors.unauthorized')` translation → your host lang key.

These are the **only** seams; everything else (repositories, services, actions,
models, migrations) is framework-standard and portable.

## Testing / examples

`examples/` contains runnable curl smoke scripts and Playwright specs for both
sides: the receiver lifecycle (signup → dedup → gateway → transaction → negative
cases) and the sender real-flow (`finonet-sender-smoke.sh` +
`finonet-sender-verify.spec.ts`, which forwards to `finonet.ir` and then logs
into the panel to confirm the record in the team tree). See [Testing](docs/TESTING.md).

## License

Released under the MIT License. See [LICENSE](LICENSE).
