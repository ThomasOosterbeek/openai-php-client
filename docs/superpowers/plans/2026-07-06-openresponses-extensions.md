# OpenResponses Extensions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let library users register typed OpenResponses vendor extensions (item types `acme:search_result`, stream events `acme:trace_event`) on a per-client basis, hydrated into the author's own typed classes across all Responses flows.

**Architecture:** An immutable `ResponsesExtensionRegistry` (validated at construction) is built in `Factory::make()` and threaded as an *optional* parameter through `Client` → `Resources\Responses` → DTO `from()` methods → the four type-routing points (`OutputObjects::parse`, `ItemObjects::parse`, `Streaming\OutputItem::from`, `CreateStreamedResponse::from`). Unregistered vendor types keep throwing exactly as today.

**Tech Stack:** PHP 8.2+, Pest (unit + arch + type-coverage), PHPStan, Mockery, Laravel Pint.

**Spec:** `docs/superpowers/specs/2026-07-06-openresponses-extensions-design.md`

## Global Constraints

- No new composer dependencies.
- All new constructor/method parameters MUST be optional with `null` defaults — zero breaking changes.
- Unregistered vendor item types throw `\UnexpectedValueException` (message: `'Uh oh! We do not recognize this type. Please submit a bug to openai-php/client on GitHub!'`); unregistered vendor events throw `OpenAI\Exceptions\UnknownEventException` (message: `'Unknown Responses streaming event: '.$event`).
- Every commit must keep `composer test` green: `composer test:lint`, `composer test:types` (PHPStan), `composer test:type-coverage` (pest `--min=100`), `composer test:unit`.
- Run a single test file with: `vendor/bin/pest tests/path/File.php` (Git Bash) — add `--filter "test name"` to narrow.
- Code style: `declare(strict_types=1);`, `final` classes, promoted readonly constructor properties, named arguments in `from()` calls — match surrounding files.
- Tests use Pest `test('name', function () { ... })` style, no classes.
- `tests/Arch.php` allow-lists are edited ONLY with the exact entries specified in tasks 2, 4, and 6.

---

### Task 1: Extension contracts + exception

**Files:**
- Create: `src/Contracts/Extensions/ResponsesExtensionContract.php`
- Create: `src/Contracts/Extensions/ExtensionOutputItemContract.php`
- Create: `src/Contracts/Extensions/ExtensionStreamEventContract.php`
- Create: `src/Exceptions/InvalidResponsesExtension.php`

**Interfaces:**
- Consumes: nothing.
- Produces: the three interfaces below (exact FQCNs and signatures) and `OpenAI\Exceptions\InvalidResponsesExtension extends \InvalidArgumentException`. Task 2's registry validates against them; tasks 3–5 type against the item/event contracts.

- [ ] **Step 1: Create the three contract interfaces**

`src/Contracts/Extensions/ResponsesExtensionContract.php`:

```php
<?php

declare(strict_types=1);

namespace OpenAI\Contracts\Extensions;

/**
 * Describes an OpenResponses vendor extension.
 *
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
```

`src/Contracts/Extensions/ExtensionOutputItemContract.php`:

```php
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
```

`src/Contracts/Extensions/ExtensionStreamEventContract.php`:

```php
<?php

declare(strict_types=1);

namespace OpenAI\Contracts\Extensions;

/**
 * A typed vendor event emitted while streaming a Response.
 */
interface ExtensionStreamEventContract
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
```

- [ ] **Step 2: Create the exception**

`src/Exceptions/InvalidResponsesExtension.php`:

```php
<?php

declare(strict_types=1);

namespace OpenAI\Exceptions;

use InvalidArgumentException;

final class InvalidResponsesExtension extends InvalidArgumentException {}
```

(`InvalidArgumentException` here is PHP's SPL class — the codebase's own `OpenAI\Exceptions\InvalidArgumentException` is `final` and cannot be extended.)

- [ ] **Step 3: Verify the suite stays green**

Run: `composer test:types && vendor/bin/pest tests/Arch.php`
Expected: PHPStan passes; arch tests pass (contracts are interfaces with no dependencies; the exception implements `Throwable` and uses no forbidden namespaces).

- [ ] **Step 4: Commit**

```bash
git add src/Contracts/Extensions src/Exceptions/InvalidResponsesExtension.php
git commit -m "feat(Responses): add OpenResponses extension contracts"
```

---

### Task 2: ResponsesExtensionRegistry (validated value object)

**Files:**
- Create: `src/ValueObjects/ResponsesExtensionRegistry.php`
- Create: `tests/Fixtures/Extensions/AcmeExtension.php`
- Create: `tests/Fixtures/Extensions/AcmeSearchResult.php`
- Create: `tests/Fixtures/Extensions/AcmeTraceEvent.php`
- Create: `tests/Fixtures/Extensions/InvalidExtensions.php`
- Modify: `tests/Arch.php` (value objects allow-list)
- Test: `tests/ValueObjects/ResponsesExtensionRegistry.php`

**Interfaces:**
- Consumes: the three contracts and `InvalidResponsesExtension` from Task 1.
- Produces: `OpenAI\ValueObjects\ResponsesExtensionRegistry` with `public static function from(array $extensions): self` (param: `array<int, class-string<ResponsesExtensionContract>>`), `public function outputItem(string $type): ?string` (returns `class-string<ExtensionOutputItemContract>|null`), `public function streamEvent(string $type): ?string` (returns `class-string<ExtensionStreamEventContract>|null`). Also the reusable test fixtures `Tests\Fixtures\Extensions\AcmeExtension` (namespace `acme`, item `acme:search_result` → `AcmeSearchResult` with `type`/`query`/`score` properties, event `acme:trace_event` → `AcmeTraceEvent` with `type`/`traceId`/`spans` properties) used by every later task.

- [ ] **Step 1: Create the valid fixture extension**

