# Integration

The module core never references a concrete `User` model, your SMS gateway, or
your auth guard. Everything host-specific flows through **three contracts**,
bound to adapters in one place: `ReferralServiceProvider`.

## The three contracts

| Contract | Method(s) | Purpose |
| --- | --- | --- |
| `Contracts\ReferralOwner` | `key()`, `type()`, `name()`, `mobile()`, `email()` | Normalizes your user into the shape the module needs. `type()` is the polymorphic `owner_type` (use `getMorphClass()`). |
| `Contracts\ReferralOwnerResolver` | `current(): ?ReferralOwner` | Resolves the authenticated user for the referral API. |
| `Contracts\ReferralNotifier` | `queueWelcome(string $mobile, ?string $name, string $link): void` | Delivers the welcome message. Webhook-created reps have **no host user**, so it receives the raw contact. |

## The shipped adapters are a Finolo example

`Adapters/` contains a **working Finolo implementation**. Copy them, then swap
the internals for your own project:

- `Adapters\UserReferralOwner` — wraps `Modules\User\Models\User` behind `ReferralOwner`.
- `Adapters\FinoloOwnerResolver` — returns the authenticated user wrapped as a `ReferralOwner`.
- `Adapters\SmsReferralNotifier` — renders the template and dispatches `SendReferralWelcomeSms` through your SMS gateway.
- `Jobs\SendReferralWelcomeSms` — queued job calling your `SmsGatewayInterface`.

A different project replaces the model/gateway references and rebinds the two
interfaces in the provider. To start from nothing, bind a no-op notifier:

```php
final class NullReferralNotifier implements ReferralNotifier
{
    public function queueWelcome(string $mobile, ?string $name, string $link): void {}
}
```

## Binding in the provider

`ReferralServiceProvider::register()` merges config, binds the five
repositories, and binds the host seams:

```php
$this->mergeConfigFrom(__DIR__.'/config/referral.php', 'referral');

// Data access (Repository-First)
$this->app->bind(ReferralAccountRepositoryInterface::class, EloquentReferralAccountRepository::class);
$this->app->bind(ReferralRelationRepositoryInterface::class, EloquentReferralRelationRepository::class);
$this->app->bind(GatewayRepositoryInterface::class, EloquentGatewayRepository::class);
$this->app->bind(TransactionRepositoryInterface::class, EloquentTransactionRepository::class);
$this->app->bind(WebhookEventRepositoryInterface::class, EloquentWebhookEventRepository::class);

// Host seams  <-- EDIT THESE for your project
$this->app->bind(ReferralOwnerResolver::class, FinoloOwnerResolver::class);
$this->app->bind(ReferralNotifier::class, SmsReferralNotifier::class);
```

## Hooking your own signup flow

When a **new user registers in your app**, call `ReferralRegistrar::onRegistered`
so the module records them as a representative:

- If the signup carried a valid `finonet_ref` → the user becomes a
  **finonet-arrived** rep (referral option hidden), placed under that code's
  parent, with the ancestor chain recorded.
- If it carried **no** (or an unknown) code → the user gets a **normal** referral
  account with their own code (they can refer others). The unknown-code case
  never blocks signup.

```php
use Modules\Referral\Contracts\ReferralOwner;
use Modules\Referral\Services\ReferralRegistrar;

// Right after your user row is created (e.g. in a UserCreated listener):
$owner = new UserReferralOwner($user); // your adapter
$this->registrar->onRegistered($owner, $referralRef /* from ?finonet_ref */);
```

**Capturing `finonet_ref`.** Finolo reads the signup query param and stores it on
the pending registration, then passes it to `onRegistered` after verification.
Reproduce the same idea: capture `?finonet_ref=<code>` at signup and hand it to
the registrar when the account is created. Wrap the call in a `try/catch` so a
referral failure never breaks registration.

## Base controller

The API controllers extend `App\Http\Controllers\Controller` (Finolo's base).
In another project, change them to your framework's base controller or
`Illuminate\Routing\Controller`.

## Auth guard

`routes/api.php` guards `/v1/referral/*` with `auth:sanctum`. Swap for your
guard. The webhook routes are public and guarded only by `VerifyFinopalWebhook`.

## <a name="identifiers"></a> Identifiers

All module tables use **UUID** primary keys (`HasUuids`). So webhook responses
(`user_id`, `gateway_id`, `transaction_id`) and `/referral/me` return UUIDs even
though the finopal docs show integers. If your integration truly requires
numeric ids, add a numeric surrogate column and return it instead.

## Translations

The module ships `lang/en` and `lang/fa`, loaded under the `referral` namespace
(`__('referral::webhook.signup.duplicate')`, etc.). The controllers also reference
a host key `__('api.errors.unauthorized')` for the 401 — provide it or replace it.

Validation messages in the request classes are intentionally Persian, matching
the finonet.ir contract; adjust per your audience.
