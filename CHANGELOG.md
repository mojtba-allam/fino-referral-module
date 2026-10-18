# Changelog

All notable changes to the **Fino Referral Module** are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0]

### Added
- Portable `Modules/Referral` module implementing the finopal/finonet.ir
  **inbound** referral contract: `signup`, `gateway`, and `transaction` webhooks.
- Authenticated referral API: `GET /api/v1/referral/me`, `POST /api/v1/referral/activate`.
- Host-agnostic seams via three contracts (`ReferralOwner`,
  `ReferralOwnerResolver`, `ReferralNotifier`) with a working Finolo adapter set.
- Module-owned, polymorphic-owner data model (never touches the host `users`
  table): `referral_accounts`, `referral_relations`, `finopal_gateways`,
  `finopal_transactions`, `finopal_webhook_events`.
- Repository-First layering for five aggregates, actions, services, form
  requests, resources, backed enums, queued welcome-SMS job, factories, and an
  idempotent root-representative seeder.
- Per-project webhook toggles (`webhooks.{signup,gateway,transaction}`) and
  sandbox-first configuration (`FINOPAL_SANDBOX`, `FINOPAL_API_BASE`, referral
  link base).
- National id stored and used as the primary match key
  (`national_id → mobile → finopal_user_id`), required only on the referral
  path.
- Documentation: README, Configuration, Webhooks, Referral API, Integration,
  Architecture, Testing.
- Examples: PHPUnit suite, curl smoke script, Playwright A→B→C→D full flow.

[1.0.0]: #v100
