<?php

declare(strict_types=1);

namespace OpenAI\ValueObjects;

use OpenAI\Contracts\Extensions\ExtensionOutputItemContract;
use OpenAI\Contracts\Extensions\ExtensionStreamEventContract;
use OpenAI\Contracts\Extensions\ResponsesExtensionContract;
use OpenAI\Exceptions\InvalidResponsesExtension;

/**
 * @internal
 */
final class ResponsesExtensionRegistry
{
    /**
     * @param  array<string, class-string<ExtensionOutputItemContract>>  $outputItems
     * @param  array<string, class-string<ExtensionStreamEventContract>>  $streamEvents
     */
    private function __construct(
        private readonly array $outputItems,
        private readonly array $streamEvents,
    ) {}

    /**
     * @param  array<int, class-string<ResponsesExtensionContract>>  $extensions
     */
    public static function from(array $extensions): self
    {
        $outputItems = [];
        $streamEvents = [];

        foreach ($extensions as $extension) {
            if (! is_a($extension, ResponsesExtensionContract::class, true)) {
                throw new InvalidResponsesExtension(
                    sprintf('The extension [%s] must implement [%s].', $extension, ResponsesExtensionContract::class),
                );
            }

            $namespace = $extension::namespace();

            if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $namespace) !== 1) {
                throw new InvalidResponsesExtension(
                    sprintf('The extension [%s] has an invalid namespace slug [%s].', $extension, $namespace),
                );
            }

            foreach ($extension::outputItems() as $type => $class) {
                self::guardType($extension, $namespace, $type);
                self::guardClass($class, $type, ExtensionOutputItemContract::class);
                self::guardUnique($outputItems, $type);

                $outputItems[$type] = $class;
            }

            foreach ($extension::streamEvents() as $type => $class) {
                self::guardType($extension, $namespace, $type);
                self::guardClass($class, $type, ExtensionStreamEventContract::class);
                self::guardUnique($streamEvents, $type);

                $streamEvents[$type] = $class;
            }
        }

        return new self($outputItems, $streamEvents);
    }

    /**
     * @return class-string<ExtensionOutputItemContract>|null
     */
    public function outputItem(string $type): ?string
    {
        return $this->outputItems[$type] ?? null;
    }

    /**
     * @return class-string<ExtensionStreamEventContract>|null
     */
    public function streamEvent(string $type): ?string
    {
        return $this->streamEvents[$type] ?? null;
    }

    /**
     * @param  class-string<ResponsesExtensionContract>  $extension
     */
    private static function guardType(string $extension, string $namespace, string $type): void
    {
        if (! str_starts_with($type, $namespace.':') || strlen($type) <= strlen($namespace) + 1) {
            throw new InvalidResponsesExtension(
                sprintf('The extension [%s] registers the type [%s] outside its [%s:] namespace.', $extension, $type, $namespace),
            );
        }
    }

    private static function guardClass(string $class, string $type, string $contract): void
    {
        if (! is_a($class, $contract, true)) {
            throw new InvalidResponsesExtension(
                sprintf('The class [%s] registered for the type [%s] must implement [%s].', $class, $type, $contract),
            );
        }
    }

    /**
     * @param  array<string, string>  $registered
     */
    private static function guardUnique(array $registered, string $type): void
    {
        if (isset($registered[$type])) {
            throw new InvalidResponsesExtension(
                sprintf('The type [%s] is registered by more than one extension.', $type),
            );
        }
    }
}
