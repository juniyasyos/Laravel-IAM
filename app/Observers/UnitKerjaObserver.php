<?php

namespace App\Observers;

use App\Jobs\SyncApplicationUsers;
use App\Models\UnitKerja;
use Illuminate\Support\Facades\Log;

class UnitKerjaObserver
{
    public function saved(UnitKerja $unitKerja): void
    {
        if (! $unitKerja->wasRecentlyCreated && ! $unitKerja->wasChanged(['unit_name', 'description', 'slug'])) {
            return;
        }

        $this->dispatchUnitSync($unitKerja, 'saved');
    }

    public function deleted(UnitKerja $unitKerja): void
    {
        $this->dispatchUnitSync($unitKerja, 'deleted');
    }

    public function restored(UnitKerja $unitKerja): void
    {
        $this->dispatchUnitSync($unitKerja, 'restored');
    }

    protected function dispatchUnitSync(UnitKerja $unitKerja, string $event): void
    {
        if (config('iam.user_sync_mode', 'pull') !== 'push') {
            return;
        }

        Log::info('iam.unit_kerja_observer_trigger', [
            'unit_kerja_id' => $unitKerja->getKey(),
            'slug' => $unitKerja->slug,
            'unit_name' => $unitKerja->unit_name,
            'event' => $event,
        ]);

        SyncApplicationUsers::dispatch([], [], [], null);
    }
}
