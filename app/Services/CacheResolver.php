<?php

namespace App\Services;

use App\Models\Application;
use App\Models\User;
use App\Services\Contracts\CacheResolverContract;
use App\Services\Contracts\RbacServiceContract;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CacheResolver implements CacheResolverContract
{
    private CacheRepository $cache;

    public function __construct(
        private readonly RbacServiceContract $rbacService,
        ?CacheRepository $cache = null,
        private readonly int $ttlSeconds = 300
    ) {
        $store = config('permission.cache.store', 'default');
        $this->cache = $cache ?? Cache::store($store === 'default' ? null : $store);
    }

    public function rememberUserPerms(User $user, Application $application): array
    {
        $key = $this->cacheKey($user, $application->getKey());

        $permissions = $this->cache->remember($key, now()->addSeconds($this->ttlSeconds), function () use ($user, $application) {
            return $this->rbacService->userPermissions($user, $application);
        });

        $this->rememberIndex($user, $application->getKey());

        return $this->normalise($permissions);
    }

    public function invalidateUser(User $user, ?Application $application = null): void
    {
        $indexKey = $this->indexKey($user);

        if ($application) {
            $this->cache->forget($this->cacheKey($user, $application->getKey()));
            $this->pullFromIndex($user, $application->getKey());

            return;
        }

        $applicationIds = $this->cache->get($indexKey, []);

        foreach ($applicationIds as $id) {
            $this->cache->forget($this->cacheKey($user, (int) $id));
        }

        $this->cache->forget($indexKey);
    }

    protected function rememberIndex(User $user, int $applicationId): void
    {
        $indexKey = $this->indexKey($user);
        $existing = $this->cache->get($indexKey, []);
        $existing[] = $applicationId;
        $unique = array_values(array_unique(array_map('intval', $existing)));

        $this->cache->put($indexKey, $unique, now()->addSeconds($this->ttlSeconds));
    }

    protected function pullFromIndex(User $user, int $applicationId): void
    {
        $indexKey = $this->indexKey($user);
        $existing = $this->cache->get($indexKey, []);
        $filtered = array_values(array_filter($existing, static fn ($id) => (int) $id !== $applicationId));

        if ($filtered === []) {
            $this->cache->forget($indexKey);

            return;
        }

        $this->cache->put($indexKey, $filtered, now()->addSeconds($this->ttlSeconds));
    }

    protected function cacheKey(User $user, int $applicationId): string
    {
        return sprintf('perms:%d:%d', $user->getKey(), $applicationId);
    }

    protected function indexKey(User $user): string
    {
        return sprintf('perms:index:%d', $user->getKey());
    }

    /**
     * @param  mixed  $permissions
     * @return list<string>
     */
    protected function normalise(mixed $permissions): array
    {
        if ($permissions instanceof Collection) {
            return $permissions->values()->all();
        }

        if (! is_array($permissions)) {
            return [];
        }

        return array_values(array_unique(array_map('strval', $permissions)));
    }
}
