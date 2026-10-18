# Referral API

Two authenticated endpoints let a logged-in user see and (re)veal their own
referral code and share link. They are registered under your auth guard in
`Modules/Referral/routes/api.php`:

```php
Route::prefix('v1/referral')->middleware('auth:sanctum')->group(function () {
    Route::get('/me', [ReferralController::class, 'me']);
    Route::post('/activate', [ReferralController::class, 'activate']);
});
```

> Change `auth:sanctum` to your project's guard if you do not use Sanctum.

The current user is resolved through the `ReferralOwnerResolver` contract (see
[Integration](INTEGRATION.md)), so the controller never touches your `User`
model directly. An unresolvable (unauthenticated) user returns **401**.

---

## `GET /api/v1/referral/me`

Returns the caller's referral status.

```json
{
  "data": {
    "referral_enabled": true,
    "show_referral_option": true,
    "referral_code": "AB12CD",
    "referral_link": "https://finopal.ir/login?finonet_ref=AB12CD",
    "referred_users_count": 7
  }
}
```

| Field | Meaning |
| --- | --- |
| `referral_enabled` | Whether the referral subsystem is on (always `true` today). |
| `show_referral_option` | `false` when the user **arrived via finonet** (`arrived_via_finonet=true`) — they are already-referred, so they get no code/link. |
| `referral_code` / `referral_link` | `null` unless `show_referral_option` is `true`. |
| `referred_users_count` | How many accounts sit under this code. |

If the user has **no account yet**, the response shows
`show_referral_option=true` with `referral_code=null` — call `activate` to
provision one (or it is provisioned automatically on signup, see Integration).

---

## `POST /api/v1/referral/activate`

Lazily provisions a code + link for an eligible (non-finonet-arrived) user and
returns the same `data` shape as `/me`. It is **idempotent**: if the user
already has an account, the existing code is returned unchanged.

**Body:** none required (a validated empty request is accepted).

**Status codes:** `200` with `data` · `401` unauthenticated.

---

## Display rule (the two behaviors)

- **Arrived WITHOUT a `finonet_ref`** → normal representative: `show_referral_option=true`,
  they get their own code + link and can refer others.
- **Arrived WITH a `finonet_ref`** → already-referred: `show_referral_option=false`,
  no code/link is exposed.