`tests/Fixtures/Extensions/AcmeExtension.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions;

use OpenAI\Contracts\Extensions\ResponsesExtensionContract;

final class AcmeExtension implements ResponsesExtensionContract
{
    public static function namespace(): string
    {
        return 'acme';
    }

    public static function outputItems(): array
    {
        return [
            'acme:search_result' => AcmeSearchResult::class,
        ];
    }

    public static function streamEvents(): array
    {
        return [
            'acme:trace_event' => AcmeTraceEvent::class,
        ];
    }
}
```

`tests/Fixtures/Extensions/AcmeSearchResult.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions;

use OpenAI\Contracts\Extensions\ExtensionOutputItemContract;

final class AcmeSearchResult implements ExtensionOutputItemContract
{
    private function __construct(
        public readonly string $type,
        public readonly string $query,
        public readonly float $score,
    ) {}

    public static function from(array $attributes): static
    {
        return new self(
            type: $attributes['type'],
            query: $attributes['query'],
            score: $attributes['score'],
        );
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'query' => $this->query,
            'score' => $this->score,
        ];
    }
}
```

`tests/Fixtures/Extensions/AcmeTraceEvent.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions;

use OpenAI\Contracts\Extensions\ExtensionStreamEventContract;

final class AcmeTraceEvent implements ExtensionStreamEventContract
{
    private function __construct(
        public readonly string $type,
        public readonly string $traceId,
        public readonly int $spans,
    ) {}

    public static function from(array $attributes): static
    {
        return new self(
            type: $attributes['type'],
            traceId: $attributes['trace_id'],
            spans: $attributes['spans'],
        );
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'trace_id' => $this->traceId,
            'spans' => $this->spans,
        ];
    }
}
```

- [ ] **Step 2: Create the invalid fixtures (one file, several small classes)**

`tests/Fixtures/Extensions/InvalidExtensions.php`:

```php
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
```

- [ ] **Step 3: Write the failing tests**

`tests/ValueObjects/ResponsesExtensionRegistry.php`:

```php
<?php

use OpenAI\Exceptions\InvalidResponsesExtension;
use OpenAI\ValueObjects\ResponsesExtensionRegistry;
use Tests\Fixtures\Extensions\AcmeExtension;
use Tests\Fixtures\Extensions\AcmeSearchResult;
use Tests\Fixtures\Extensions\AcmeTraceEvent;
use Tests\Fixtures\Extensions\BadSlugExtension;
use Tests\Fixtures\Extensions\DuplicateAcmeExtension;
use Tests\Fixtures\Extensions\EmptySuffixExtension;
use Tests\Fixtures\Extensions\UnprefixedTypeExtension;
use Tests\Fixtures\Extensions\WrongClassExtension;

test('resolves registered output items and stream events', function () {
    $registry = ResponsesExtensionRegistry::from([AcmeExtension::class]);

    expect($registry->outputItem('acme:search_result'))->toBe(AcmeSearchResult::class)
        ->and($registry->streamEvent('acme:trace_event'))->toBe(AcmeTraceEvent::class);
});

test('returns null for unregistered types', function () {
    $registry = ResponsesExtensionRegistry::from([AcmeExtension::class]);

    expect($registry->outputItem('other:thing'))->toBeNull()
        ->and($registry->streamEvent('acme:search_result'))->toBeNull()
        ->and($registry->outputItem('acme:trace_event'))->toBeNull();
});

test('an empty registry resolves nothing', function () {
    $registry = ResponsesExtensionRegistry::from([]);

    expect($registry->outputItem('acme:search_result'))->toBeNull()
        ->and($registry->streamEvent('acme:trace_event'))->toBeNull();
});

test('rejects classes not implementing the extension contract', function () {
    ResponsesExtensionRegistry::from([\stdClass::class]); // @phpstan-ignore-line
})->throws(InvalidResponsesExtension::class, 'must implement');

test('rejects invalid namespace slugs', function () {
    ResponsesExtensionRegistry::from([BadSlugExtension::class]);
})->throws(InvalidResponsesExtension::class, 'invalid namespace slug');

test('rejects types outside the extension namespace', function () {
    ResponsesExtensionRegistry::from([UnprefixedTypeExtension::class]);
})->throws(InvalidResponsesExtension::class, 'outside its');

test('rejects types with an empty suffix', function () {
    ResponsesExtensionRegistry::from([EmptySuffixExtension::class]);
})->throws(InvalidResponsesExtension::class, 'outside its');

test('rejects item classes not implementing the item contract', function () {
    ResponsesExtensionRegistry::from([WrongClassExtension::class]);
})->throws(InvalidResponsesExtension::class, 'must implement');

test('rejects the same type registered by two extensions', function () {
    ResponsesExtensionRegistry::from([AcmeExtension::class, DuplicateAcmeExtension::class]);
})->throws(InvalidResponsesExtension::class, 'more than one extension');
```

- [ ] **Step 4: Run tests to verify they fail**

Run: `vendor/bin/pest tests/ValueObjects/ResponsesExtensionRegistry.php`
Expected: FAIL — `Class "OpenAI\ValueObjects\ResponsesExtensionRegistry" not found`.

- [ ] **Step 5: Implement the registry**

`src/ValueObjects/ResponsesExtensionRegistry.php`:

```php
<?php

declare(strict_types=1);

namespace OpenAI\ValueObjects;

use OpenAI\Contracts\Extensions\ExtensionOutputItemContract;
use OpenAI\Contracts\Extensions\ExtensionStreamEventContract;
use OpenAI\Contracts\Extensions\ResponsesExtensionContract;
use OpenAI\Exceptions\InvalidResponsesExtension;

/**
 * Immutable map of OpenResponses vendor types to their typed classes.
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
```

- [ ] **Step 6: Allow the exception in the value-objects arch rule**

In `tests/Arch.php`, the `test('value objects')` allow-list, add one entry:

