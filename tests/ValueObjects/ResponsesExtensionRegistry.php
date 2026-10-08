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
    ResponsesExtensionRegistry::from([stdClass::class]); // @phpstan-ignore-line
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

test('scoped exposes the registry only for the duration of the callback', function () {
    $registry = ResponsesExtensionRegistry::from([AcmeExtension::class]);

    $inside = ResponsesExtensionRegistry::scoped($registry, fn () => ResponsesExtensionRegistry::current());

    expect($inside)->toBe($registry)
        ->and(ResponsesExtensionRegistry::current())->toBeNull();
});

test('scoped restores the previous registry when the callback throws', function () {
    $registry = ResponsesExtensionRegistry::from([AcmeExtension::class]);

    try {
        ResponsesExtensionRegistry::scoped($registry, fn () => throw new RuntimeException);
    } catch (RuntimeException) {
        //
    }

    expect(ResponsesExtensionRegistry::current())->toBeNull();
});

test('a nested null scope hides the outer registry', function () {
    $registry = ResponsesExtensionRegistry::from([AcmeExtension::class]);

    $inner = ResponsesExtensionRegistry::scoped($registry, fn () => ResponsesExtensionRegistry::scoped(null, fn () => ResponsesExtensionRegistry::current()));

    expect($inner)->toBeNull();
});
