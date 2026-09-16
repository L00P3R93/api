<?php

function logViewerBasicAuthHeader(string $username, string $password): array
{
    return ['Authorization' => 'Basic '.base64_encode("{$username}:{$password}")];
}

it('rejects log viewer access without credentials', function () {
    $response = $this->get(config('log-viewer.route_path'));

    $response->assertStatus(401);
});

it('rejects log viewer access with invalid credentials', function () {
    $response = $this->withHeaders(
        logViewerBasicAuthHeader('wrong-user', 'wrong-password')
    )->get(config('log-viewer.route_path'));

    $response->assertStatus(401);
});

it('allows log viewer access with valid credentials', function () {
    $response = $this->withHeaders(
        logViewerBasicAuthHeader(
            config('log-viewer.basic_auth.username'),
            config('log-viewer.basic_auth.password'),
        )
    )->get(config('log-viewer.route_path'));

    $response->assertStatus(200);
});