```php
test('value objects')->expect('OpenAI\ValueObjects')->toOnlyUse([
    'Http\Discovery\Psr17Factory',
    'Http\Message\MultipartStream\MultipartStreamBuilder',
    'Psr\Http\Message\RequestInterface',
    'Psr\Http\Message\StreamInterface',
    'OpenAI\Enums',
    'OpenAI\Contracts',
    'OpenAI\Exceptions\InvalidResponsesExtension',
    'OpenAI\Responses\Meta\MetaInformation',
]);
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `vendor/bin/pest tests/ValueObjects/ResponsesExtensionRegistry.php tests/Arch.php && composer test:types`
Expected: all PASS.

- [ ] **Step 8: Commit**

```bash
git add src/ValueObjects/ResponsesExtensionRegistry.php tests/Fixtures/Extensions tests/ValueObjects/ResponsesExtensionRegistry.php tests/Arch.php
git commit -m "feat(Responses): add validated extension registry value object"
```

---

### Task 3: Vendor item routing in OutputObjects and ItemObjects

**Files:**
- Create: `src/Actions/Responses/ExtensionItems.php`
- Modify: `src/Actions/Responses/OutputObjects.php`
- Modify: `src/Actions/Responses/ItemObjects.php`
- Test: `tests/Responses/Responses/Extensions.php` (new file, first half)

**Interfaces:**
- Consumes: `ResponsesExtensionRegistry` (Task 2), `ExtensionOutputItemContract` (Task 1).
- Produces: `OutputObjects::parse(array $outputItems, ?ResponsesExtensionRegistry $registry = null): array` and `ItemObjects::parse(array $outputItems, ?ResponsesExtensionRegistry $registry = null): array`, both routing unknown types through `ExtensionItems::resolve(array $item, ?ResponsesExtensionRegistry $registry): ExtensionOutputItemContract`. The phpstan types `ResponseOutputObjectReturnType` and `ResponseItemObjectReturnType` now include `ExtensionOutputItemContract` — Task 4 relies on that.

- [ ] **Step 1: Write the failing tests**

`tests/Responses/Responses/Extensions.php`:

```php
<?php

use OpenAI\Actions\Responses\ItemObjects;
use OpenAI\Actions\Responses\OutputObjects;
use OpenAI\ValueObjects\ResponsesExtensionRegistry;
use Tests\Fixtures\Extensions\AcmeExtension;
use Tests\Fixtures\Extensions\AcmeSearchResult;

function acmeRegistry(): ResponsesExtensionRegistry
{
    return ResponsesExtensionRegistry::from([AcmeExtension::class]);
}

function acmeSearchResultItem(): array
{
    return [
        'type' => 'acme:search_result',
        'query' => 'openresponses',
        'score' => 0.98,
    ];
}

test('OutputObjects routes registered vendor items to the extension class', function () {
    $output = OutputObjects::parse([acmeSearchResultItem()], acmeRegistry());

    expect($output[0])->toBeInstanceOf(AcmeSearchResult::class)
        ->type->toBe('acme:search_result')
        ->query->toBe('openresponses')
        ->score->toBe(0.98);
});

test('OutputObjects throws on vendor items without a registered extension', function () {
    OutputObjects::parse([['type' => 'other:thing']], acmeRegistry());
})->throws(UnexpectedValueException::class);

test('OutputObjects throws on vendor items when no registry is given', function () {
    OutputObjects::parse([acmeSearchResultItem()]);
})->throws(UnexpectedValueException::class);

test('OutputObjects still parses standard items alongside vendor items', function () {
    $output = OutputObjects::parse([
        [
            'type' => 'reasoning',
            'id' => 'rs_1',
            'status' => null,
            'summary' => [],
        ],
        acmeSearchResultItem(),
    ], acmeRegistry());

    expect($output[0])->toBeInstanceOf(OpenAI\Responses\Responses\Output\OutputReasoning::class)
        ->and($output[1])->toBeInstanceOf(AcmeSearchResult::class);
});

test('ItemObjects routes registered vendor items to the extension class', function () {
    $items = ItemObjects::parse([acmeSearchResultItem()], acmeRegistry());

    expect($items[0])->toBeInstanceOf(AcmeSearchResult::class)
        ->query->toBe('openresponses');
});

test('ItemObjects throws on vendor items without a registered extension', function () {
    ItemObjects::parse([['type' => 'other:thing']], acmeRegistry());
})->throws(UnexpectedValueException::class);

test('ItemObjects throws on unknown items when no registry is given', function () {
    ItemObjects::parse([acmeSearchResultItem()]);
})->throws(UnexpectedValueException::class);
```

Note: if the `reasoning` fixture shape above does not satisfy `OutputReasoning::from` (check `src/Responses/Responses/Output/OutputReasoning.php` for required keys), copy a valid reasoning item from `createResponseResource()` in `tests/Fixtures/Responses.php` instead.

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Responses/Responses/Extensions.php`
Expected: FAIL — parse() does not accept a second argument / vendor types throw.

- [ ] **Step 3: Create the shared resolver**

`src/Actions/Responses/ExtensionItems.php`:

```php
<?php

declare(strict_types=1);

namespace OpenAI\Actions\Responses;

use OpenAI\Contracts\Extensions\ExtensionOutputItemContract;
use OpenAI\ValueObjects\ResponsesExtensionRegistry;
use UnexpectedValueException;

final class ExtensionItems
{
    /**
     * Resolves a vendor-typed item through the extension registry.
     *
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
```

- [ ] **Step 4: Route through the registry in OutputObjects**

In `src/Actions/Responses/OutputObjects.php`:

1. Add imports:

```php
use OpenAI\Contracts\Extensions\ExtensionOutputItemContract;
use OpenAI\ValueObjects\ResponsesExtensionRegistry;
```

2. Append `|ExtensionOutputItemContract` to the `ResponseOutputObjectReturnType` phpstan-type (the `@phpstan-type` line ending in `|OutputCompaction>` becomes `...|OutputCompaction|ExtensionOutputItemContract>`).

