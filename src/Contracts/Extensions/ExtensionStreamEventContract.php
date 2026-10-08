<?php

declare(strict_types=1);

namespace OpenAI\Contracts\Extensions;

interface ExtensionStreamEventContract
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function from(array $attributes): static;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
