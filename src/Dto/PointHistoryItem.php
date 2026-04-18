<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Annotation\SerializedName;

final readonly class PointHistoryItem
{
    public function __construct(
        #[SerializedName('point_amount')]
        public string $pointAmount,
        public string $description,
        #[SerializedName('transaction_id')]
        public ?int $transactionId,
        #[SerializedName('redemption_id')]
        public ?int $redemptionId,
        #[SerializedName('created_at')]
        public string $createdAt,
    ) {
    }
}