3. Change the method:

```php
    /**
     * @param  ResponseOutputObjectTypes  $outputItems
     * @return ResponseOutputObjectReturnType
     */
    public static function parse(array $outputItems, ?ResponsesExtensionRegistry $registry = null): array
    {
        return array_map(
            fn (array $item): OutputMessage|OutputComputerToolCall|OutputFileSearchToolCall|OutputWebSearchToolCall|OutputFunctionToolCall|OutputReasoning|OutputMcpListTools|OutputMcpApprovalRequest|OutputMcpCall|OutputImageGenerationToolCall|OutputCodeInterpreterToolCall|OutputLocalShellCall|OutputCustomToolCall|OutputToolSearchCall|OutputToolSearchOutput|OutputCompaction|ExtensionOutputItemContract => match ($item['type']) {
                // ... all existing arms unchanged ...
                default => ExtensionItems::resolve($item, $registry),
            },
            $outputItems,
        );
    }
```

(The existing `default => throw new \UnexpectedValueException(...)` arm is REPLACED by the `ExtensionItems::resolve` arm — the resolver throws the identical exception when nothing is registered.)

- [ ] **Step 5: Route through the registry in ItemObjects**

In `src/Actions/Responses/ItemObjects.php`, same three changes: add the two imports, append `|ExtensionOutputItemContract` to `ResponseItemObjectReturnType`, add `?ResponsesExtensionRegistry $registry = null` parameter, append `|ExtensionOutputItemContract` to the closure's native return union, and add a default arm (this match previously had NO default — unknown types used to raise `UnhandledMatchError`):

```php
                default => ExtensionItems::resolve($item, $registry),
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Responses/Responses/Extensions.php && composer test:types && composer test:unit`
Expected: all PASS (full unit run guards against regressions in existing parse behavior).

- [ ] **Step 7: Commit**

```bash
git add src/Actions/Responses tests/Responses/Responses/Extensions.php
git commit -m "feat(Responses): route vendor item types through the extension registry"
```

---

### Task 4: Registry threading into non-streamed DTOs

**Files:**
- Modify: `src/Responses/Responses/CreateResponse.php`
- Modify: `src/Responses/Responses/RetrieveResponse.php`
- Modify: `src/Responses/Responses/ListInputItems.php`
- Modify: `src/Responses/Responses/Streaming/Response.php`
- Modify: `tests/Arch.php` (responses allow-list)
- Test: `tests/Responses/Responses/Extensions.php` (append)

**Interfaces:**
- Consumes: `OutputObjects::parse($items, ?$registry)` / `ItemObjects::parse($items, ?$registry)` from Task 3; `ResponsesExtensionRegistry` from Task 2.
- Produces: `CreateResponse::from(array $attributes, MetaInformation $meta, ?ResponsesExtensionRegistry $registry = null): self` — same added third parameter on `RetrieveResponse::from` and `ListInputItems::from`, and the same added THIRD parameter on `Streaming\Response::from(array $attributes, MetaInformation $meta, ?ResponsesExtensionRegistry $registry = null)`. Tasks 5–6 call these.

- [ ] **Step 1: Write the failing tests (append to `tests/Responses/Responses/Extensions.php`)**

```php
use OpenAI\Responses\Responses\CreateResponse;
use OpenAI\Responses\Responses\ListInputItems;
use OpenAI\Responses\Responses\RetrieveResponse;

test('CreateResponse hydrates vendor items via the registry', function () {
    $attributes = createResponseResource();
    $attributes['output'][] = acmeSearchResultItem();

    $response = CreateResponse::from($attributes, meta(), acmeRegistry());

    $vendorItem = $response->output[count($response->output) - 1];

    expect($vendorItem)->toBeInstanceOf(AcmeSearchResult::class)
        ->query->toBe('openresponses');

    expect($response->toArray()['output'])->toContain(acmeSearchResultItem());
});

test('CreateResponse without a registry keeps throwing on vendor items', function () {
    $attributes = createResponseResource();
    $attributes['output'][] = acmeSearchResultItem();

    CreateResponse::from($attributes, meta());
})->throws(UnexpectedValueException::class);

test('RetrieveResponse hydrates vendor items via the registry', function () {
    $attributes = retrieveResponseResource();
    $attributes['output'][] = acmeSearchResultItem();

    $response = RetrieveResponse::from($attributes, meta(), acmeRegistry());

    $vendorItem = $response->output[count($response->output) - 1];

    expect($vendorItem)->toBeInstanceOf(AcmeSearchResult::class);
});

test('ListInputItems hydrates vendor items via the registry', function () {
    $attributes = listInputItemsResource();
    $attributes['data'][] = acmeSearchResultItem();

    $response = ListInputItems::from($attributes, meta(), acmeRegistry());

    $vendorItem = $response->data[count($response->data) - 1];

    expect($vendorItem)->toBeInstanceOf(AcmeSearchResult::class)
        ->and($response->toArray()['data'])->toContain(acmeSearchResultItem());
});
```

