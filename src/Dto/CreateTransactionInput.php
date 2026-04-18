<?php

declare(strict_types=1);

namespace App\Dto;

use App\State\Processor\CreateTransactionProcessor;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(operations: [new Post(uriTemplate: '/transactions', processor: CreateTransactionProcessor::class, output: TransactionOutput::class)])]
final class CreateTransactionInput
{
    #[Assert\NotBlank]
    #[Assert\Positive]
    #[SerializedName('member_id')]
    public int $memberId = 0;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/')]
    public string $amount = '0.00';
}
