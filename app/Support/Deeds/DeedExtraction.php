<?php

namespace App\Support\Deeds;

final class DeedExtraction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public array $data,
        public ?string $model = null,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
    ) {}
}
