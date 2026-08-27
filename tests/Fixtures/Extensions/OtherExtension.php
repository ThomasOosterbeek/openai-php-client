<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions;

use OpenAI\Contracts\Extensions\ResponsesExtensionContract;

final class OtherExtension implements ResponsesExtensionContract
{
    public static function namespace(): string
    {
        return 'other';
    }

    public static function outputItems(): array
    {
        return [
            'other:widget' => OtherWidget::class,
        ];
    }

    public static function streamEvents(): array
    {
        return [];
    }
}
