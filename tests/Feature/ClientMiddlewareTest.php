<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use App\Models\User;

beforeEach(function (): void {
    // Ensure middleware config enabled for tests
    config(['iam.verify_each_request' => true]);
});

it('allows request when IAM verify returns OK', function () {
    $user = User::factory()->create();

    Http::fake([
        '*' => Http::response(['user' => ['id' => $user->id]], 200),
    ]);

    Route::middleware([\Juniyasyos\IamClient\Http\Middleware\EnsureAuthenticated::class])
        ->get('/_iam-protected', fn () => 'ok');

    $this->actingAs($user);
    session(['iam.access_token' => 'valid-token']);

    $response = $this->get('/_iam-protected');

    $response->assertOk()->assertSee('ok');
});

it('invalidates session when IAM verify returns non-ok', function () {
    $user = User::factory()->create();

    Http::fake([
        '*' => Http::response(['active' => false], 422),
    ]);

    Route::middleware([\Juniyasyos\IamClient\Http\Middleware\EnsureAuthenticated::class])
        ->get('/_iam-protected2', fn () => 'ok');

    $this->actingAs($user);
    session(['iam.access_token' => 'invalid-token']);

    $response = $this->get('/_iam-protected2');

    $response->assertRedirect();
    $this->assertFalse(auth()->check());
    $this->assertNull(session('iam.access_token'));
});
