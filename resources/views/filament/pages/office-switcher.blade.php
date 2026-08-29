<x-filament-panels::page>
    <div class="space-y-6">
        @if ($current = $this->getCurrentOffice())
            <x-filament::section>
                <x-slot name="heading">
                    Kantor aktif saat ini
                </x-slot>
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <div class="text-lg font-semibold">{{ $current->name }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Kode: {{ $current->code }}</div>
                    </div>
                    <x-filament::badge color="success">
                        Aktif
                    </x-filament::badge>
                </div>
            </x-filament::section>
        @endif

        <x-filament::section>
            <x-slot name="heading">
                Kantor yang dapat dipilih
            </x-slot>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($this->getAvailableOffices() as $office)
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <div class="font-semibold">{{ $office->name }}</div>
                        <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $office->code }}
                            @if ($current && $current->id === $office->id)
                                <span class="ml-1">(saat ini)</span>
                            @endif
                        </div>
                        <div class="mt-3">
                            @if ($current && $current->id === $office->id)
                                <x-filament::button color="gray" disabled>
                                    Sedang aktif
                                </x-filament::button>
                            @else
                                <x-filament::button
                                    color="primary"
                                    wire:click="switchOffice({{ $office->id }})"
                                >
                                    Gunakan kantor ini
                                </x-filament::button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="col-span-full text-sm text-gray-500 dark:text-gray-400">
                        Tidak ada kantor yang dapat dipilih. Hubungi administrator untuk penugasan kantor.
                    </div>
                @endforelse
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
