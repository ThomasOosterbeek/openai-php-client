<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions;

use OpenAI\Contracts\Extensions\ExtensionOutputItemContract;

final class AcmeSearchResult implements ExtensionOutputItemContract
{
    private function __construct(
        public readonly string $type,
        public readonly string $query,
        public readonly float $score,
    ) {}

    public static function from(array $attributes): static
    {
        return new self(
            type: $attributes['type'],
            query: $attributes['query'],
            score: $attributes['score'],
        );
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'query' => $this->query,
            'score' => $this->score,
        ];
    }
}
