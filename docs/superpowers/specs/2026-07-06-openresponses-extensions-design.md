# OpenResponses Extensions — Design

**Date:** 2026-07-06
**Branch:** `responses-extension`
**Status:** Approved

## Goal

Let library users plug typed OpenResponses vendor extensions into the Responses API flows, with strong contracts, full PHPStan type safety, 100% test coverage of the contract/flow, and minimal changes to the existing structure.

## Background

The [OpenResponses specification](https://www.openresponses.org/specification) allows implementors to extend the Responses API using implementor-slug prefixes: item types (`acme:search_result`) and streaming event types (`acme:trace_event`) that are not part of the core spec MUST be prefixed with the vendor slug. Today this client throws on any such payload:

- `Actions\Responses\OutputObjects::parse` — `UnexpectedValueException` on unknown item types
- `Actions\Responses\ItemObjects::parse` — same, for `list()` input items
- `Responses\Responses\Streaming\OutputItem::from` — `UnhandledMatchError` (match without default arm) on unknown nested item types
- `Responses\Responses\CreateStreamedResponse::from` — `UnknownEventException` on unknown event types

## Scope

**In scope**

- Typed handling of vendor-prefixed **item types** (surface A) in `create`, `retrieve`, `cancel`, `list` (input items), and inside streamed `response.output_item.*` events.
- Typed handling of vendor-prefixed **streaming event types** (surface B) in `createStreamed` / `retrieveStreamed`.
- Per-client extension registration via the `Factory`.

**Out of scope**

- Schema extensions: extra vendor fields on existing core objects (surface C). Core DTOs keep dropping unknown keys.
- Request-side mechanisms — `create()` parameters are already free-form arrays; vendor request fields and items need no library support.
- Changes to the `Testing`/`Fakeable` layer (see Boundaries).

## Public API

Extension authors write, in their own codebase:

```php
final class AcmeExtension implements ResponsesExtensionContract
{
    public static function namespace(): string
    {
        return 'acme';
    }

    /** @return array<string, class-string<ExtensionOutputItemContract>> */
    public static function outputItems(): array
    {
        return ['acme:search_result' => AcmeSearchResult::class];
    }

    /** @return array<string, class-string<ExtensionStreamEventContract>> */
    public static function streamEvents(): array
    {
        return ['acme:trace_event' => AcmeTraceEvent::class];
    }
}
```

Item/event classes implement the corresponding contract: a static `from(array $attributes): static` hydrator and `toArray(): array`. The author owns their typing.

Registration and use:

```php
$client = OpenAI::factory()
    ->withApiKey($key)
    ->withBaseUri('api.acme.ai/v1')
    ->withResponsesExtension(AcmeExtension::class) // repeatable
    ->make();

foreach ($client->responses()->create([...])->output as $item) {
    if ($item instanceof AcmeSearchResult) {
        $item->score; // fully typed, user-defined
    }
}
```

## New files

| File | Purpose |
|---|---|
| `src/Contracts/Extensions/ResponsesExtensionContract.php` | `namespace(): string`, `outputItems(): array`, `streamEvents(): array` (all static) |
| `src/Contracts/Extensions/ExtensionOutputItemContract.php` | `from(array): static`, `toArray(): array` — items on output and input-items list |
| `src/Contracts/Extensions/ExtensionStreamEventContract.php` | same shape, for streamed event payloads |
| `src/ValueObjects/ResponsesExtensionRegistry.php` | immutable registry, validated at construction |
| `src/Exceptions/InvalidResponsesExtension.php` | extends `InvalidArgumentException`; registration-time failures |

## Registry

`ResponsesExtensionRegistry` is built once in `Factory::make()` from the registered extension class-strings. Construction validates, throwing `InvalidResponsesExtension` on:

- namespace slug not matching the implementor-slug shape (non-empty, lowercase alphanumeric with dashes/underscores);
- any map key not prefixed with `"{namespace}:"` of its own extension;
- a mapped class that does not exist or does not implement the required contract;
- the same item/event type claimed by more than one extension.

Lookups: `outputItem(string $type): ?class-string<ExtensionOutputItemContract>`, `streamEvent(string $type): ?class-string<ExtensionStreamEventContract>`.

## Flow (threading)

All additions are **optional parameters with `null` defaults** — no signature is broken; `from()` is not part of any public contract, so widening it is safe.

1. `Factory::withResponsesExtension(string $extension): self` collects class-strings; `make()` builds the registry and passes it to `Client::__construct` (new optional param).
2. `Client::responses()` passes the registry to the `Responses` resource.
3. The resource forwards it to `CreateResponse::from`, `RetrieveResponse::from`, `ListInputItems::from` (new optional third param) and to `StreamResponse::__construct` (new optional param), which forwards it to `CreateStreamedResponse::from`.
4. DTOs forward it into the four routing points:

| Routing point | Change |
|---|---|
| `OutputObjects::parse($items, ?$registry)` | before `default => throw`, a `vendor:type` with a registered class hydrates via `$class::from($item)` |
| `ItemObjects::parse($items, ?$registry)` | same |
| `Streaming\OutputItem::from($attributes, $meta, ?$registry)` | same lookup for nested `item.type`; match also gains a strict `default => throw UnexpectedValueException` (fixing today's `UnhandledMatchError`) |
| `CreateStreamedResponse::from($attributes, ?$registry)` | event-type registry lookup before the event match's `default => throw` |

PHPStan types: `CreateResponse::$output` / `ListInputItems::$data` / `OutputItem::$item` unions widen with `ExtensionOutputItemContract`; `CreateStreamedResponse::$response` widens with `ExtensionStreamEventContract`. `instanceof` narrows to the author's concrete class.

No other resource or DTO is touched.

## Error handling

- **Registration time (fail fast):** all validation errors throw `InvalidResponsesExtension` from `Factory::make()` before any HTTP call.
- **Response time (strict, unchanged):** vendor types with no registered handler throw exactly what the library throws today — `UnexpectedValueException` for items, `UnknownEventException` for events. No silent skipping, no generic fallback DTO.
- Exceptions raised inside an extension's `from()` propagate untouched.

## Testing

- Fixture extension in `tests/Fixtures/Extensions/` (`AcmeExtension`, one typed item class, one typed event class).
- Unit: one test per registry validation failure mode, plus happy path; factory wiring (repeatable registration, registry reaches the client).
- Flow, per routing point: registered vendor type → typed instance with hydrated values; unregistered vendor type → exact existing exception; no registry → identical to current behavior (regression guard).
- End-to-end via existing transporter fakes: `create`, `retrieve`, `cancel`, `list`, `createStreamed`, `retrieveStreamed` with vendor payloads, including a vendor item nested inside `response.output_item.added`.
- Gates: PHPStan (existing level) and `pest --type-coverage --min=100` must stay green; 100% line coverage on all new code.

## Boundaries

- The shared `Fakeable` trait and fixture layer are not modified. Extension authors needing fake Responses containing vendor items call the public `from($attributes, $meta, $registry)` directly.
- Generic fallback containers for unregistered vendor data were considered and rejected in favor of strict throwing (explicit user decision).
- Approaches considered and rejected: instance-based parser services (too large a refactor), call-scoped static registry (global mutable state).
