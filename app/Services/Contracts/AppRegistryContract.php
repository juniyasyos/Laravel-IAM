<?php

namespace App\Services\Contracts;

use App\Models\Application;
use Illuminate\Support\Collection;

interface AppRegistryContract
{
    public function create(array $payload): Application;

    public function update(Application $application, array $payload): Application;

    public function delete(Application $application): void;

    public function getByKeyOrFail(string $appKey): Application;

    public function enabledList(): Collection;
}
