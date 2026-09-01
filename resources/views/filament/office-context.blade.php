@php
    $activeAssignment = $context->assignment();
    $allowedContexts = $context->allowedContexts();
@endphp

@if ($activeAssignment)
    <div class="flex items-center gap-2 text-sm">
        @if ($allowedContexts->count() > 1)
            <form method="POST" action="{{ route('office.switch') }}" class="flex items-center gap-2">
                @csrf
                <label for="active-access-context" class="sr-only">Active access context</label>
                <select id="active-access-context" name="assignment_id" onchange="this.form.submit()" class="fi-select-input rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                    @foreach ($allowedContexts as $candidate)
                        <option value="{{ $candidate->getKey() }}" @selected($candidate->is($activeAssignment))>
                            {{ $candidate->office?->name }}
                            @if ($candidate->branch) · {{ $candidate->branch->name }} @endif
                            @if ($candidate->department) · {{ $candidate->department->name }} @endif
                            @if ($candidate->assignedRole) · {{ $candidate->assignedRole->name }} @endif
                        </option>
                    @endforeach
                </select>
            </form>
        @else
            <span class="font-medium text-gray-700 dark:text-gray-200">{{ $activeAssignment->office?->name }}</span>
        @endif
        <span class="text-gray-500 dark:text-gray-400">
            @if ($activeAssignment->branch) · {{ $activeAssignment->branch->name }} @endif
            @if ($activeAssignment->department) · {{ $activeAssignment->department->name }} @endif
            @if ($activeAssignment->assignedRole) · {{ $activeAssignment->assignedRole->name }} @endif
        </span>
        @if ($context->requiresConfirmation() && ! $context->mutationIsConfirmed())
            <form method="POST" action="{{ route('office.confirm-mutation') }}" onsubmit="return confirm('Confirm mutations in this non-default office context?')">
                @csrf
                <input type="hidden" name="confirmed" value="1">
                <button type="submit" class="rounded-lg border border-amber-500 px-2 py-1 text-xs font-medium text-amber-700 dark:text-amber-300">
                    Confirm mutations
                </button>
            </form>
        @endif
    </div>
@endif
