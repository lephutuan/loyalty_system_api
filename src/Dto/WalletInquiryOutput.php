<?php

declare(strict_types=1);

namespace App\Dto;

use App\State\Provider\WalletInquiryProvider;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[ApiResource(operations: [new Get(uriTemplate: '/members/{member_id}/wallet', provider: WalletInquiryProvider::class)])]
final readonly class WalletInquiryOutput
{
    /**
     * @param array<int, PointHistoryItem> $recentPoints
     */
    public function __construct(
        #[SerializedName('member_id')]
        public int $memberId,
        public string $fullname,
        public string $email,
        public string $balance,
        #[SerializedName('recent_points')]
        public array $recentPoints,
    ) {
    }
}