(`meta()` is the existing helper from `tests/Fixtures/Meta.php` returning a `MetaInformation`; `createResponseResource()`, `retrieveResponseResource()`, and `listInputItemsResource()` come from `tests/Fixtures/Responses.php` — all autoloaded.)

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Responses/Responses/Extensions.php`
Expected: FAIL — `from()` does not accept a third argument.

- [ ] **Step 3: Thread the registry through the DTOs**

`src/Responses/Responses/CreateResponse.php`:

1. Add imports:

```php
use OpenAI\Contracts\Extensions\ExtensionOutputItemContract;
use OpenAI\ValueObjects\ResponsesExtensionRegistry;
```

2. `from()` signature and parse call:

```php
    /**
     * @param  CreateResponseType  $attributes
     */
    public static function from(array $attributes, MetaInformation $meta, ?ResponsesExtensionRegistry $registry = null): self
    {
        $output = OutputObjects::parse($attributes['output'], $registry);
```

(everything else in the body unchanged)

3. In `toArray()`, append `|ExtensionOutputItemContract` to the native union in the `array_map` closure over `$this->output` (the one currently ending `...|OutputCompaction $output`).

The `@param ResponseOutputObjectReturnType $output` constructor docblock needs no edit — the imported type was widened in Task 3.

`src/Responses/Responses/RetrieveResponse.php`: identical three changes (imports, `from()` third param + `OutputObjects::parse($attributes['output'], $registry)`, `toArray()` closure union).

`src/Responses/Responses/ListInputItems.php`:

1. Same two imports.
2. `from(array $attributes, MetaInformation $meta, ?ResponsesExtensionRegistry $registry = null)` with `ItemObjects::parse($attributes['data'], $registry)`.
3. Append `|ExtensionOutputItemContract` to BOTH the constructor `@param` docblock union for `$data` and the `toArray()` closure's native union.

`src/Responses/Responses/Streaming/Response.php` (forwards only — `response.completed` etc. carry a full response object that can contain vendor items):

```php
use OpenAI\ValueObjects\ResponsesExtensionRegistry;
```

```php
    /**
     * @param  ResponseType  $attributes
     */
    public static function from(array $attributes, MetaInformation $meta, ?ResponsesExtensionRegistry $registry = null): self
    {
        return new self(
            type: $attributes['type'],
            response: CreateResponse::from($attributes['response'], $meta, $registry),
            sequenceNumber: $attributes['sequence_number'],
            meta: $meta,
        );
    }
```

- [ ] **Step 4: Allow the registry in the responses arch rule**

In `tests/Arch.php`, `test('responses')` allow-list, add:

```php
    'OpenAI\ValueObjects\ResponsesExtensionRegistry',
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Responses/Responses/Extensions.php tests/Arch.php && composer test:types && composer test:unit`
Expected: all PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Responses/Responses tests/Arch.php tests/Responses/Responses/Extensions.php
git commit -m "feat(Responses): thread extension registry into response DTOs"
```

---

### Task 5: Streaming routing (events + nested output items)

**Files:**
- Modify: `src/Responses/Responses/Streaming/OutputItem.php`
- Modify: `src/Responses/Responses/CreateStreamedResponse.php`
- Modify: `src/Responses/StreamResponse.php`
- Test: `tests/Responses/Responses/Extensions.php` (append)

**Interfaces:**
- Consumes: `ExtensionItems::resolve` (Task 3), `Streaming\Response::from(..., ?$registry)` (Task 4), registry lookups (Task 2).
- Produces: `CreateStreamedResponse::from(array $attributes, ?ResponsesExtensionRegistry $registry = null): self` (registry is the SECOND parameter — this DTO's `from()` has no `MetaInformation` parameter); `Streaming\OutputItem::from(array $attributes, MetaInformation $meta, ?ResponsesExtensionRegistry $registry = null): self`; `StreamResponse::__construct(string $responseClass, ResponseInterface $response, ?ResponsesExtensionRegistry $registry = null)`. Task 6 constructs `StreamResponse` with the third argument.

- [ ] **Step 1: Write the failing tests (append to `tests/Responses/Responses/Extensions.php`)**

```php
use OpenAI\Exceptions\UnknownEventException;
use OpenAI\Responses\Responses\CreateStreamedResponse;
use OpenAI\Responses\Responses\Streaming\OutputItem;
use Tests\Fixtures\Extensions\AcmeTraceEvent;

function acmeTraceEventPayload(): array
{
    return [
        'type' => 'acme:trace_event',
        'trace_id' => 'tr_123',
        'spans' => 3,
        '__meta' => meta(),
    ];
}

test('CreateStreamedResponse routes registered vendor events to the extension class', function () {
    $response = CreateStreamedResponse::from(acmeTraceEventPayload(), acmeRegistry());

    expect($response->event)->toBe('acme:trace_event')
        ->and($response->response)->toBeInstanceOf(AcmeTraceEvent::class)
        ->and($response->response->traceId)->toBe('tr_123')
        ->and($response->response->spans)->toBe(3)
        ->and($response->toArray()['data'])->toBe([
            'type' => 'acme:trace_event',
            'trace_id' => 'tr_123',
            'spans' => 3,
        ]);
});

test('CreateStreamedResponse throws on vendor events without a registered extension', function () {
    CreateStreamedResponse::from([
        'type' => 'other:thing',
        '__meta' => meta(),
    ], acmeRegistry());
})->throws(UnknownEventException::class, 'Unknown Responses streaming event: other:thing');

test('CreateStreamedResponse without a registry keeps throwing on vendor events', function () {
    CreateStreamedResponse::from(acmeTraceEventPayload());
})->throws(UnknownEventException::class);

test('streamed output_item events hydrate nested vendor items via the registry', function () {
    $response = CreateStreamedResponse::from([
        'type' => 'response.output_item.added',
        'output_index' => 0,
        'sequence_number' => 2,
        'item' => acmeSearchResultItem(),
        '__meta' => meta(),
    ], acmeRegistry());

    expect($response->response)->toBeInstanceOf(OutputItem::class)
        ->and($response->response->item)->toBeInstanceOf(AcmeSearchResult::class);
});

test('streamed output_item events throw on unknown nested item types', function () {
    CreateStreamedResponse::from([
        'type' => 'response.output_item.added',
        'output_index' => 0,
        'sequence_number' => 2,
        'item' => ['type' => 'other:thing'],
        '__meta' => meta(),
    ], acmeRegistry());
})->throws(UnexpectedValueException::class);
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Responses/Responses/Extensions.php`
Expected: FAIL — `from()` does not accept a registry / vendor event throws `UnknownEventException` in the registered case too / nested vendor item raises `UnhandledMatchError`.

- [ ] **Step 3: Route nested items in `Streaming\OutputItem`**

In `src/Responses/Responses/Streaming/OutputItem.php`:

1. Add imports:

```php
use OpenAI\Actions\Responses\ExtensionItems;
use OpenAI\Contracts\Extensions\ExtensionOutputItemContract;
use OpenAI\ValueObjects\ResponsesExtensionRegistry;
```

2. Append `|ExtensionOutputItemContract` to the constructor's native `$item` union type.
3. Change `from()`:

```php
    /**
     * @param  OutputItemType  $attributes
     */
    public static function from(array $attributes, MetaInformation $meta, ?ResponsesExtensionRegistry $registry = null): self
    {
        $item = match ($attributes['item']['type']) {
            // ... all existing arms unchanged ...
            default => ExtensionItems::resolve($attributes['item'], $registry),
        };
```

(This match previously had no default arm.)

4. `toArray()` needs no change — `$this->item->toArray()` is satisfied by the contract.

- [ ] **Step 4: Route vendor events in `CreateStreamedResponse`**

In `src/Responses/Responses/CreateStreamedResponse.php`:

1. Add imports:

```php
use OpenAI\Contracts\Extensions\ExtensionStreamEventContract;
use OpenAI\ValueObjects\ResponsesExtensionRegistry;
```

2. Append `|ExtensionStreamEventContract` to the constructor's native `$response` union type.
3. Change `from()` signature and three arms:

```php
    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function from(array $attributes, ?ResponsesExtensionRegistry $registry = null): self
```

- the `'response.created', ... 'response.incomplete'` arm becomes `Response::from($attributes, $meta, $registry)` (keep the existing `@phpstan-ignore-line`),
- the `'response.output_item.added', 'response.output_item.done'` arm becomes `OutputItem::from($attributes, $meta, $registry)` (keep the ignore),
- the `default` arm becomes:

```php
            default => self::extensionEvent($event, $attributes, $registry),
```

4. Add the private resolver below `from()`:

```php
    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function extensionEvent(string $event, array $attributes, ?ResponsesExtensionRegistry $registry): ExtensionStreamEventContract
    {
        $class = $registry?->streamEvent($event);

        if ($class === null) {
            throw new UnknownEventException('Unknown Responses streaming event: '.$event);
        }

        return $class::from($attributes);
    }
```

- [ ] **Step 5: Carry the registry in `StreamResponse`**

In `src/Responses/StreamResponse.php`:

```php
use OpenAI\ValueObjects\ResponsesExtensionRegistry;
```

```php
    public function __construct(
        private readonly string $responseClass,
        private readonly ResponseInterface $response,
        private readonly ?ResponsesExtensionRegistry $registry = null,
    ) {
        //
    }
```

and in `getIterator()` replace the yield:

```php
            yield $this->registry === null
                ? $this->responseClass::from($response)
                : $this->responseClass::from($response, $this->registry);
```

(Only the Responses resource ever passes a registry, so stream DTOs of other resources are never called with a second argument. If PHPStan flags the two-argument call on the `TResponse` template, use the codebase's existing `// @phpstan-ignore-line` idiom on that line.)

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Responses/Responses/Extensions.php && composer test:types && composer test:unit`
Expected: all PASS.

- [ ] **Step 7: Commit**

```bash
git add src/Responses/Responses/Streaming/OutputItem.php src/Responses/Responses/CreateStreamedResponse.php src/Responses/StreamResponse.php tests/Responses/Responses/Extensions.php
git commit -m "feat(Responses): route vendor stream events and nested items through the registry"
```

---

### Task 6: Factory / Client / resource wiring + end-to-end tests

**Files:**
- Modify: `src/Factory.php`
- Modify: `src/Client.php`
- Modify: `src/Resources/Responses.php`
- Modify: `tests/Arch.php` (client allow-list)
- Modify: `tests/Pest.php` (mockClient extensions param)
- Create: `tests/Fixtures/Streams/ResponsesCreateWithExtension.txt`
- Modify: `tests/Fixtures/Responses.php` (stream fixture helper)
- Test: `tests/Resources/Responses.php` (append), `tests/OpenAI.php` (append)

**Interfaces:**
- Consumes: everything above — `ResponsesExtensionRegistry::from`, DTO `from(..., ?$registry)` signatures, `StreamResponse(..., ?$registry)`.
- Produces: `Factory::withResponsesExtension(string $extension): self`; `Client::__construct(TransporterContract $transporter, ?ResponsesExtensionRegistry $extensions = null)`; `Resources\Responses::__construct(TransporterContract $transporter, ?ResponsesExtensionRegistry $extensions = null)`. Public user-facing entry point complete.

- [ ] **Step 1: Write the failing factory test (append to `tests/OpenAI.php`)**

```php
test('factory registers responses extensions', function () {
    $client = OpenAI::factory()
        ->withApiKey('foo')
        ->withResponsesExtension(Tests\Fixtures\Extensions\AcmeExtension::class)
        ->make();

    expect($client)->toBeInstanceOf(OpenAI\Client::class);
});

test('factory rejects invalid responses extensions at make time', function () {
    OpenAI::factory()
        ->withResponsesExtension(stdClass::class) // @phpstan-ignore-line
        ->make();
})->throws(OpenAI\Exceptions\InvalidResponsesExtension::class);
```

(Match the file's existing top-of-file `use` style if it imports classes instead of using FQCNs.)

- [ ] **Step 2: Write the failing end-to-end tests (append to `tests/Resources/Responses.php`)**

```php
use OpenAI\ValueObjects\ResponsesExtensionRegistry;
use Tests\Fixtures\Extensions\AcmeExtension;
use Tests\Fixtures\Extensions\AcmeSearchResult;
use Tests\Fixtures\Extensions\AcmeTraceEvent;

test('create hydrates vendor extension items', function () {
    $resource = createResponseResource();
    $resource['output'][] = ['type' => 'acme:search_result', 'query' => 'openresponses', 'score' => 0.98];

    $client = mockClient('POST', 'responses', [
        'model' => 'gpt-4o',
        'input' => 'search please',
    ], OpenAI\ValueObjects\Transporter\Response::from($resource, metaHeaders()), extensions: ResponsesExtensionRegistry::from([AcmeExtension::class]));

    $result = $client->responses()->create([
        'model' => 'gpt-4o',
        'input' => 'search please',
    ]);

    $vendorItem = $result->output[count($result->output) - 1];

    expect($vendorItem)->toBeInstanceOf(AcmeSearchResult::class)
        ->query->toBe('openresponses')
        ->score->toBe(0.98);
});

test('create throws on vendor extension items when none are registered', function () {
    $resource = createResponseResource();
    $resource['output'][] = ['type' => 'acme:search_result', 'query' => 'openresponses', 'score' => 0.98];

    $client = mockClient('POST', 'responses', [
        'model' => 'gpt-4o',
        'input' => 'search please',
    ], OpenAI\ValueObjects\Transporter\Response::from($resource, metaHeaders()));

    $client->responses()->create([
        'model' => 'gpt-4o',
        'input' => 'search please',
    ]);
})->throws(UnexpectedValueException::class);

test('retrieve hydrates vendor extension items', function () {
    $resource = retrieveResponseResource();
    $resource['output'][] = ['type' => 'acme:search_result', 'query' => 'openresponses', 'score' => 0.98];

    $client = mockClient('GET', 'responses/resp_67ccf18ef5fc8190b16dbee19bc54e5f087bb177ab789d5c', [], OpenAI\ValueObjects\Transporter\Response::from($resource, metaHeaders()), extensions: ResponsesExtensionRegistry::from([AcmeExtension::class]));

    $result = $client->responses()->retrieve('resp_67ccf18ef5fc8190b16dbee19bc54e5f087bb177ab789d5c');

    $vendorItem = $result->output[count($result->output) - 1];

    expect($vendorItem)->toBeInstanceOf(AcmeSearchResult::class);
});

test('list hydrates vendor extension input items', function () {
    $resource = listInputItemsResource();
    $resource['data'][] = ['type' => 'acme:search_result', 'query' => 'openresponses', 'score' => 0.98];

    $client = mockClient('GET', 'responses/resp_67ccf18ef5fc8190b16dbee19bc54e5f087bb177ab789d5c/input_items', [], OpenAI\ValueObjects\Transporter\Response::from($resource, metaHeaders()), extensions: ResponsesExtensionRegistry::from([AcmeExtension::class]));

    $result = $client->responses()->list('resp_67ccf18ef5fc8190b16dbee19bc54e5f087bb177ab789d5c');

    $vendorItem = $result->data[count($result->data) - 1];

    expect($vendorItem)->toBeInstanceOf(AcmeSearchResult::class);
});

test('create streamed hydrates vendor extension events and items', function () {
    $response = new Response(
        headers: metaHeaders(),
        body: new Stream(responsesExtensionStream()),
    );

    $client = mockStreamClient('POST', 'responses', [
        'model' => 'gpt-4o',
        'input' => 'search please',
        'stream' => true,
    ], $response, extensions: ResponsesExtensionRegistry::from([AcmeExtension::class]));

    $result = $client->responses()->createStreamed([
        'model' => 'gpt-4o',
        'input' => 'search please',
    ]);

    $events = iterator_to_array($result->getIterator());

    expect($events[0]->event)->toBe('acme:trace_event')
        ->and($events[0]->response)->toBeInstanceOf(AcmeTraceEvent::class)
        ->and($events[0]->response->traceId)->toBe('tr_123');

    expect($events[1]->event)->toBe('response.output_item.added')
        ->and($events[1]->response->item)->toBeInstanceOf(AcmeSearchResult::class);
});

test('create streamed throws on vendor events when none are registered', function () {
    $response = new Response(
        headers: metaHeaders(),
        body: new Stream(responsesExtensionStream()),
    );

    $client = mockStreamClient('POST', 'responses', [
        'model' => 'gpt-4o',
        'input' => 'search please',
        'stream' => true,
    ], $response);

    $result = $client->responses()->createStreamed([
        'model' => 'gpt-4o',
        'input' => 'search please',
    ]);

    iterator_to_array($result->getIterator());
})->throws(OpenAI\Exceptions\UnknownEventException::class);
```

```php
test('cancel hydrates vendor extension items', function () {
    $resource = retrieveResponseResource();
    $resource['output'][] = ['type' => 'acme:search_result', 'query' => 'openresponses', 'score' => 0.98];

    $client = mockClient('POST', 'responses/resp_67ccf18ef5fc8190b16dbee19bc54e5f087bb177ab789d5c/cancel', [
    ], OpenAI\ValueObjects\Transporter\Response::from($resource, metaHeaders()), extensions: ResponsesExtensionRegistry::from([AcmeExtension::class]));

    $result = $client->responses()->cancel('resp_67ccf18ef5fc8190b16dbee19bc54e5f087bb177ab789d5c');

    $vendorItem = $result->output[count($result->output) - 1];

    expect($vendorItem)->toBeInstanceOf(AcmeSearchResult::class);
});

test('retrieve streamed hydrates vendor extension events', function () {
    $response = new Response(
        headers: metaHeaders(),
        body: new Stream(responsesExtensionStream()),
    );

    $client = mockStreamClient('GET', 'responses/resp_67ccf18ef5fc8190b16dbee19bc54e5f087bb177ab789d5c', [
        'stream' => 'true',
    ], $response, extensions: ResponsesExtensionRegistry::from([AcmeExtension::class]));

    $result = $client->responses()->retrieveStreamed('resp_67ccf18ef5fc8190b16dbee19bc54e5f087bb177ab789d5c');

    $events = iterator_to_array($result->getIterator());

    expect($events[0]->response)->toBeInstanceOf(AcmeTraceEvent::class)
        ->and($events[1]->response->item)->toBeInstanceOf(AcmeSearchResult::class);
});
```

- [ ] **Step 3: Create the stream fixture**

`tests/Fixtures/Streams/ResponsesCreateWithExtension.txt` (each `data:` line is a single line of compact JSON; blank line between events; final `[DONE]`):

```
data: {"type":"acme:trace_event","trace_id":"tr_123","spans":3}

data: {"type":"response.output_item.added","output_index":0,"sequence_number":2,"item":{"type":"acme:search_result","query":"openresponses","score":0.98}}

data: [DONE]

```

Append to `tests/Fixtures/Responses.php`:

```php
/**
 * @return resource
 */
function responsesExtensionStream()
{
    return fopen(__DIR__.'/Streams/ResponsesCreateWithExtension.txt', 'r');
}
```

- [ ] **Step 4: Extend the mock helpers (`tests/Pest.php`)**

```php
use OpenAI\ValueObjects\ResponsesExtensionRegistry;
```

```php
function mockClient(string $method, string $resource, array $params, Response|AdaptableResponse|ResponseInterface|string $response, $methodName = 'requestObject', bool $validateParams = true, ?ResponsesExtensionRegistry $extensions = null)
{
    // ... body unchanged until the return ...
    return new Client($transporter, $extensions);
}
```

```php
function mockStreamClient(string $method, string $resource, array $params, ResponseInterface $response, bool $validateParams = true, ?ResponsesExtensionRegistry $extensions = null)
{
    return mockClient($method, $resource, $params, $response, 'requestStream', $validateParams, $extensions);
}
```

- [ ] **Step 5: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Resources/Responses.php tests/OpenAI.php`
Expected: FAIL — `Client::__construct` does not accept a second argument / `withResponsesExtension` undefined.

- [ ] **Step 6: Wire Factory, Client, and the Responses resource**

`src/Factory.php`:

1. Add imports:

```php
use OpenAI\Contracts\Extensions\ResponsesExtensionContract;
use OpenAI\ValueObjects\ResponsesExtensionRegistry;
```

2. Add the field (next to `$queryParams`):

```php
    /**
     * The OpenResponses extensions for the Responses API.
     *
     * @var array<int, class-string<ResponsesExtensionContract>>
     */
    private array $responsesExtensions = [];
```

3. Add the builder method (after `withQueryParam`):

```php
    /**
     * Registers an OpenResponses extension for the Responses API.
     *
     * @param  class-string<ResponsesExtensionContract>  $extension
     */
    public function withResponsesExtension(string $extension): self
    {
        $this->responsesExtensions[] = $extension;

        return $this;
    }
```

4. In `make()`, change the final return:

```php
        $extensions = $this->responsesExtensions === []
            ? null
            : ResponsesExtensionRegistry::from($this->responsesExtensions);

        return new Client($transporter, $extensions);
```

`src/Client.php`:

```php
use OpenAI\ValueObjects\ResponsesExtensionRegistry;
```

```php
    /**
     * Creates a Client instance with the given API token.
     */
    public function __construct(
        private readonly TransporterContract $transporter,
        private readonly ?ResponsesExtensionRegistry $extensions = null,
    ) {
        // ..
    }

    public function responses(): Responses
    {
        return new Responses($this->transporter, $this->extensions);
    }
```

(only `responses()` changes; every other resource method stays as-is)

`src/Resources/Responses.php` — replace the `Transportable` trait with an explicit constructor (the trait only provides a constructor; the class-defined one supersedes it, so drop the trait `use`):

```php
use OpenAI\Contracts\TransporterContract;
use OpenAI\ValueObjects\ResponsesExtensionRegistry;
```

```php
final class Responses implements ResponsesContract
{
    use Concerns\Streamable;

    public function __construct(
        private readonly TransporterContract $transporter,
        private readonly ?ResponsesExtensionRegistry $extensions = null,
    ) {
        // ..
    }
```

and thread it into every response construction:

```php
        return CreateResponse::from($response->data(), $response->meta(), $this->extensions);
```

```php
        return new StreamResponse(CreateStreamedResponse::class, $response, $this->extensions);
```

(both in `createStreamed` and `retrieveStreamed`)

```php
        return RetrieveResponse::from($response->data(), $response->meta(), $this->extensions);
```

(in both `retrieve` and `cancel`)

```php
        return ListInputItems::from($response->data(), $response->meta(), $this->extensions);
```

`DeleteResponse::from` stays two-argument. Check whether `Concerns\Streamable` references `$this->transporter`; if it does, the explicit constructor above still satisfies it (same property name).

- [ ] **Step 7: Allow the registry in the client arch rule**

In `tests/Arch.php`, `test('client')` allow-list:

```php
test('client')->expect('OpenAI\Client')->toOnlyUse([
    'OpenAI\Resources',
    'OpenAI\Contracts',
    'OpenAI\ValueObjects\ResponsesExtensionRegistry',
]);
```

- [ ] **Step 8: Run the full suite**

Run: `composer test`
Expected: lint, PHPStan, type-coverage (100%), and all unit tests PASS.

- [ ] **Step 9: Commit**

```bash
git add src/Factory.php src/Client.php src/Resources/Responses.php tests/Arch.php tests/Pest.php tests/Fixtures tests/Resources/Responses.php tests/OpenAI.php
git commit -m "feat(Responses): register OpenResponses extensions via the client factory"
```

---

## Out of scope (per spec)

- Extra vendor fields on existing core objects (schema extensions) — core DTOs keep dropping unknown keys.
- Request-side helpers — `create()` parameters are already free-form arrays.
- `Testing`/`Fakeable` layer changes — extension authors construct fakes via the public `from($attributes, $meta, $registry)`.
- README documentation (can be a follow-up PR).
