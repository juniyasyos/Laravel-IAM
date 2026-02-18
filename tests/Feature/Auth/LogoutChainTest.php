<?php

use App\Domain\Iam\Models\Application;
use App\Models\User;

it('starts_logout_chain_and_redirects_to_client_iam_logout', function () {
    $user = User::factory()->create();

    Application::factory()->create(['redirect_uris' => ['http://client1.test']]);
    Application::factory()->create(['redirect_uris' => ['http://client2.test']]);

    $this->actingAs($user)
        ->post('/logout')
        ->assertRedirect(route('sso.logout.chain', ['index' => 0]));

    $resp = $this->get(route('sso.logout.chain', ['index' => 0]));
    $resp->assertStatus(302);

    $target = $resp->headers->get('Location');
    expect(str_starts_with($target, 'http://client1.test/iam/logout'))->toBeTrue();
    expect(str_contains($target, 'post_logout_redirect=' . urlencode(route('sso.logout.chain', ['index' => 1], true))))->toBeTrue();

    $resp2 = $this->get(route('sso.logout.chain', ['index' => 1]));
    $resp2->assertStatus(302);
    $target2 = $resp2->headers->get('Location');
    expect(str_starts_with($target2, 'http://client2.test/iam/logout'))->toBeTrue();
});
