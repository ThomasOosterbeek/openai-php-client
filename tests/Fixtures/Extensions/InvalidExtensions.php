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

final class WrongClassExtension implements ResponsesExtensionContract
{
    public static function namespace(): string
    {
        return 'acme';
    }

    public static function outputItems(): array
    {
        // @phpstan-ignore-next-line intentionally wrong for validation tests
        return ['acme:search_result' => \stdClass::class];
    }

    public static function streamEvents(): array
    {
        return [];
    }
}

final class DuplicateAcmeExtension implements ResponsesExtensionContract
{
    public static function namespace(): string
    {
        return 'acme';
    }

    public static function outputItems(): array
    {
        return ['acme:search_result' => AcmeSearchResult::class];
    }

    public static function streamEvents(): array
    {
        return [];
    }
}

final class EmptySuffixExtension implements ResponsesExtensionContract
{
    public static function namespace(): string
    {
        return 'acme';
    }

    public static function outputItems(): array
    {
        return ['acme:' => AcmeSearchResult::class];
    }

    public static function streamEvents(): array
    {
        return [];
    }
}
