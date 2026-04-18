<?php

declare(strict_types=1);

namespace App\Dto;

use App\State\Processor\CreateRedemptionProcessor;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(operations: [new Post(uriTemplate: '/redemptions', processor: CreateRedemptionProcessor::class, output: RedemptionOutput::class)])]
final class CreateRedemptionInput
{
    #[Assert\NotBlank]
    #[Assert\Positive]
    #[SerializedName('member_id')]
    public int $memberId = 0;

    #[Assert\NotBlank]
    #[Assert\Positive]
    #[SerializedName('gift_id')]
    public int $giftId = 0;
}
