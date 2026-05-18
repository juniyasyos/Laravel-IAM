<div class="space-y-8 mt-8 mb-8 h-full">
    <section class="overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950">
        <div class="relative isolate px-6 py-8 sm:px-8 sm:py-10">
            <div class="absolute inset-0 -z-10 bg-gradient-to-br from-sky-50 via-white to-emerald-50 dark:from-sky-950/20 dark:via-gray-950 dark:to-emerald-950/20"></div>
            <div class="max-w-3xl">
                <p class="text-sm font-medium uppercase tracking-[0.3em] text-gray-500 dark:text-gray-400">
                    {{ $eyebrow ?? 'Settings' }}
                </p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-gray-950 dark:text-white sm:text-4xl">
                    {{ $pageHeading ?? 'Settings' }}
                </h1>
                <p class="mt-4 max-w-2xl text-sm leading-6 text-gray-600 dark:text-gray-300 sm:text-base">
                    {{ $pageDescription ?? 'Kelola value settings tanpa mengubah key dan struktur dasarnya.' }}
                </p>
            </div>
        </div>
    </section>

    <div class="my-8">
        @if ($hasFields)
            <form wire:submit="save" class="space-y-6">
                {{ $this->form }}

                <div class="flex items-center justify-end">
                    <x-filament::button type="submit" color="primary">
                        {{ $submitLabel ?? 'Save Values' }}
                    </x-filament::button>
                </div>
            </form>
        @endif
    </div>
</div>