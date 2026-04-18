<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Annotation\SerializedName;

final readonly class TransactionOutput
{
    public function __construct(
        #[SerializedName('transaction_id')]
        public int $transactionId,
        #[SerializedName('member_id')]
        public int $memberId,
        public string $amount,
        public string $status,
        #[SerializedName('points_earned')]
        public string $pointsEarned,
        #[SerializedName('created_at')]
        public string $createdAt,
    ) {
    }
}
