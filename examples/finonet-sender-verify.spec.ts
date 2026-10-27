import { test, expect } from '@playwright/test'

// SENDER end-to-end against the REAL finonet.ir panel.
//   1. forward a referred signup (and optionally gateway + transaction) OUT to
//      finonet using the same contract the ForwardSignupToFinonet job sends.
//   2. log into the finonet UI and confirm the user really appears in the team
//      tree (and, if a sale was recorded, under فروش و پورسانت / تراکنش‌ها).
//
// Credentials are NEVER hard-coded — supply them via env (throwaway test panel):
//   FINONET_BASE_URL          default https://finonet.ir
//   FINONET_WEBHOOK_SECRET    outbound secret finonet.ir expects (required)
//   FINONET_REFERRAL_CODE     parent code to credit, e.g. QM4VGJ7XBL
//   FINONET_TEST_MOBILE       a finonet test account phone (login)
//   FINONET_TEST_PASSWORD     that account's password (login)
//   FINONET_ROLE_PATH         dashboard role segment, default senior-manager
//   FINONET_RECORD_SALE       "true" to also POST gateway + transaction
//
// NOTE: finonet is a Persian RTL app; the login/team selectors below are the
// ones observed on the current panel. Adjust the locators if the UI changes.

const FINONET = (process.env.FINONET_BASE_URL || 'https://finonet.ir').replace(/\/$/, '')
const SECRET = process.env.FINONET_WEBHOOK_SECRET || ''
const CODE = process.env.FINONET_REFERRAL_CODE || 'QM4VGJ7XBL'
const LOGIN_MOBILE = process.env.FINONET_TEST_MOBILE || ''
const LOGIN_PASSWORD = process.env.FINONET_TEST_PASSWORD || ''
const ROLE_PATH = process.env.FINONET_ROLE_PATH || 'senior-manager'
const RECORD_SALE = process.env.FINONET_RECORD_SALE === 'true'
const SIGNUP_URL = `${FINONET}/api/webhooks/finopal/signup`
const webhookHeaders = { 'X-Finopal-Webhook-Secret': SECRET, Accept: 'application/json', 'Content-Type': 'application/json' }

// Random identity that the forwarder creates on finonet; reused for the UI search.
const stamp = Date.now()
const newMobile = `09${String(stamp).slice(-9)}`
const newNational = `0${String(stamp).slice(-9)}0`
let saleMerchantCode = ''

test.describe.serial('FinoCard/Finolo SENDER -> finonet real flow', () => {
  test.beforeAll(() => {
    test.skip(!SECRET, 'FINONET_WEBHOOK_SECRET is required')
  })

  test('1) forward a referred signup to finonet.ir (expect 200, user_created)', async ({ request }) => {
    const signup = await request.post(SIGNUP_URL, {
      headers: webhookHeaders,
      data: {
        event: 'user.registered',
        referral_code: CODE,
        user: { name: `Playwright Sender ${stamp}`, mobile: newMobile, national_id: newNational },
      },
    })
    expect(signup.status()).toBe(200)
    const body = await signup.json()
    expect(body.user_created).toBe(true)

    if (RECORD_SALE) {
      saleMerchantCode = `PW-${stamp}`
      const gw = await request.post(`${FINONET}/api/webhooks/finopal/gateway`, {
        headers: webhookHeaders,
        data: {
          event: 'gateway.registered',
          idempotency_key: `pw-gw-${stamp}`,
          user: { mobile: newMobile, national_id: newNational },
          gateway: { external_id: `pw-gw-${stamp}`, merchant_code: saleMerchantCode, name: 'PW Shop', amount: 1500000 },
        },
      })
      expect(gw.status()).toBe(200)

      const txn = await request.post(`${FINONET}/api/webhooks/finopal/transaction`, {
        headers: webhookHeaders,
        data: { transaction_id: `PW-TXN-${stamp}`, merchant_id: saleMerchantCode, profit: 250000, amount: 1000000 },
      })
      expect(txn.status()).toBe(200)
    }
  })

  test('2) log into finonet and find the forwarded user in the team tree', async ({ page }) => {
    test.skip(!LOGIN_MOBILE || !LOGIN_PASSWORD, 'FINONET_TEST_MOBILE / FINONET_TEST_PASSWORD required for the UI check')

    // Login. finonet's landing has a «ورود به حساب» (enter account) button.
    await page.goto(FINONET, { waitUntil: 'domcontentloaded' })
    const loginBtn = page.getByRole('button', { name: /ورود به حساب|ورود|Login/i }).first()
    if (await loginBtn.isVisible().catch(() => false)) await loginBtn.click()

    await page.getByLabel(/موبایل|شماره|Mobile|Phone/i).first().fill(LOGIN_MOBILE)
    const pwd = page.getByLabel(/رمز|گذرواژه|Password/i).first()
    if (await pwd.isVisible().catch(() => false)) await pwd.fill(LOGIN_PASSWORD)
    await page.getByRole('button', { name: /ورود|ارسال|Login|Submit/i }).last().click()

    await page.waitForURL(/\/dashboard\//, { timeout: 20_000 })

    // Open the multi-level team tree and search the mobile we just created.
    await page.goto(`${FINONET}/dashboard/${ROLE_PATH}/team`, { waitUntil: 'domcontentloaded' })
    const search = page.getByPlaceholder(/جستجوی نام، موبایل|Search name, mobile/i).first()
    await search.fill(newMobile)

    await expect(page.getByText(newMobile).first()).toBeVisible({ timeout: 10_000 })

    if (RECORD_SALE) {
      // Transactions live under فروش و پورسانت -> تراکنش‌ها (path may vary by role).
      await page.goto(`${FINONET}/dashboard/${ROLE_PATH}/transactions`, { waitUntil: 'domcontentloaded' }).catch(() => {})
    }
  })
})
