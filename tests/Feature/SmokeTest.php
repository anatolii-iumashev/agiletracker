<?php

declare(strict_types=1);

// ─── Application health ──────────────────────────────────────────────────

test('application is healthy and redirects guests to login', function () {
    $response = $this->get('/');

    $response->assertStatus(302);
    $response->assertRedirect('/login');
});

test('login page is accessible', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
    $response->assertSee('Sign in');
});

test('authenticated user reaches dashboard', function () {
    actingAsUser();

    $response = $this->get('/');

    $response->assertStatus(200);
});

// ─── Panel pages (unauthenticated) ───────────────────────────────────────

test('guest cannot access admin pages', function (string $path) {
    $response = $this->get($path);

    $response->assertRedirect('/login');
})->with([
    '/i',
    '/users',
    '/manage-settings',
    '/labels',
    '/inbox',
    '/kanban-board',
    '/favorite',
    '/sent',
]);

// ─── Panel pages (authenticated) ─────────────────────────────────────────

test('authenticated user can access panel pages', function (string $path) {
    actingAsUser();

    $response = $this->get($path);

    $response->assertStatus(200);
})->with([
    '/',
    '/i',
    '/users',
    '/labels',
    '/inbox',
    '/kanban-board',
    '/favorite',
    '/sent',
]);

// ─── 404 for unknown routes ──────────────────────────────────────────────

test('unknown routes return 404', function () {
    actingAsUser();

    $response = $this->get('/non-existent-page');

    $response->assertStatus(404);
});
