<?php

declare(strict_types=1);

namespace OpenAI\Contracts\Extensions;

/**
 * A typed vendor item appearing in Responses output or input item lists.
 */
interface ExtensionOutputItemContract
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
