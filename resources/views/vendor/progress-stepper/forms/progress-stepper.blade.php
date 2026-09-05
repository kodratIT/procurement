@php
    $hasInlineLabel = $hasInlineLabel();
    $id = $getId();
    $isDisabled = $isDisabled();
    $isInline = $isInline();
    $isMultiple = $isMultiple();
    $statePath = $getStatePath();

    $visibleOptions = $getVisibleOptions();
    $size = $getSize();
    $direction = $getDirection();
    $theme = $getTheme();
    $separator = $getConnectorShape();
    $isCompact = $isIconOnly();
    $showIndex = $shouldShowIndex();
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
    :has-inline-label="$hasInlineLabel"
>
    <x-slot
        name="label"
        @class([
            'sm:pt-1.5' => $hasInlineLabel,
        ])
    >
        {{ $getLabel() }}
    </x-slot>

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
        {{ $getChildComponentContainer() }}

        @foreach ($visibleOptions as $value => $label)
            @php
                $inputId = "{$id}-{$value}";
                $shouldOptionBeDisabled = $isDisabled || $isOptionDisabled($value, $label);
                $color = $getStepColor((string) $value);
                $status = $getStepStatus((string) $value);
                $stepIcon = $getIcon($value);
                $description = $getStepDescription((string) $value, (string) $label);
                $tooltip = $getStepTooltip((string) $value, (string) $label);
                $badge = $getStepBadge((string) $value, (string) $label);
                $index = $loop->iteration;
            @endphp

            <div
                class="ps-step"
                data-ps-status="{{ $status }}"
                data-ps-color="{{ $color }}"
                @if($tooltip) title="{{ $tooltip }}" @endif
            >
                <input
                    @disabled($shouldOptionBeDisabled)
                    id="{{ $inputId }}"
                    @if (! $isMultiple)
                        name="{{ $id }}"
                    @endif
                    type="{{ $isMultiple ? 'checkbox' : 'radio' }}"
                    value="{{ $value }}"
                    wire:loading.attr="disabled"
                    {{ $applyStateBindingModifiers('wire:model') }}="{{ $statePath }}"
                    {{ $getExtraInputAttributeBag()->class(['ps-input peer pointer-events-none absolute opacity-0']) }}
                />

                <label
                    for="{{ $inputId }}"
                    class="ps-button stage-button"
                    style="{{ $shouldOptionBeDisabled ? 'pointer-events: none;' : '' }}"
                >
                    @if ($stepIcon)
                        <x-filament::icon :icon="$stepIcon" class="w-4 h-4" />
                    @endif

                    <span class="ps-label">
                        @if ($showIndex)
                            <span class="ps-index">{{ $index }}.</span>
                        @endif

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
</x-dynamic-component>
