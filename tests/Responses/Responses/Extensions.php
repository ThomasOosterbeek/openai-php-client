<?php

use OpenAI\Actions\Responses\ItemObjects;
use OpenAI\Actions\Responses\OutputObjects;
use OpenAI\Responses\Responses\Output\OutputReasoning;
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
