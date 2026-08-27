<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions;

use OpenAI\Contracts\Extensions\ExtensionStreamEventContract;

final class AcmeTraceEvent implements ExtensionStreamEventContract
{
    private function __construct(
        public readonly string $type,
        public readonly string $traceId,
        public readonly int $spans,
    ) {}

    public static function from(array $attributes): static
    {
        return new self(
            type: $attributes['type'],
            traceId: $attributes['trace_id'],
            spans: $attributes['spans'],
        );
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'trace_id' => $this->traceId,
            'spans' => $this->spans,
        ];
    }
}
