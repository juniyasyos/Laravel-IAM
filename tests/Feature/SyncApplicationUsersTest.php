<?php

use App\Domain\Iam\Models\Application;
use App\Jobs\SyncApplicationUsers;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    // default to legacy hmac behaviour and keep verification enabled
    config([
        'iam.backchannel_method' => 'hmac',
        'iam.backchannel_verify' => true,
    ]);
    Queue::fake();
    Http::fake();
});

it('queues a job that fetches with jwt token when syncing', function () {
    $app = Application::factory()->create([
        'callback_url' => 'http://client.test',
        'app_key' => 'abc',
    ]);

    // dispatch job directly rather than going through HTTP
    SyncApplicationUsers::dispatch($app);

    // the job will run immediately because of the fake queue
    Http::assertSent(function ($request) use ($app) {
        // check URL and signature header presence (legacy HMAC)
        $urlOK = $request->url() === 'http://client.test/api/iam/sync-users?app_key=abc';
        $header = config('sso.backchannel.signature_header');
        return $urlOK && ! empty($request->header($header));
    });
});
