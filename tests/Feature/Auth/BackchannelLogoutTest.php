<?php

use App\Domain\Iam\Models\Application;
use App\Models\User;
use Illuminate\Support\Facades\Http;

it('does_not_send_backchannel_logout_notifications_when_feature_is_disabled', function () {
    Http::fake();

    $user = User::factory()->create();

    Application::factory()->create(['redirect_uris' => ['http://client1.test']]);

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect();

    Http::assertNothingSent();
});
