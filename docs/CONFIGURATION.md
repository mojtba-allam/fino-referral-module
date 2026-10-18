# Configuration

All settings live in `Modules/Referral/config/referral.php` and are read from
the environment. The provider merges this file under the `referral` config name,
so you access values with `config('referral.<key>')`. **Never use `env()`
outside the config file** — set the env vars and read through `config()`.

After any change run `php artisan config:clear` (and `php artisan route:clear`
if you toggled a webhook endpoint, since routes are registered at boot).

## Environment reference

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
```
