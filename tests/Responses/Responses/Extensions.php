<?php

use OpenAI\Actions\Responses\ItemObjects;
use OpenAI\Actions\Responses\OutputObjects;
use OpenAI\Exceptions\UnknownEventException;
use OpenAI\Responses\Responses\CreateResponse;
use OpenAI\Responses\Responses\CreateStreamedResponse;
use OpenAI\Responses\Responses\ListInputItems;
use OpenAI\Responses\Responses\Output\OutputReasoning;
use OpenAI\Responses\Responses\RetrieveResponse;
use OpenAI\Responses\Responses\Streaming\OutputItem;
use OpenAI\ValueObjects\ResponsesExtensionRegistry;
use Tests\Fixtures\Extensions\AcmeExtension;
use Tests\Fixtures\Extensions\AcmeSearchResult;
use Tests\Fixtures\Extensions\AcmeTraceEvent;

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

    expect($output[0])->toBeInstanceOf(OutputReasoning::class)
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
