<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <x-filament::section heading="Aplikasi" icon="heroicon-o-cpu-chip">
            <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div><dt>Status</dt><dd>Sehat</dd></div>
                <div><dt>Lingkungan</dt><dd>{{ config('app.env') }}</dd></div>
                <div><dt>Versi PHP</dt><dd>{{ PHP_VERSION }}</dd></div>
                <div><dt>Timezone</dt><dd>{{ config('app.timezone') }}</dd></div>
                <div><dt>Koneksi DB</dt><dd>{{ config('database.default') }}</dd></div>
            </dl>
        </x-filament::section>
        <x-filament::section heading="Endpoint Publik" icon="heroicon-o-globe-alt">
            <p class="text-sm">Laravel Health (<code>/up</code>) — liveness dasar aplikasi.</p>
        </x-filament::section>
    </div>
</x-filament-panels::page>