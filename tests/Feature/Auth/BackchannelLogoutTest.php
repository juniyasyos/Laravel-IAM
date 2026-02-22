<?php

use App\Domain\Iam\Models\Application;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'sso.backchannel.enabled' => true,
        'iam.backchannel_method' => 'hmac',
        'iam.backchannel_verify' => true,
    ]);
});

it('sends_backchannel_logout_notifications_to_registered_clients', function () {
    Http::fake();

    $user = User::factory()->create();

    Application::factory()->create(['redirect_uris' => ['http://client1.test']]);

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect();

    Http::assertSent(function ($request) use ($user) {
        $urlOK = $request->url() === 'http://client1.test/iam/backchannel-logout';
        $eventOK = $request['event'] === 'logout' && ($request['user']['id'] ?? null) === $user->getKey();

        $header = config('sso.backchannel.signature_header');
        return $urlOK && $eventOK && ! empty($request->header($header));
    });
});
