<?php

namespace Modules\Referral\Adapters;

use Modules\Referral\Contracts\ReferralOwner;
use Modules\User\Models\User;

/**
 * Finolo adapter: wraps the host User model behind the module's ReferralOwner
 * contract so the module core never depends on Modules\User.
 */
class UserReferralOwner implements ReferralOwner
{
    public function __construct(
        protected User $user
    ) {}

    public function key(): string
    {
        return (string) $this->user->getKey();
    }

    public function type(): string
    {
        return $this->user->getMorphClass();
    }

    public function name(): ?string
    {
        return $this->user->name;
    }

    public function mobile(): ?string
    {
        return $this->user->phone;
    }

    public function email(): ?string
    {
        return $this->user->email;
    }
}
