<?php

namespace App\Jobs;

use App\Domain\Iam\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon as Carbon;
use App\Models\User;

class NotifyClientsOfLogout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly User $user) {}

    public function handle(): void
    {
        if (! config('sso.backchannel.enabled', false)) {
            return;
        }

        $apps = Application::enabled()
            ->get()
            ->filter(fn(Application $a) => ! empty($a->backchannel_logout_uri))
            ->values();

        $timestamp = Carbon::now()->toIso8601String();

        foreach ($apps as $app) {
            $uri = $app->backchannel_logout_uri;

            if (! $uri) {
                continue;
            }

            $payload = [
                'event' => 'logout',
                'timestamp' => $timestamp,
                'user' => [
                    'id' => $this->user->getKey(),
                    'email' => $this->user->email ?? null,
                ],
                'application' => [
                    'app_key' => $app->app_key,
                    'name' => $app->name,
                ],
            ];

            $body = json_encode($payload);
            $signature = hash_hmac('sha256', $body, (string) config('sso.secret'));
            $sigHeader = config('sso.backchannel.signature_header', 'IAM-Signature');

            try {
                Http::timeout(3)
                    ->withHeaders([
                        'Accept' => 'application/json',
                        $sigHeader => $signature,
                    ])
                    ->post($uri, $payload)
                    ->throw();

                Log::info('backchannel_logout_success', ['uri' => $uri, 'app_key' => $app->app_key, 'user_id' => $this->user->getKey()]);
            } catch (RequestException $e) {
                Log::warning('backchannel_logout_failed', [
                    'uri' => $uri,
                    'app_key' => $app->app_key,
                    'user_id' => $this->user->getKey(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
