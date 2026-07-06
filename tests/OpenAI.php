<?php

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Response as Psr7Response;
use OpenAI\Client;
use OpenAI\Exceptions\InvalidResponsesExtension;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\Fixtures\Extensions\AcmeExtension;
use Tests\Fixtures\Extensions\AcmeSearchResult;
use Tests\Fixtures\Extensions\OtherExtension;
use Tests\Fixtures\Extensions\OtherWidget;

it('may create a client', function () {
    $openAI = OpenAI::client('foo');

    expect($openAI)->toBeInstanceOf(Client::class);
});

it('sets organization when provided', function () {
    $openAI = OpenAI::client('foo', 'nunomaduro');

    expect($openAI)->toBeInstanceOf(Client::class);
});

it('sets project when provided', function () {
    $openAI = OpenAI::client('foo', 'nunomaduro', 'openai_proj');

    expect($openAI)->toBeInstanceOf(Client::class);
});

it('may create a client via factory', function () {
    $openAI = OpenAI::factory()
        ->withApiKey('foo')
        ->make();

    expect($openAI)->toBeInstanceOf(Client::class);
});

it('sets an organization via factory', function () {
    $openAI = OpenAI::factory()
        ->withOrganization('nunomaduro')
        ->make();

    expect($openAI)->toBeInstanceOf(Client::class);
});

it('sets an project via factory', function () {
    $openAI = OpenAI::factory()
        ->withOrganization('nunomaduro')
        ->withProject('openai_proj')
        ->make();

    expect($openAI)->toBeInstanceOf(Client::class);
});

it('sets a custom client via factory', function () {
    $openAI = OpenAI::factory()
        ->withHttpClient(new GuzzleClient)
        ->make();

    expect($openAI)->toBeInstanceOf(Client::class);
});

it('sets a custom base url via factory', function () {
    $openAI = OpenAI::factory()
        ->withBaseUri('https://openai.example.com/v1')
        ->make();

    expect($openAI)->toBeInstanceOf(Client::class);
});

it('sets a custom header via factory', function () {
    $openAI = OpenAI::factory()
        ->withHttpHeader('X-My-Header', 'foo')
        ->make();

    expect($openAI)->toBeInstanceOf(Client::class);
});

it('sets a custom query parameter via factory', function () {
    $openAI = OpenAI::factory()
        ->withQueryParam('my-param', 'bar')
        ->make();

    expect($openAI)->toBeInstanceOf(Client::class);
});

it('sets a custom stream handler via factory', function () {
    $openAI = OpenAI::factory()
        ->withHttpClient($client = new GuzzleClient)
        ->withStreamHandler(fn (RequestInterface $request): ResponseInterface => $client->send($request, ['stream' => true]))
        ->make();

    expect($openAI)->toBeInstanceOf(Client::class);
});

test('factory registers responses extensions', function () {
    $attributes = createResponseResource();
    $attributes['output'][] = [
        'type' => 'acme:search_result',
        'query' => 'openresponses',
        'score' => 0.98,
    ];

    $httpClient = Mockery::mock(ClientInterface::class);
    $httpClient
        ->shouldReceive('sendRequest')
        ->once()
        ->andReturn(new Psr7Response(200, ['Content-Type' => 'application/json', ...metaHeaders()], json_encode($attributes)));

    $client = OpenAI::factory()
        ->withApiKey('foo')
        ->withHttpClient($httpClient)
        ->withResponsesExtension(AcmeExtension::class)
        ->make();

    expect($client)->toBeInstanceOf(Client::class);

    $result = $client->responses()->create([
        'model' => 'gpt-4o',
        'input' => 'what was a positive news story from today?',
    ]);

    $output = $result->output;

    expect($output[count($output) - 1])->toBeInstanceOf(AcmeSearchResult::class)
        ->query->toBe('openresponses')
        ->score->toBe(0.98);
});

test('factory threads multiple responses extensions to the client', function () {
    $attributes = createResponseResource();
    $attributes['output'][] = [
        'type' => 'acme:search_result',
        'query' => 'openresponses',
        'score' => 0.98,
    ];
    $attributes['output'][] = [
        'type' => 'other:widget',
        'label' => 'gadget',
    ];

    $httpClient = Mockery::mock(ClientInterface::class);
    $httpClient
        ->shouldReceive('sendRequest')
        ->once()
        ->andReturn(new Psr7Response(200, ['Content-Type' => 'application/json', ...metaHeaders()], json_encode($attributes)));

    $client = OpenAI::factory()
        ->withApiKey('foo')
        ->withHttpClient($httpClient)
        ->withResponsesExtension(AcmeExtension::class)
        ->withResponsesExtension(OtherExtension::class)
        ->make();

    $result = $client->responses()->create([
        'model' => 'gpt-4o',
        'input' => 'what was a positive news story from today?',
    ]);

    $output = $result->output;
    $count = count($output);

    expect($output[$count - 2])->toBeInstanceOf(AcmeSearchResult::class)
        ->and($output[$count - 1])->toBeInstanceOf(OtherWidget::class)
        ->and($output[$count - 1]->label)->toBe('gadget');
});

test('factory rejects invalid responses extensions at make time', function () {
    OpenAI::factory()
        ->withResponsesExtension(stdClass::class) // @phpstan-ignore-line
        ->make();
})->throws(InvalidResponsesExtension::class);
