<?php

use  App\Domain\Iam\Models\Application;;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns_profile_and_empty_claims_for_authenticated_user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson(route('oauth.userinfo'));

    $response->assertOk()
        ->assertJson([
            'sub' => (string) $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'claims' => [
                'apps' => [],
                'roles' => [],
            ],
        ]);
});

it('attaches_application_context_when_requested', function () {
    $user = User::factory()->create();
    $application = Application::factory()->create();

    $response = $this->actingAs($user)
        ->getJson(route('oauth.userinfo', ['app' => $application->app_key]));

    $response->assertOk()
        ->assertJsonPath('claims.application_id', $application->getKey())
        ->assertJsonPath('claims.app_key', $application->app_key)
        ->assertJsonPath('claims.apps', [])
        ->assertJsonPath('claims.roles', []);
});

it('returns_accessible_applications_for_authenticated_user', function () {
    $user = User::factory()->create();
    $application = Application::factory()->create();

    $role = App\Domain\Iam\Models\ApplicationRole::create([
        'application_id' => $application->id,
        'slug' => 'user',
        'name' => 'User',
    ]);

    $user->applicationRoles()->attach($role->id);

    $response = $this->actingAs($user)->getJson(route('users.applications'));

    $response->assertOk()
        ->assertJsonPath('user_id', $user->id)
        ->assertJsonPath('accessible_apps', [$application->app_key])
        ->assertJsonPath('applications.0.app_key', $application->app_key)
        ->assertJsonPath('applications.0.roles.0.slug', 'user');
});
