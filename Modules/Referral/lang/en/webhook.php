<?php

return [
    'signup' => [
        'success' => 'User registered from Finopal in the sales organization.',
        'duplicate' => 'This user is already registered.',
        'missing_referral' => 'The referral code is required.',
        'invalid_referral' => 'The referral code is not valid for a new user.',
    ],
    'gateway' => [
        'success' => 'Gateway registered from Finopal in the sales organization.',
        'duplicate' => 'This gateway is already registered.',
        'user_not_found' => 'Send the user signup webhook first.',
        'idempotency_conflict' => 'This idempotency key is already used by another gateway. Send a fresh key.',
    ],
    'transaction' => [
        'success' => 'Transaction recorded and commission computed.',
        'duplicate' => 'This transaction is already recorded.',
        'missing_merchant' => 'The merchant id is required.',
        'unknown_merchant' => 'Gateway not found. Send the gateway registration webhook first.',
    ],
];
