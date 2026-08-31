<div class="space-y-4">
    <div>
        <h2 class="text-lg font-semibold">{{ $preview['workflow']['name'] }}</h2>
        <p class="text-sm text-gray-600">
            {{ $preview['workflow']['code'] }} · versi {{ $preview['workflow']['version'] }}
        </p>
    </div>

    <dl class="grid grid-cols-2 gap-3 text-sm">
        <div>
            <dt class="font-medium">Kantor sumber</dt>
            <dd>{{ $preview['source']['office_name'] ?? '-' }}</dd>
        </div>
        <div>
            <dt class="font-medium">Cabang sumber</dt>
            <dd>{{ $preview['source']['branch_name'] ?? '-' }}</dd>
        </div>
        <div>
            <dt class="font-medium">Pemilik budget</dt>
            <dd>{{ $preview['budget_owner']['office_name'] ?? '-' }}</dd>
        </div>
        <div>
            <dt class="font-medium">Status handoff</dt>
            <dd>{{ $preview['can_handoff'] ? 'Siap' : 'Terblokir konfigurasi' }}</dd>
        </div>
    </dl>

    @if ($preview['errors'] !== [])
        <div class="rounded border border-danger-300 p-3 text-sm text-danger-700">
            <strong>Perbaiki konfigurasi approver:</strong>
            <ul class="list-disc pl-5">
                @foreach ($preview['errors'] as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <ol class="space-y-2 text-sm">
        @foreach ($preview['steps'] as $step)
            <li class="rounded border p-3">
                <div class="font-medium">
                    {{ $step['step_order'] }}. {{ $step['label'] }}
                    @if (($step['status'] ?? null) === 'skipped')
                        <span class="text-gray-500">(dilewati)</span>
                    @elseif (($step['status'] ?? null) === 'unresolved')
                        <span class="text-danger-600">(approver belum ditemukan)</span>
                    @endif
                </div>
                <div>
                    Approver: {{ $step['approver_name'] ?? '-' }}
                    @if ($step['approver_role'] ?? null)
                        · {{ $step['approver_role'] }}
                    @endif
                </div>
                <div class="text-gray-600">
                    Resolver: {{ $step['resolver_type'] }} · scope: {{ $step['scope_source'] ?? '-' }}
                </div>
                @if (($step['conditions'] ?? []) !== [])
                    <div class="text-gray-600">Kondisi: {{ json_encode($step['conditions'], JSON_THROW_ON_ERROR) }}</div>
                @endif
            </li>
        @endforeach
    </ol>
</div>
