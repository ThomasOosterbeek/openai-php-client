<?php

declare(strict_types=1);

namespace OpenAI\Contracts\Extensions;

/**
 * @see https://www.openresponses.org/specification
 */
interface ResponsesExtensionContract
{
    /**
     * The implementor slug that prefixes every vendor type, e.g. 'acme'.
     */
    public static function namespace(): string;

    /**
     * Maps vendor item types to their typed classes, e.g. ['acme:search_result' => AcmeSearchResult::class].
     *
     * @return array<string, class-string<ExtensionOutputItemContract>>
     */
    public static function outputItems(): array;

    /**
     * Maps vendor streaming event types to their typed classes, e.g. ['acme:trace_event' => AcmeTraceEvent::class].
     *
     * @return array<string, class-string<ExtensionStreamEventContract>>
     */
    public static function streamEvents(): array;
}
