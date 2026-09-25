<?php

namespace App\Support\Properties;

final class CommercialTransactionStatus
{
    public const BUY = 'BUY';
    public const LEASE = 'LEASE';
    public const SOLD = 'SOLD';
    public const LEASED = 'LEASED';

    public const VALUES = [
        self::BUY,
        self::LEASE,
        self::SOLD,
        self::LEASED,
    ];
}
