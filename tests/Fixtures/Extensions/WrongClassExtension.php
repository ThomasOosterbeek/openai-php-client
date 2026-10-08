<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions;

use OpenAI\Contracts\Extensions\ResponsesExtensionContract;

final class WrongClassExtension implements ResponsesExtensionContract
{
    public static function namespace(): string
    {
        return 'acme';
    }

    public static function outputItems(): array
    {
        // @phpstan-ignore-next-line
        return ['acme:search_result' => \stdClass::class];
    }

    public static function streamEvents(): array
    {
        return [];
    }
}
