<?php

declare(strict_types=1);

namespace OpenAI\ValueObjects;

use Closure;
use OpenAI\Contracts\Extensions\ExtensionOutputItemContract;
use OpenAI\Contracts\Extensions\ExtensionStreamEventContract;
use OpenAI\Contracts\Extensions\ResponsesExtensionContract;
use OpenAI\Exceptions\InvalidResponsesExtension;

/**
 * @internal
 */
final class ResponsesExtensionRegistry
{
    private static ?self $current = null;

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

            self::register($outputItems, $extension, $namespace, $extension::outputItems(), ExtensionOutputItemContract::class);
            self::register($streamEvents, $extension, $namespace, $extension::streamEvents(), ExtensionStreamEventContract::class);
        }

        return new self($outputItems, $streamEvents);
    }

    /**
     * Runs the callback with the given registry as the current one, restoring the previous one afterwards.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function scoped(?self $registry, Closure $callback): mixed
    {
        $previous = self::$current;
        self::$current = $registry;

        try {
            return $callback();
        } finally {
            self::$current = $previous;
        }
    }

    public static function current(): ?self
    {
        return self::$current;
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
     * @template TContract of object
     *
     * @param  array<string, class-string<TContract>>  $registered
     * @param  class-string<ResponsesExtensionContract>  $extension
     * @param  array<string, class-string<TContract>>  $types
     * @param  class-string<TContract>  $contract
     */
    private static function register(array &$registered, string $extension, string $namespace, array $types, string $contract): void
    {
        foreach ($types as $type => $class) {
            self::guardType($extension, $namespace, $type);
            self::guardClass($class, $type, $contract);
            self::guardUnique($registered, $type);

            $registered[$type] = $class;
        }
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
