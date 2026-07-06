<?php

declare(strict_types=1);

namespace OpenAI\Actions\Responses;

use OpenAI\Contracts\Extensions\ExtensionOutputItemContract;
use OpenAI\ValueObjects\ResponsesExtensionRegistry;
use UnexpectedValueException;

final class ExtensionItems
{
    /**
     * @param  array<string, mixed>  $item
     */
    public static function resolve(array $item, ?ResponsesExtensionRegistry $registry): ExtensionOutputItemContract
    {
        $type = $item['type'] ?? null;

        $class = is_string($type) ? $registry?->outputItem($type) : null;

        if ($class === null) {
            throw new UnexpectedValueException('Uh oh! We do not recognize this type. Please submit a bug to openai-php/client on GitHub!');
        }

        return $class::from($item);
    }
}
