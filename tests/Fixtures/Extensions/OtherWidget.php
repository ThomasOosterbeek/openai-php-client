<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions;

use OpenAI\Contracts\Extensions\ExtensionOutputItemContract;

final class OtherWidget implements ExtensionOutputItemContract
{
    private function __construct(
        public readonly string $type,
        public readonly string $label,
    ) {}

    public static function from(array $attributes): static
    {
        return new self(
            type: $attributes['type'],
            label: $attributes['label'],
        );
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'label' => $this->label,
        ];
    }
}
