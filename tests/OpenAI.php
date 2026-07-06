<?php

use GuzzleHttp\Client as GuzzleClient;
use OpenAI\Client;
use OpenAI\Exceptions\InvalidResponsesExtension;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\Fixtures\Extensions\AcmeExtension;

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
    $client = OpenAI::factory()
        ->withApiKey('foo')
        ->withResponsesExtension(AcmeExtension::class)
        ->make();

    expect($client)->toBeInstanceOf(Client::class);
});

test('factory rejects invalid responses extensions at make time', function () {
    OpenAI::factory()
        ->withResponsesExtension(stdClass::class) // @phpstan-ignore-line
        ->make();
})->throws(InvalidResponsesExtension::class);
