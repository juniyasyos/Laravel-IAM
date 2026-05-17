<?php

namespace App\Repositories\Contracts;

use App\Models\Setting;
use Illuminate\Support\Collection;

interface SettingRepositoryInterface
{
    public function all(): Collection;

    public function grouped(?string $group = null): Collection;

    public function findByKey(string $key): ?Setting;

    public function upsert(array $attributes): Setting;

    public function deleteByKey(string $key): bool;

    public function upsertMany(array $items): int;
}
