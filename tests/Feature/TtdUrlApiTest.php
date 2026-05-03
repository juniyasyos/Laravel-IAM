<?php

use App\Domain\Iam\Services\TokenBuilder;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

test('ttd url endpoint returns presigned url', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test-user@example.com',
        'password' => bcrypt('password'),
        'remember_token' => 'remember-token',
        'ttd_url' => 'ttd/sample.png',
    ]);

    $adapter = \Mockery::mock(FilesystemAdapter::class);
    $adapter->shouldReceive('exists')->with('ttd/sample.png')->andReturn(true);
    $adapter->shouldReceive('temporaryUrl')
        ->with('ttd/sample.png', \Mockery::on(fn($value) => $value instanceof \DateTimeInterface))
        ->andReturn('https://signed.example.com/ttd/sample.png?signature=abc');

    Storage::shouldReceive('disk')
        ->with('s3')
        ->andReturn($adapter);

    $token = app(TokenBuilder::class)->buildTokenForUser($user);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token,
    ])->getJson("/api/users/{$user->id}/ttd-url");

    $response->assertOk();
    $response->assertJsonStructure(['url']);
    $this->assertStringContainsString('https://signed.example.com/ttd/sample.png', $response->json('url'));
});

test('returns 401 if bearer token is missing', function () {
    $user = User::create([
        'name' => 'Missing Token',
        'email' => 'missing-token@example.com',
        'password' => bcrypt('password'),
        'remember_token' => 'remember-token',
    ]);

    $response = $this->getJson("/api/users/{$user->id}/ttd-url");

    $response->assertStatus(401);
    $response->assertJson(['message' => 'Unauthenticated: bearer token is required.']);
});

test('returns 401 when bearer token is invalid', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer invalid.token.value',
    ])->getJson('/api/users/1/ttd-url');

    $response->assertStatus(401);
    $response->assertJson(['message' => 'Unauthenticated: invalid or expired SSO token.']);
});

test('returns 403 when authenticated user does not match requested user', function () {
    $userA = User::create([
        'name' => 'User A',
        'email' => 'user-a@example.com',
        'password' => bcrypt('password'),
        'remember_token' => 'remember-token',
    ]);
    $userB = User::create([
        'name' => 'User B',
        'email' => 'user-b@example.com',
        'password' => bcrypt('password'),
        'remember_token' => 'remember-token',
    ]);

    $token = app(TokenBuilder::class)->buildTokenForUser($userA);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token,
    ])->getJson("/api/users/{$userB->id}/ttd-url");

    $response->assertStatus(403);
    $response->assertJson(['message' => 'You are not authorized to access this TTD file.']);
});

test('returns 404 when ttd file not found', function () {
    $user = User::create([
        'name' => 'No TTD User',
        'email' => 'no-ttd@example.com',
        'password' => bcrypt('password'),
        'remember_token' => 'remember-token',
        'ttd_url' => null,
    ]);

    $token = app(TokenBuilder::class)->buildTokenForUser($user);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token,
    ])->getJson("/api/users/{$user->id}/ttd-url");

    $response->assertStatus(404);
    $response->assertJson(['message' => 'User does not have a TTD file configured.']);
});
