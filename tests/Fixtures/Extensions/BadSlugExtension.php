<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions;

use OpenAI\Contracts\Extensions\ResponsesExtensionContract;

final class BadSlugExtension implements ResponsesExtensionContract
{
    public static function namespace(): string
    {
        return 'Acme Vendor!';
    }

    public static function outputItems(): array
    {
        return [];
    }

    public static function streamEvents(): array
    {
        return [];
    }
}
