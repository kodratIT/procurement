<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <x-filament::section heading="Aplikasi" icon="heroicon-o-cpu-chip">
            <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Status</dt>
                    <dd class="mt-1 flex items-center gap-2">
                        <span class="inline-flex size-2 rounded-full bg-success-500"></span>
                        <span class="font-semibold text-success-700 dark:text-success-500">Sehat</span>
                    </dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Lingkungan</dt>
                    <dd class="mt-1">{{ config('app.env') }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Versi PHP</dt>
                    <dd class="mt-1">{{ PHP_VERSION }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Timezone</dt>
                    <dd class="mt-1">{{ config('app.timezone') }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Koneksi DB</dt>
                    <dd class="mt-1">{{ config('database.default') }}</dd>
                </div>
            </dl>
        </x-filament::section>

        <x-filament::section heading="Endpoint Publik" icon="heroicon-o-globe-alt">
            <ul class="space-y-3 text-sm">
                <li class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 p-3 dark:border-white/10">
                    <div>
                        <p class="font-medium">Laravel Health (<code>/up</code>)</p>
                        <p class="text-gray-500 dark:text-gray-400">Liveness dasar aplikasi</p>
                    </div>
                    <a href="/up" target="_blank" rel="noopener"
                       class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-500">
                        Buka
                    </a>
                </li>
            </ul>
        </x-filament::section>
    </div>
</x-filament-panels::page>
