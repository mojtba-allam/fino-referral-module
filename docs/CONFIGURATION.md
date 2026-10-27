# Configuration

All settings live in `Modules/Referral/config/referral.php` and are read from
the environment. The provider merges this file under the `referral` config name,
so you access values with `config('referral.<key>')`. **Never use `env()`
outside the config file** — set the env vars and read through `config()`.

After any change run `php artisan config:clear` (and `php artisan route:clear`
if you toggled a webhook endpoint, since routes are registered at boot).

## Environment reference — receiver (inbound)

| Env var | Config key | Default | Required | Notes |
| --- | --- | --- | --- | --- |
| `FINOPAL_WEBHOOK_SECRET` | `webhook_secret` | *(none)* | **Yes** | Shared secret guarding **all three** inbound webhooks. Set a long random value; it must equal the secret finopal sends. Empty → every webhook returns **401**. |
| `FINOPAL_SANDBOX` | `sandbox` | `true` | No | Sandbox flag. Informational for outbound/host choice. |
| `FINOPAL_API_BASE` | `api_base` | `https://sandbox.finopal.ir` | No | finopal integration host (used to build links / any future outbound). |
| `FINOPAL_REFERRAL_LINK_BASE` | `referral.link_base` | `https://finopal.ir/login` | No | Base of the shared referral link. For sandbox use `https://sandbox.finopal.ir/login`. |
| `FINOPAL_REFERRAL_CODE_LENGTH` | `referral.code_length` | `6` | No | Length of generated referral codes (unambiguous alphabet). |
| `FINOPAL_REFERRAL_ROOT_CODE` | `referral.root_code` | `ROOT01` | No | Code used by the seeder for the root representative node. |
| `FINOPAL_WELCOME_SMS_ENABLED` | `welcome_sms.enabled` | `true` | No | Whether the signup webhook queues a welcome message for a newly created representative. |
| `FINOPAL_WELCOME_SMS_TEMPLATE` | `welcome_sms.template` | Persian welcome text | No | Template with `{name}` and `{link}` tokens, replaced at send time. |
| `FINOPAL_REPRESENTATIVE_ROLE` | `representative_role` | `Representative` | No | Optional role name your host adapter may attach to referral-created users. Purely advisory to the module. |
| `FINOPAL_WEBHOOK_SIGNUP_ENABLED` | `webhooks.signup` | `true` | No | Register the `/signup` webhook route. |
| `FINOPAL_WEBHOOK_GATEWAY_ENABLED` | `webhooks.gateway` | `true` | No | Register the `/gateway` webhook route. |
| `FINOPAL_WEBHOOK_TRANSACTION_ENABLED` | `webhooks.transaction` | `true` | No | Register the `/transaction` webhook route. |

> The referral link is `"<link_base>?finonet_ref=<code>"`. `finonet_ref` is the
> fixed query parameter finopal preserves and echoes back as `referral_code` in
> the signup webhook. It is **not** configurable — it is part of the contract.

## Environment reference — sender (outbound, optional)

Only used when the host forwards a referred signup to the finonet sales
organization. See [SENDER.md](SENDER.md) for wiring.

| Env var | Config key | Default | Required | Notes |
| --- | --- | --- | --- | --- |
| `FINONET_FORWARD_SIGNUP_ENABLED` | `forward.enabled` | `false` | No | Master switch for outbound forwarding. Off by default so a unit run never POSTs to the real panel. |
| `FINONET_SIGNUP_WEBHOOK_URL` | `forward.signup_url` | `https://finonet.ir/api/webhooks/finopal/signup` | No | The finonet.ir **receiver** endpoint the host posts the signup webhook to. |
| `FINONET_WEBHOOK_SECRET` | `forward.secret` | *(empty)* | For sender | **OUTBOUND** secret. Must equal what finonet.ir expects on `/api/webhooks/finopal/*`. **Deliberately separate** from the inbound `FINOPAL_WEBHOOK_SECRET`. Empty → forwarding is skipped (`referral.forward_skipped`). |
| `FINONET_FORWARD_TIMEOUT` | `forward.timeout` | `15` | No | Outbound HTTP timeout (seconds). |

> **Never reuse the inbound secret for outbound, and never commit either.** The
> outbound secret is provided by the finonet team. `ForwardSignupToFinonet` is
> best-effort: a 4xx is logged and **not** retried; a 5xx/network error rethrows
> so the queue retries (`tries=3`, `backoff=30`); it never breaks signup.

## Sandbox vs production

The module ships **sandbox-first** so you can integrate and test safely:

```dotenv
FINOPAL_SANDBOX=true
FINOPAL_API_BASE=https://sandbox.finopal.ir
FINOPAL_REFERRAL_LINK_BASE=https://sandbox.finopal.ir/login
```

Switch to production when you go live:

```dotenv
FINOPAL_SANDBOX=false
FINOPAL_API_BASE=https://finopal.ir
FINOPAL_REFERRAL_LINK_BASE=https://finopal.ir/login
```

## Per-project webhook toggles

Each inbound endpoint is opt-in. Disabled endpoints are **not registered**, so a
call to them returns **404** (not 401) — the route simply does not exist.

- A **PMS** (no payment gateways) typically keeps only signup:
  ```dotenv
  FINOPAL_WEBHOOK_SIGNUP_ENABLED=true
  FINOPAL_WEBHOOK_GATEWAY_ENABLED=false
  FINOPAL_WEBHOOK_TRANSACTION_ENABLED=false
  ```
- A **sales / payment** project keeps all three (the defaults).

Because the toggles are read while registering routes (at boot), clear the route
cache after changing them: `php artisan route:clear`.

## Generating a strong secret

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Put the output in `FINOPAL_WEBHOOK_SECRET` in `.env`, give the same value to
finopal, then `php artisan config:clear`.

## Full `.env.example`

```dotenv
# --- Receiver (inbound) ---
FINOPAL_WEBHOOK_SECRET=
FINOPAL_SANDBOX=true
FINOPAL_API_BASE=https://sandbox.finopal.ir
FINOPAL_REFERRAL_LINK_BASE=https://sandbox.finopal.ir/login
FINOPAL_REFERRAL_CODE_LENGTH=6
FINOPAL_REFERRAL_ROOT_CODE=ROOT01
FINOPAL_WELCOME_SMS_ENABLED=true
# FINOPAL_WELCOME_SMS_TEMPLATE=...
# FINOPAL_REPRESENTATIVE_ROLE=Representative
FINOPAL_WEBHOOK_SIGNUP_ENABLED=true
FINOPAL_WEBHOOK_GATEWAY_ENABLED=true
FINOPAL_WEBHOOK_TRANSACTION_ENABLED=true

# --- Sender (outbound, optional) — see docs/SENDER.md ---
FINONET_FORWARD_SIGNUP_ENABLED=false
FINONET_SIGNUP_WEBHOOK_URL=https://finonet.ir/api/webhooks/finopal/signup
FINONET_WEBHOOK_SECRET=
FINONET_FORWARD_TIMEOUT=15
```
