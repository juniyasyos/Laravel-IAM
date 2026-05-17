<?php

namespace App\Repositories;

use App\Models\Setting;
use App\Repositories\Contracts\SettingRepositoryInterface;
use Illuminate\Support\Collection;

class SettingRepository implements SettingRepositoryInterface
{
    public function all(): Collection
    {
        return Setting::query()
            ->orderBy('group')
            ->orderBy('key')
            ->get();
    }

    public function grouped(?string $group = null): Collection
    {
        $query = Setting::query()->orderBy('group')->orderBy('key');

        if ($group !== null) {
            $query->where('group', $group);
        }

        return $query->get()->groupBy('group');
    }

    public function findByKey(string $key): ?Setting
    {
        return Setting::query()->where('key', $key)->first();
    }

    public function upsert(array $attributes): Setting
    {
        $key = (string) $attributes['key'];

        $payload = [
            'group' => (string) ($attributes['group'] ?? 'general'),
            'value' => $this->serializeValue($attributes['value'] ?? null, (string) ($attributes['type'] ?? 'string')),
            'type' => (string) ($attributes['type'] ?? 'string'),
            'description' => $attributes['description'] ?? null,
            'input_type' => (string) ($attributes['input_type'] ?? 'text'),
            'select_options' => $attributes['select_options'] ?? null,
            'validation_rules' => $attributes['validation_rules'] ?? null,
            'is_readonly' => (bool) ($attributes['is_readonly'] ?? false),
            'is_sensitive' => (bool) ($attributes['is_sensitive'] ?? false),
            'environment' => $attributes['environment'] ?? null,
            'category' => $attributes['category'] ?? null,
        ];

        return Setting::updateOrCreate(['key' => $key], $payload);
    }

    public function deleteByKey(string $key): bool
    {
        $setting = $this->findByKey($key);

        if (! $setting) {
            return false;
        }

        return (bool) $setting->delete();
    }

    public function upsertMany(array $items): int
    {
        $count = 0;

        foreach ($items as $item) {
            if (! is_array($item) || empty($item['key'])) {
                continue;
            }

            $this->upsert($item);
            $count++;
        }

        return $count;
    }

    private function serializeValue(mixed $value, string $type): string
    {
        return match ($type) {
            'integer' => (string) (int) $value,
            'boolean' => $value ? 'true' : 'false',
            'array', 'json' => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]',
            default => (is_scalar($value) || $value === null)
                ? (string) $value
                : (json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: ''),
        };
    }
}
