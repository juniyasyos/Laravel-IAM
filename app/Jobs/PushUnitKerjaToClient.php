<?php

namespace App\Jobs;

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Services\UnitKerjaPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PushUnitKerjaToClient implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $applicationIds = [];

    public ?int $unitKerjaId = null;

    public function __construct(array $applicationIds = [], ?int $unitKerjaId = null)
    {
        $this->applicationIds = $applicationIds;
        $this->unitKerjaId = $unitKerjaId;
    }

    public function handle(): void
    {
        $query = Application::query()->enabled();

        if (! empty($this->applicationIds)) {
            $query->whereIn('id', $this->applicationIds);
        }

        $service = new UnitKerjaPushService();

        foreach ($query->get() as $application) {
            try {
                $result = $service->push($application, $this->unitKerjaId);

                if (! ($result['success'] ?? false)) {
                    Log::warning('iam.push_unit_kerja_skipped', [
                        'app_key' => $application->app_key,
                        'application_id' => $application->id,
                        'result' => $result,
                    ]);

                    continue;
                }

                Log::info('iam.push_unit_kerja_completed', [
                    'app_key' => $application->app_key,
                    'application_id' => $application->id,
                    'result' => $result,
                ]);
            } catch (\Exception $e) {
                Log::error('iam.push_unit_kerja_failed', [
                    'app_key' => $application->app_key,
                    'application_id' => $application->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
