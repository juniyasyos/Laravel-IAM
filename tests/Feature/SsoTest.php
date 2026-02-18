<?php

use  App\Domain\Iam\Models\Application;;
use App\Models\User;
use App\Services\Sso\TokenService;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    config([
        'sso.secret' => 'testing-secret',
        'sso.issuer' => 'testing-issuer',
        'sso.ttl' => 120,
    ]);
});

it('issues a token and redirects to the client callback', function (): void {
    $user = User::factory()->create();
    $application = Application::factory()->create([
        'callback_url' => 'http://127.0.0.1:8080/callback',
    ]);

    $response = $this->actingAs($user)->get('/sso/redirect?app=' . $application->app_key);

    $response->assertRedirect();

    $location = $response->headers->get('Location');
    expect($location)->toStartWith('http://127.0.0.1:8080/callback');

    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($query)->toHaveKey('token');

    /** @var TokenService $tokens */
    $tokens = app(TokenService::class);
    $claims = $tokens->verify($query['token']);

    expect($claims['sub'])->toEqual($user->getAuthIdentifier());
    expect($claims['email'])->toEqual($user->email);
    expect($claims['app'])->toEqual($application->app_key);
    expect($claims['iss'])->toEqual('testing-issuer');
});

it('verifies a valid token via the API endpoint', function (): void {
    $user = User::factory()->create();
    $application = Application::factory()->create([
        'callback_url' => 'http://127.0.0.1:8081/callback',
    ]);

    /** @var TokenService $tokens */
    $tokens = app(TokenService::class);

    $token = $tokens->issue($user, $application);

    $response = $this->postJson('/api/sso/verify', [
        'token' => $token,
    ]);

    $response
        ->assertOk()
        ->assertJson([
            'user' => [
                'id' => $user->getAuthIdentifier(),
                'email' => $user->email,
            ],
            'app' => $application->app_key,
            'issuer' => 'testing-issuer',
        ]);
});

it('rejects invalid or expired tokens', function (): void {
    $user = User::factory()->create();
    $application = Application::factory()->create([
        'callback_url' => 'http://127.0.0.1:8081/callback',
    ]);

    /** @var TokenService $tokens */
    $tokens = app(TokenService::class);

    // Invalid token value.
    $this->postJson('/api/sso/verify', ['token' => 'not-a-token'])
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Invalid or expired token.',
        ]);

    // Expired token.
    $now = Carbon::parse('2024-01-01 00:00:00');
    Carbon::setTestNow($now);

    $token = $tokens->issue($user, $application);

    Carbon::setTestNow($now->copy()->addSeconds((int) config('sso.ttl') + 10));

    $this->postJson('/api/sso/verify', ['token' => $token])
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Invalid or expired token.',
        ]);

    Carbon::setTestNow();
});

it('resumes SSO redirect after logging in with an app context', function (): void {
    $application = Application::factory()->create([
        'app_key' => 'client-example',
        'callback_url' => 'http://127.0.0.1:8080/callback',
    ]);

    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->get('/login?app=' . $application->app_key)
        ->assertStatus(200);

    $response = $this->post('/login', [
        'email' => 'user@example.com',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('sso.redirect', ['app' => $application->app_key], absolute: true));
});

it('invalidates previously issued tokens after user logout', function (): void {
    $user = User::factory()->create();
    $application = Application::factory()->create([
        'callback_url' => 'http://127.0.0.1:8081/callback',
    ]);

    /** @var TokenService $tokens */
    $tokens = app(TokenService::class);
    $token = $tokens->issue($user, $application);

    // Token valid before logout
    $this->postJson('/api/sso/verify', ['token' => $token])->assertOk();

    // Perform web logout (should revoke refresh tokens and mark logout time)
    $this->actingAs($user)->post('/logout')->assertRedirect();

    // Previously issued token must now be rejected
    $this->postJson('/api/sso/verify', ['token' => $token])
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'Invalid or expired token.']);
});
