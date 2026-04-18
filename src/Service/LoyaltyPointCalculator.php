<?php

declare(strict_types=1);

namespace App\Service;

final class LoyaltyPointCalculator
{
    public function calculateEarnedPoints(string $amount): string
    {
        return number_format(((float) $amount) * 0.01, 2, '.', '');
    }
}
