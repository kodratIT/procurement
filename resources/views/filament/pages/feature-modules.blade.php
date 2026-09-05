<x-filament-panels::page>
    <div class="grid gap-4">
        <div class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-gray-700 dark:bg-gray-900">
            <div>
                <p class="text-sm font-semibold text-gray-950 dark:text-white">Kontrol akses modul</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Aktifkan atau nonaktifkan fitur tanpa menghapus data atau permission role.
            </p>
            </div>
        <span class="inline-flex w-fit items-center gap-2 rounded-full bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
            Perubahan tersimpan otomatis
        </span>
    </div>

        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($this->sections() as $section)
                <section
                    wire:key="feature-section-{{ $section['key'] }}"
                    class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
                >
                    <div class="flex items-start justify-between gap-4 border-b border-gray-200 p-5 dark:border-gray-700">
                        <div>
                            <h3 class="font-semibold text-gray-950 dark:text-white">{{ $section['label'] }}</h3>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $section['feature_count'] }} fitur
                            </p>
                        </div>

                        @if ($section['enabled'])
                            <button
                                type="button"
                                wire:click="toggleSection('{{ $section['key'] }}')"
                                wire:confirm="Nonaktifkan section {{ $section['label'] }}? {{ $section['feature_count'] }} fitur akan ikut nonaktif secara efektif."
                                aria-pressed="true"
                                class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-emerald-100 px-3 py-2 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-300 dark:hover:bg-emerald-900/60"
                            >
                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                Aktif
                            </button>
                        @else
                            <button
                                type="button"
                                wire:click="toggleSection('{{ $section['key'] }}')"
                                aria-pressed="false"
                                class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-600 transition hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                            >
                                <span class="h-2 w-2 rounded-full bg-gray-400"></span>
                                Nonaktif
                            </button>
                        @endif
                    </div>

                    <div class="grid gap-2 p-4">
                        @foreach ($section['features'] as $feature)
                            @php
                                $statusClass = match ($feature['status']) {
                                    'Aktif' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
                                    'Nonaktif' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                                    default => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                                };
                            @endphp

                            <div
                                wire:key="feature-{{ $feature['key'] }}"
                                class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 px-3 py-3 dark:border-gray-700"
                            >
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $feature['label'] }}</div>
                                    <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium {{ $statusClass }}">
                                        {{ $feature['status'] }}
                                    </span>
                                </div>

                                @if ($section['enabled'])
                                    @if ($feature['enabled'])
                                        <button
                                            type="button"
                                            wire:click="toggleFeature('{{ $feature['key'] }}')"
                                            wire:confirm="Nonaktifkan fitur {{ $feature['label'] }}?"
                                            aria-pressed="true"
                                            class="relative inline-flex h-6 w-11 shrink-0 rounded-full bg-emerald-500 transition hover:bg-emerald-600"
                                        >
                                            <span class="absolute right-1 top-1 h-4 w-4 rounded-full bg-white shadow"></span>
                                            <span class="sr-only">Nonaktifkan {{ $feature['label'] }}</span>
                                        </button>
                                    @else
                                        <button
                                            type="button"
                                            wire:click="toggleFeature('{{ $feature['key'] }}')"
                                            aria-pressed="false"
                                            class="relative inline-flex h-6 w-11 shrink-0 rounded-full bg-gray-300 transition hover:bg-gray-400 dark:bg-gray-700 dark:hover:bg-gray-600"
                                        >
                                            <span class="absolute left-1 top-1 h-4 w-4 rounded-full bg-white shadow"></span>
                                            <span class="sr-only">Aktifkan {{ $feature['label'] }}</span>
                                        </button>
                                    @endif
                                @else
                                    <button
                                        type="button"
                                        disabled
                                        aria-disabled="true"
                                        class="relative inline-flex h-6 w-11 shrink-0 cursor-not-allowed rounded-full bg-gray-200 opacity-60 dark:bg-gray-700"
                                    >
                                        <span class="absolute left-1 top-1 h-4 w-4 rounded-full bg-white shadow"></span>
                                        <span class="sr-only">{{ $feature['label'] }} terkunci karena section nonaktif</span>
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
