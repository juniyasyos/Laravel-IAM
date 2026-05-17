<?php

use App\Services\SettingService;

if (! function_exists('setting')) {
	function setting(string $key, mixed $default = null): mixed
	{
		return app(SettingService::class)->get($key, $default);
	}
}

if (! function_exists('setting_definition')) {
	function setting_definition(string $key): ?array
	{
		return config("settings.definitions.{$key}");
	}
}

if (! function_exists('setting_map')) {
	function setting_map(): array
	{
		return app(SettingService::class)->mapping();
	}
}

