<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

/**
 * @return array<string, mixed>
 */
function exportedOpenApi(): array
{
    $directory = storage_path('framework/testing');

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $path = $directory.'/api.json';

    expect(Artisan::call('scramble:export', ['--path' => $path]))->toBe(0);

    return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
}

beforeEach(function () {
    actingAsApi(['items:read']);
});

test('the interactive documentation page is served', function () {
    $this->get('/docs/api')->assertOk();
});

test('the exported document is OpenAPI 3.1 with bearer authentication', function () {
    $document = exportedOpenApi();

    expect($document['openapi'])->toStartWith('3.1')
        ->and($document['components']['securitySchemes'])->toBe([
            'http' => ['type' => 'http', 'scheme' => 'bearer'],
        ]);
});

test('the document exposes exactly the declared operations', function () {
    $document = exportedOpenApi();

    expect(array_keys($document['paths']))->toEqualCanonicalizing([
        '/v1/items',
        '/v1/items/search',
        '/v1/items/mutate',
        '/v1/items/actions/assign',
        '/v1/labels',
        '/v1/labels/search',
        '/v1/labels/mutate',
        '/v1/users',
        '/v1/users/search',
        '/v1/users/mutate',
        '/v1/comments',
        '/v1/comments/search',
        '/v1/comments/mutate',
    ]);
});

test('forbidden endpoints and relations are absent from the document', function () {
    $document = exportedOpenApi();
    $schemas = array_keys($document['components']['schemas']);

    expect($path = array_keys($document['paths']))
        ->not->toContain('/v1/users/force')
        ->not->toContain('/v1/items/restore')
        ->not->toContain('/v1/items/metrics/count');

    expect($schemas)->not->toContain('Role')
        ->not->toContain('Permission');

    $itemRelations = array_keys($document['components']['schemas']['Item']['properties']);

    expect($itemRelations)
        ->toContain('labels', 'children', 'comments', 'assignee', 'reporter', 'parent')
        ->not->toContain('favorites')
        ->not->toContain('to')
        ->not->toContain('cc')
        ->not->toContain('meta');
});

test('the document describes the item fields and their capabilities', function () {
    $document = exportedOpenApi();
    $fields = collect($document['components']['schemas']['ItemCreate']['properties'])->keys();

    expect($fields)->toContain('title', 'description', 'type', 'is_epic', 'parent_id', 'assignee_id', 'due_date')
        ->not->toContain('id')
        ->not->toContain('reporter_id')
        ->not->toContain('created_at');

    $userCreate = collect($document['components']['schemas']['UserCreate']['properties']);
    expect($userCreate->keys())->toContain('name', 'email', 'password')
        ->and($userCreate['password']['description'])->toStartWith('Write-only.')
        ->and($document['components']['schemas']['User']['properties'])
        ->not->toHaveKey('password');
});

test('the document describes the domain conflict of the item delete', function () {
    $document = exportedOpenApi();

    expect($document['paths']['/v1/items']['delete']['responses'])->toHaveKey('409');
});

test('the committed snapshot is up to date', function () {
    $committed = json_decode((string) file_get_contents(base_path('tests/api.json')), true, flags: JSON_THROW_ON_ERROR);

    expect(exportedOpenApi())->toBe($committed);
});
