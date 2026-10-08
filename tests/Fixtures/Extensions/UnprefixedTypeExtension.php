<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions;

use OpenAI\Contracts\Extensions\ResponsesExtensionContract;

final class UnprefixedTypeExtension implements ResponsesExtensionContract
{
    public static function namespace(): string
    {
        return 'acme';
    }

    public static function outputItems(): array
    {
        return ['search_result' => AcmeSearchResult::class];
    }

    public static function streamEvents(): array
    {
        return [];
    }
}
