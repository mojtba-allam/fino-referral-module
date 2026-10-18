<?php

namespace Tests\Unit\Modules\Referral;

use Mockery;
use Modules\Referral\Repositories\ReferralAccountRepositoryInterface;
use Modules\Referral\Support\ReferralCodeGenerator;
use Tests\TestCase;

class ReferralCodeGeneratorTest extends TestCase
{
    protected function shouldSeed(): bool
    {
        return false;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_generate_returns_unambiguous_unique_code(): void
    {
        config(['referral.referral.code_length' => 6]);

        $repo = Mockery::mock(ReferralAccountRepositoryInterface::class);
        $repo->shouldReceive('referralCodeExists')->andReturn(false);

        $generator = new ReferralCodeGenerator($repo);
        $code = $generator->generate();

        $this->assertSame(6, strlen($code));
        $this->assertMatchesRegularExpression('/^[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{6}$/', $code);
    }

    public function test_build_link_uses_configured_base_and_param(): void
    {
        $repo = Mockery::mock(ReferralAccountRepositoryInterface::class);
        config()->set('referral.referral.link_base', 'https://finopal.ir/login');
        $generator = new ReferralCodeGenerator($repo);

        $link = $generator->buildLink('AB12CD');

        $this->assertSame('https://finopal.ir/login?finonet_ref=AB12CD', $link);
    }
}
