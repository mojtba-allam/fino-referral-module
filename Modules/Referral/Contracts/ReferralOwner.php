<?php

namespace Modules\Referral\Contracts;

/**
 * A normalized view of the host project's user/subject, so the module never
 * depends on a concrete User model. Each project ships an adapter that wraps
 * its own model behind this interface.
 */
interface ReferralOwner
{
    /**
     * Stable primary key of the host user (string form).
     */
    public function key(): string;

    /**
     * Class/name used as the polymorphic owner_type.
     */
    public function type(): string;

    public function name(): ?string;

    public function mobile(): ?string;

    public function email(): ?string;
}
