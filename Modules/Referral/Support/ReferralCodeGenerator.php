<?php

namespace Modules\Referral\Support;

use Modules\Referral\Repositories\ReferralAccountRepositoryInterface;

class ReferralCodeGenerator
{
    /**
     * Ambiguous glyphs (0/O, 1/I/L) are excluded so codes survive being copied
     * or read aloud.
     */
    protected const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public function __construct(
        protected ReferralAccountRepositoryInterface $accounts
    ) {}

    /**
     * Generate a unique referral code that is not already taken.
     */
    public function generate(?int $length = null): string
    {
        $length = $length ?? (int) config('referral.referral.code_length', 6);
        $max = strlen(self::ALPHABET) - 1;

        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= self::ALPHABET[random_int(0, $max)];
            }
        } while ($this->accounts->referralCodeExists($code));

        return $code;
    }

    /**
     * Build the shared referral link for a code: {base}?{param}={code}.
     */
    public function buildLink(string $code): string
    {
        $base = (string) config('referral.referral.link_base', 'https://finopal.ir/login');
        $param = (string) config('referral.referral.param', 'finonet_ref');

        return $base.'?'.$param.'='.$code;
    }
}
