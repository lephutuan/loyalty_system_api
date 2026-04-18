<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Annotation\SerializedName;

final readonly class RedemptionOutput
{
    public function __construct(
        #[SerializedName('redemption_id')]
        public int $redemptionId,
        #[SerializedName('member_id')]
        public int $memberId,
        #[SerializedName('gift_id')]
        public int $giftId,
        #[SerializedName('points_used')]
        public string $pointsUsed,
        public string $status,
        #[SerializedName('created_at')]
        public string $createdAt,
    ) {
    }
}
