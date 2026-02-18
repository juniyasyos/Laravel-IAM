<?php

use App\Domain\Iam\Models\Application;
use App\Models\User;

it('includes_localhost_with_port_in_logout_chain', function () {
    $user = User::factory()->create();

    // application simulating a client running on localhost:8088
    Application::factory()->create(['redirect_uris' => ['http://localhost:8088']]);

    $this->actingAs($user)
        ->post('/logout')
        ->assertRedirect(route('sso.logout.chain', ['index' => 0]));

    $resp = $this->get(route('sso.logout.chain', ['index' => 0]));
    $resp->assertStatus(302);

    $target = $resp->headers->get('Location');
    expect(str_starts_with($target, 'http://localhost:8088/iam/logout'))->toBeTrue();
    expect(str_contains($target, 'post_logout_redirect=' . urlencode(route('sso.logout.chain', ['index' => 1], true))))->toBeTrue();
});
