import { test, expect } from '@playwright/test'

// Full referral + finonet webhook lifecycle: A -> B -> C -> D, chained in
// dependency order so every action is exercised end-to-end against a live app.
//
// Prereqs (mirrors the other e2e specs): a migrated DB, and tinker available on
// PATH for DB reads/seed. Webhook secret resolves from .env (FINOPAL_WEBHOOK_SECRET).
const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000'
const SECRET = process.env.FINOPAL_WEBHOOK_SECRET || 'finopal-local-webhook-secret'
const ROOT = process.cwd()
const webhookHeaders = { 'X-Finopal-Webhook-Secret': SECRET, Accept: 'application/json' }

async function readPendingCode(email: string): Promise<string> {
  const { execSync } = await import('child_process')
  return execSync(
    `cd ${ROOT} && php artisan tinker --execute="echo Modules\\\\User\\\\Models\\\\PendingRegistration::where('email','${email}')->value('verification_code');"`,
    { encoding: 'utf-8' },
  ).trim()
}

test.describe.serial('Finopal referral webhook full flow', () => {
  test('A -> B -> C -> D: registration yields a referral code; webhooks chain on top of it', async ({ request }) => {
    test.skip(BASE_URL.includes('finolo.ir'), 'Requires local DB access to read the verification code')

    // ---- A: sign up WITHOUT finonet_ref -> referral option shown + code issued.
    const email = `e2e-referral-a-${Date.now()}@finolo.test`
    const password = 'StrongPass123!'

    const reg = await request.post(`${BASE_URL}/api/v1/auth/register`, {
      data: { name: 'E2E Referrer', email, password, password_confirmation: password, timezone: 'UTC' },
    })
    expect(reg.status()).toBe(201)
    const pendingToken = (await reg.json()).data.pending_token

    await request.post(`${BASE_URL}/api/v1/auth/pending/send-code`, { data: { pending_token: pendingToken } })
    await new Promise((r) => setTimeout(r, 500))
    const code = await readPendingCode(email)
    expect(code).toMatch(/^\d{6}$/)

    const verify = await request.post(`${BASE_URL}/api/v1/auth/pending/verify`, {
      data: { pending_token: pendingToken, code },
    })
    expect(verify.status()).toBe(200)
    const token = (await verify.json()).data.token

    const me = await request.get(`${BASE_URL}/api/v1/referral/me`, {
      headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
    })
    expect(me.status()).toBe(200)
    const meBody = (await me.json()).data
    expect(meBody.show_referral_option).toBe(true)
    expect(meBody.referral_code).toBeTruthy()
    expect(meBody.referral_link).toContain(`finonet_ref=${meBody.referral_code}`)

    const parentCode = meBody.referral_code as string
    const national = `9${Date.now()}`
    const mobile = `09${Date.now()}`

    // ---- B: signup webhook attaches a referred rep under the parent code.
    const signupPayload = {
      event: 'user.registered',
      referral_code: parentCode,
      user: { name: 'E2E Referred User', mobile, national_id: national },
    }
    const signup = await request.post(`${BASE_URL}/api/webhooks/finopal/signup`, { data: signupPayload, headers: webhookHeaders })
    expect(signup.status()).toBe(200)
    expect((await signup.json()).user_created).toBe(true)

    const signupAgain = await request.post(`${BASE_URL}/api/webhooks/finopal/signup`, { data: signupPayload, headers: webhookHeaders })
    expect(signupAgain.status()).toBe(200)
    expect((await signupAgain.json()).user_created).toBe(false)

    // ---- C: gateway webhook records a 100% sale, idempotent on re-send.
    const gatewayPayload = {
      user: { mobile, national_id: national },
      gateway: { external_id: `e2e-gw-${Date.now()}`, merchant_code: `MRC-${Date.now()}`, name: 'E2E Shop' },
    }
    const gateway = await request.post(`${BASE_URL}/api/webhooks/finopal/gateway`, { data: gatewayPayload, headers: webhookHeaders })
    expect(gateway.status()).toBe(200)
    const gatewayBody = await gateway.json()
    expect(gatewayBody.gateway_created).toBe(true)
    expect(gatewayBody.gateway_id).toBeTruthy()

    const gatewayAgain = await request.post(`${BASE_URL}/api/webhooks/finopal/gateway`, { data: gatewayPayload, headers: webhookHeaders })
    expect(gatewayAgain.status()).toBe(200)
    expect((await gatewayAgain.json()).gateway_created).toBe(false)

    // ---- D: transaction webhook credits commission from profit, idempotent.
    const txnPayload = { merchant_id: gatewayPayload.gateway.merchant_code, transaction_id: `TXN-${Date.now()}`, profit: 250000, amount: 1000000 }
    const txn = await request.post(`${BASE_URL}/api/webhooks/finopal/transaction`, { data: txnPayload, headers: webhookHeaders })
    expect(txn.status()).toBe(200)
    expect((await txn.json()).commission).toBe(250000)

    const txnAgain = await request.post(`${BASE_URL}/api/webhooks/finopal/transaction`, { data: txnPayload, headers: webhookHeaders })
    expect(txnAgain.status()).toBe(200)
    expect((await txnAgain.json()).transaction_created).toBe(false)
  })

  test('negative cases: wrong secret 401 and gateway-before-signup 422', async ({ request }) => {
    const bad = await request.post(`${BASE_URL}/api/webhooks/finopal/signup`, {
      data: { referral_code: 'X', user: { name: 'Nope', mobile: '0900000000', national_id: '0000000000' } },
      headers: { 'X-Finopal-Webhook-Secret': 'wrong-secret', Accept: 'application/json' },
    })
    expect(bad.status()).toBe(401)

    const orphan = await request.post(`${BASE_URL}/api/webhooks/finopal/gateway`, {
      data: { user: { mobile: `09${Date.now()}`, national_id: `orphan-${Date.now()}` }, gateway: { external_id: `orphan-${Date.now()}` } },
      headers: webhookHeaders,
    })
    expect(orphan.status()).toBe(422)
  })
})
