# Architecture

The module follows a layered, Repository-First Laravel structure so it can be
copied into any project using the same convention.

## Folder layout

```
Modules/Referral/
├── Actions/            Single-purpose steps, each with execute()
│   ├── ProvisionReferralCode
│   ├── ResolveWebhookOwner
│   ├── RegisterSignupFromWebhook
│   ├── RegisterGatewayFromWebhook
│   └── RegisterTransactionFromWebhook
├── Adapters/           Host glue (Finolo example): owner, resolver, notifier
├── Contracts/          ReferralOwner, ReferralOwnerResolver, ReferralNotifier
├── Database/
│   ├── Factories/      Model factories
│   ├── migrations/     The five module tables
│   └── Seeders/        ReferralSeeder (idempotent root representative)
├── Enums/              ReferralAccountStatus, GatewayStatus, TransactionStatus
├── Events/ Listeners/  Optional host event wiring
├── Filters/            Query filters
├── Http/
│   ├── Controllers/Api/V1/   Signup/Gateway/Transaction webhooks + ReferralController
│   ├── Middleware/     VerifyFinopalWebhook
│   └── Requests/       FinopalSignup/Gateway/Transaction + ActivateReferral
├── Jobs/               SendReferralWelcomeSms (ShouldQueue)
├── Models/             ReferralAccount, ReferralRelation, Gateway, FinopalTransaction, WebhookEvent
├── Policies/
├── Repositories/       Interface + Eloquent impl per aggregate
├── Resources/          ReferralAccountResource, GatewayResource
├── Services/           ReferralService, ReferralRegistrar, FinopalWebhookService
├── Support/            ReferralCodeGenerator (unambiguous alphabet)
├── config/referral.php
├── lang/{en,fa}
├── routes/api.php
└── ReferralServiceProvider.php
```

## Layers & responsibilities

- **Repositories** own all data access; each aggregate has an interface + an
  Eloquent implementation (five aggregates: `ReferralAccount`,
  `ReferralRelation`, `Gateway`, `Transaction`, `WebhookEvent`).
- **Actions** perform one orchestrated step and are the units services compose.
- **Services** orchestrate actions + repositories. `FinopalWebhookService` is
  the single inbound entry point: it dispatches to the right action and writes
  the `finopal_webhook_events` audit row. `ReferralService` backs `/referral/me`
  and `/activate`. `ReferralRegistrar` is the one public method a host calls on
  signup.
- **Form Requests** validate the webhook + activate payloads and carry custom
  (Persian) messages.
- **Resources** shape API output.
- **Backed enums** with `values()` / `label()` for statuses.
- **Provider** self-registers config, migrations, routes and translations, and
  binds repositories + host contracts. No publishing step is required.

## Data model

All tables are **module-owned**; the host `users` table is never modified.

### `referral_accounts`
`id (uuid)`, `owner_type`, `owner_id`, `referral_code (unique)`,
`referred_by_code`, `arrived_via_finonet (bool)`, `national_id`, `mobile`,
`finopal_user_id`, `referrer_owner_id`, `referral_path` (comma-joined ancestor
ids), `name`, `email`, `status`, timestamps. Unique on (`owner_type`,
`owner_id`); indexed on `national_id`, `mobile`, `finopal_user_id`.
`national_id` is stored **only** for referred/webhook users and is never written
to the host user record.

### `referral_relations`
Edges `referrer_referral_id → referred_referral_id` with `depth`, for tree
traversal. A composite index `referral_relations_pair_idx` supports lookups.

### `finopal_gateways`
`id (uuid)`, `referral_account_id`, `owner_type`/`owner_id`, `external_id
(unique)`, `merchant_code (unique)`, `name`, `amount`, `website`, `callback_url`,
`status`, `share_percent (default 100)`, `manager_path (json)`, `idempotency_key
(unique)`, timestamps.

### `finopal_transactions`
`id (uuid)`, `gateway_id`, `merchant_code`, `referral_account_id`,
`external_txn_id`, `idempotency_key (unique)`, `amount`, `profit`, `commission`,
`currency`, `status`, `manager_path (json)`, `payload (json)`, timestamps.
`commission` is derived from `profit`, never `amount`.

### `finopal_webhook_events`
`id`, `event`, `idempotency_key`, `payload (json)`, `status`, timestamps. Audit
+ idempotency log for every received webhook.

## Referral tree

Two complementary representations are kept:

- A **materialized path** (`referral_path` on the account) for fast ancestor
  reads (used to build `manager_path` on gateways/transactions).
- **Edge rows** in `referral_relations` for explicit parent→child traversal.

## Code generation

`Support\ReferralCodeGenerator` produces fixed-length codes from an unambiguous
alphabet (no confusable characters), checking the repository for collisions, and
builds the share link as `<referral.link_base>?finonet_ref=<code>`.
