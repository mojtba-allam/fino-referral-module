<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Finopal Inbound Webhook Secret
    |--------------------------------------------------------------------------
    |
    | Shared secret used by finopal/finonet.ir to sign the signup, gateway and
    | transaction webhooks it sends to this sales organization. It echoes the
    | value back in the X-Finopal-Webhook-Secret header (or Authorization:
    | Bearer). The same secret guards all three webhooks. Never commit a real
    | value.
    |
    */
    'webhook_secret' => env('FINOPAL_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Sandbox / API Base
    |--------------------------------------------------------------------------
    |
    | When true the module targets the finopal sandbox instead of production.
    | api_base is the finopal integration host used to build shared referral
    | links (and any future outbound calls). Switch to the production hosts
    | (finopal.ir / finonet.ir) for live deployments.
    |
    */
    'sandbox' => (bool) env('FINOPAL_SANDBOX', true),

    'api_base' => env('FINOPAL_API_BASE', 'https://sandbox.finopal.ir'),

    /*
    |--------------------------------------------------------------------------
    | Referral Link
    |--------------------------------------------------------------------------
    |
    | Base used to build each representative's shared referral link. The link
    | starts Finopal signup and carries our sales-org code as the configured
    | query param, which finopal preserves and echoes back in the signup
    | webhook's referral_code.
    |
    */
    'referral' => [
        'link_base' => env('FINOPAL_REFERRAL_LINK_BASE', 'https://finopal.ir/login'),
        'param' => 'finonet_ref',
        'code_length' => (int) env('FINOPAL_REFERRAL_CODE_LENGTH', 6),
        'root_code' => env('FINOPAL_REFERRAL_ROOT_CODE', 'ROOT01'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Welcome SMS
    |--------------------------------------------------------------------------
    |
    | Message queued when a new representative is created through the signup
    | webhook. {name} and {link} tokens are replaced at send time.
    |
    */
    'welcome_sms' => [
        'enabled' => (bool) env('FINOPAL_WELCOME_SMS_ENABLED', true),
        'template' => env(
            'FINOPAL_WELCOME_SMS_TEMPLATE',
            'کاربر گرامی {name}، خوش آمدید. لینک معرفی شما: {link}'
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Representative Role
    |--------------------------------------------------------------------------
    |
    | Optional role name the host may attach to representatives created through
    | the referral flow. Purely informational for the module; the host adapter
    | decides whether and how to apply it.
    |
    */
    'representative_role' => env('FINOPAL_REPRESENTATIVE_ROLE', 'Representative'),

    /*
    |--------------------------------------------------------------------------
    | Inbound Webhook Toggles
    |--------------------------------------------------------------------------
    |
    | The module ships the full finopal contract (signup, gateway, transaction),
    | but each inbound endpoint can be enabled/disabled per project. A PMS like
    | Finolo may disable gateway + transaction (it has no payment gateways),
    | while payment/sales projects keep all three. Disabled routes are not
    | registered, so a call to them returns 404.
    |
    */
    'webhooks' => [
        'signup' => (bool) env('FINOPAL_WEBHOOK_SIGNUP_ENABLED', true),
        'gateway' => (bool) env('FINOPAL_WEBHOOK_GATEWAY_ENABLED', true),
        'transaction' => (bool) env('FINOPAL_WEBHOOK_TRANSACTION_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Outbound Signup Forwarding (this host acts as a SENDER)
    |--------------------------------------------------------------------------
    |
    | Optional. When a user signs up on THIS host carrying a ?finonet_ref=<code>,
    | the host forwards a signup webhook to the sales organization (finonet.ir)
    | exactly like finopal does, so the referrer is credited there. This is the
    | mirror of the inbound signup webhook and uses the SAME contract
    | ({event, referral_code, user{name,mobile}} signed with
    | X-Finopal-Webhook-Secret). The OUTBOUND secret is deliberately separate
    | from the INBOUND FINOPAL_WEBHOOK_SECRET. Disabled by default; enable and
    | point signup_url at the finonet.ir (or sandbox) endpoint. See docs/SENDER.md.
    |
    */
    'forward' => [
        'enabled' => (bool) env('FINONET_FORWARD_SIGNUP_ENABLED', false),
        'signup_url' => env('FINONET_SIGNUP_WEBHOOK_URL', 'https://finonet.ir/api/webhooks/finopal/signup'),
        'secret' => env('FINONET_WEBHOOK_SECRET', ''),
        'timeout' => (int) env('FINONET_FORWARD_TIMEOUT', 15),
    ],
];
