<?php

namespace App\Services;

use App\Models\Setting;
use App\Repositories\Contracts\SettingRepositoryInterface;

class SettingService
{
    public function __construct(
        private readonly SettingRepositoryInterface $repository,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $setting = $this->repository->findByKey($key);

        if ($setting) {
            return $setting->getValue();
        }

        $definition = $this->definition($key);

        if ($definition !== null && array_key_exists('default', $definition)) {
            return $definition['default'];
        }

        return $default;
    }

    public function set(string $key, mixed $value, ?array $overrides = null): Setting
    {
        $definition = $this->definition($key) ?? [];

        return $this->repository->upsert(array_merge($definition, $overrides ?? [], [
            'key' => $key,
            'value' => $value,
        ]));
    }

    public function delete(string $key): bool
    {
        return $this->repository->deleteByKey($key);
    }

    public function all(): array
    {
        return $this->repository->all()
            ->mapWithKeys(fn (Setting $setting) => [$setting->key => $setting->getValue()])
            ->all();
    }

    public function group(string $group): array
    {
        $settings = $this->repository->grouped($group)->get($group, collect());

        return $settings
            ->mapWithKeys(fn (Setting $setting) => [$setting->key => $setting->getValue()])
            ->all();
    }

    public function syncFromDefinitions(): int
    {
        return $this->repository->upsertMany($this->definitions());
    }

    public function definitions(): array
    {
        return config('settings.definitions', []);
    }

    public function definition(string $key): ?array
    {
        return config("settings.definitions.{$key}");
    }

    public function mapping(): array
    {
        $mapping = [];

        foreach ($this->definitions() as $key => $definition) {
            $mapping[] = [
                'key' => $key,
                'group' => $definition['group'] ?? null,
                'source' => $definition['source'] ?? null,
                'type' => $definition['type'] ?? 'string',
                'default' => $definition['default'] ?? null,
            ];
        }

        return $mapping;
    }
}
