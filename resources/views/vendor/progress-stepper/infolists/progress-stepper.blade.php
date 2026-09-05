@php
    $id = $getId();
    $isInline = $isInline();
    $visibleOptions = $getVisibleOptions();
    $size = $getSize();
    $direction = $getDirection();
    $theme = $getTheme();
    $separator = $getConnectorShape();
    $isCompact = $isIconOnly();
    $currentState = $getState();

    if ($currentState instanceof \BackedEnum) {
        $currentState = $currentState->value;
    }

    $record = $entry->getRecord();
    $workflowComplete = $record
        ? \App\Filament\Resources\PurchaseRequests\Schemas\PurchaseRequestInfolist::workflowIsComplete($record)
        : false;
    $activityJson = $record
        ? \App\Filament\Resources\PurchaseRequests\Schemas\PurchaseRequestInfolist::timelineEntries($record)
        : [];
    $initialStep = $workflowComplete ? '' : (string) $currentState;
    $initialLabel = $visibleOptions[$initialStep] ?? $visibleOptions[array_key_first($visibleOptions)] ?? '';
@endphp

<x-dynamic-component
    :component="$getEntryWrapperView()"
    :entry="$entry"
>
    <div
        x-data="{
            selectedStep: @js($initialStep),
            selectedLabel: @js($initialLabel),
            activities: @js($activityJson),
            workflowComplete: @js($workflowComplete),
            showModal: false,
            selectStep(value, label) { this.selectedStep = value; this.selectedLabel = label; this.showModal = true; },
            get filteredActivities() {
                if (!this.selectedStep) return this.activities;
                const step = this.selectedStep.toLowerCase();
                const label = this.selectedLabel.toLowerCase();
                let filtered = this.activities.filter(a => a.stage_key === step || a.description.toLowerCase().includes(label) || a.event_raw.includes(step));
                return filtered.length ? filtered : [];
            }
        }"
        class="space-y-3"
    >
        <div
            {{
                \Filament\Support\prepare_inherited_attributes($attributes)
                    ->merge($getExtraAttributes(), escape: false)
                    ->class(['ps-container fi-progress-stepper'])
            }}
            data-ps-direction="{{ $direction }}"
            data-ps-size="{{ $size }}"
            data-ps-theme="{{ $theme }}"
            data-ps-separator="{{ $separator }}"
            data-ps-compact="{{ $isCompact ? 'true' : 'false' }}"
            data-ps-inline="{{ $isInline ? 'true' : 'false' }}"
        >
            @foreach ($visibleOptions as $value => $label)
                @php
                    $inputId = "{$id}-{$value}";
                    $isChecked = ((string) $currentState === (string) $value);
                    $color = $getStepColor((string) $value);
                    $status = $workflowComplete ? 'completed' : $getStepStatus((string) $value);
                    $description = $getStepDescription((string) $value, (string) $label);
                    $tooltip = $getStepTooltip((string) $value, (string) $label);
                    $badge = $getStepBadge((string) $value, (string) $label);
                    $index = $loop->iteration;
                @endphp

                <div
                    class="ps-step cursor-pointer"
                    data-ps-status="{{ $status }}"
                    data-ps-color="{{ $color }}"
                    @if($tooltip) title="{{ $tooltip }}" @endif
                    x-on:click="selectStep('{{ $value }}', '{{ addslashes($label) }}')"
                    :class="selectedStep === '{{ $value }}' ? 'ring-2 ring-primary-500 ring-offset-1 rounded-lg' : ''"
                >
                    <input
                        @if($isChecked) checked @endif
                        id="{{ $inputId }}"
                        name="{{ $id }}"
                        type="radio"
                        value="{{ $value }}"
                        class="ps-input peer pointer-events-none absolute opacity-0"
                    />

                    <label
                        for="{{ $inputId }}"
                        class="ps-button stage-button cursor-pointer"
                    >
                        <span class="ps-status-indicator" aria-hidden="true">
                            @if ($status === 'completed')
                                <x-filament::icon
                                    icon="heroicon-m-check"
                                    class="ps-status-icon"
                                />
                            @elseif ($status === 'error')
                                <x-filament::icon
                                    icon="heroicon-m-x-mark"
                                    class="ps-status-icon"
                                />
                            @else
                                <span class="ps-status-number">{{ str_pad((string) $index, 2, '0', STR_PAD_LEFT) }}</span>
                            @endif
                        </span>

                        <span class="ps-label">
                            @if (! $isCompact)
                                <span class="ps-label-text">
                                    <span>{{ $label }}</span>
                                    @if ($description)
                                        <span class="ps-description">{{ $description }}</span>
                                    @endif
                                </span>
                            @endif

                            @if ($badge !== null)
                                <span class="ps-badge">{{ $badge }}</span>
                            @endif
                        </span>
                    </label>
                </div>
            @endforeach
        </div>

        <!-- Modal rapi — light/dark, hanya timeline step terpilih, tanpa Histori Status -->
        <div
            x-cloak
            x-show="showModal"
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            style="display: none;"
        >
            <div x-on:click="showModal = false" class="absolute inset-0 bg-black/40 dark:bg-black/60 backdrop-blur-sm"></div>
            <div
                x-show="showModal"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="relative bg-white dark:bg-gray-900 rounded-2xl shadow-xl w-full max-w-lg max-h-[80vh] flex flex-col overflow-hidden border border-gray-200 dark:border-gray-700"
                @click.away="showModal = false"
            >
                <div class="flex items-center justify-between gap-4 px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shrink-0">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white" x-text="'Riwayat — ' + selectedLabel"></h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Aktivitas untuk langkah ini</p>
                    </div>
                    <button x-on:click="showModal = false" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-6 flex-1 bg-white dark:bg-gray-900 overflow-hidden flex flex-col min-h-0">
                    <template x-if="filteredActivities.length === 0">
                        <div class="text-center py-10">
                            <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Belum ada aktivitas</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Belum ada catatan khusus untuk tahap <span x-text="selectedLabel"></span>.</div>
                        </div>
                    </template>

                    <template x-if="filteredActivities.length > 0">
                        <div>
                            <div x-show="filteredActivities.length > 4" class="flex items-center justify-between mb-3 text-xs">
                                <span class="text-gray-500 dark:text-gray-400" x-text="filteredActivities.length + ' aktivitas — menampilkan 4 terbaru, scroll untuk lainnya'"></span>
                                <span class="text-primary-600 dark:text-primary-400 font-medium" x-text="filteredActivities.length + ' total'"></span>
                            </div>
                            <div class="pr-history-timeline pr-history-scrollable max-h-[22rem] overflow-y-auto overscroll-contain pr-2 -mr-2">
                            <template x-for="(act, idx) in filteredActivities" :key="idx">
                                <div class="pr-history-item" :class="(idx === filteredActivities.length - 1 && ! act.is_created && ! act.is_terminal && ! act.is_completed ? 'is-current' : 'is-completed') + ' is-' + act.color">
                                    <div class="pr-history-marker-column">
                                        <span class="pr-history-marker">
                                            <template x-if="act.color === 'danger'">
                                                <svg class="pr-history-marker-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg>
                                            </template>
                                            <template x-if="act.color === 'warning'">
                                                <span class="pr-history-marker-symbol">!</span>
                                            </template>
                                            <template x-if="act.color !== 'danger' && act.color !== 'warning' && idx === filteredActivities.length - 1 && ! act.is_created && ! act.is_terminal">
                                                <span class="pr-history-marker-dot"></span>
                                            </template>
                                            <template x-if="act.color !== 'danger' && act.color !== 'warning' && (idx !== filteredActivities.length - 1 || act.is_created || act.is_terminal)">
                                                <svg class="pr-history-marker-check" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            </template>
                                        </span>
                                        <template x-if="idx < filteredActivities.length - 1">
                                            <span class="pr-history-connector"></span>
                                        </template>
                                        </div>
                                        <div class="pr-history-content">
                                            <div class="pr-history-title" x-text="act.event"></div>
                                            <div class="pr-history-description">
                                                <span x-show="act.description" x-text="act.description"></span>
                                            </div>
                                            <div class="pr-history-meta">oleh <span x-text="act.causer"></span> · <span x-text="act.time"></span></div>
                                    </div>
                                </div>
                            </template>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="px-6 py-3 bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center shrink-0">
                    <span class="text-xs text-gray-500 dark:text-gray-400">Lihat semua di Riwayat Perubahan di bawah</span>
                    <button x-on:click="showModal = false" class="px-4 py-1.5 rounded-lg bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 shadow-sm">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</x-dynamic-component>
