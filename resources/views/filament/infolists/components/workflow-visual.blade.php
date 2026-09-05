@php
    use App\Services\WorkflowVisualService;
    $service = app(WorkflowVisualService::class);
    $visual = $service->build($record);
    $nodes = $visual['nodes'];
    $edges = $visual['edges'];
    $meta = $visual['meta'];
@endphp

<div class="col-span-full">
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden bg-white dark:bg-gray-900 shadow-sm">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Visual Workflow</span>
                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300 font-mono">{{ $record->code }}</span>
                </div>
                @if($meta['version'])
                    <span class="text-xs text-gray-500 dark:text-gray-400">v{{ $meta['version'] }} · {{ $meta['version_status'] }} · {{ $meta['steps_count'] }} tahap</span>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <span class="hidden sm:inline-flex text-[10px] leading-none px-2 py-1 rounded bg-amber-100 text-amber-700 border border-amber-200">TRIGGER</span>
                <span class="hidden sm:inline-flex text-[10px] leading-none px-2 py-1 rounded bg-violet-100 text-violet-700 border border-violet-200">DECISION</span>
                <span class="hidden sm:inline-flex text-[10px] leading-none px-2 py-1 rounded bg-blue-100 text-blue-700 border border-blue-200">ACTION</span>
                <button type="button" onclick="document.getElementById('workflow-visual-{{ $record->getKey() }}').requestFullscreen()" class="inline-flex items-center justify-center w-7 h-7 rounded-md border border-gray-200 bg-white text-gray-500 hover:text-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 20v-4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 0h-4"/></svg>
                </button>
            </div>
        </div>

        @if(empty($nodes) || $meta['steps_count'] === 0)
            <div class="p-12 text-center">
                <div class="mx-auto w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 6.5h12A1.5 1.5 0 0119.5 8v8a1.5 1.5 0 01-1.5 1.5H6A1.5 1.5 0 014.5 16V8A1.5 1.5 0 016 6.5z"/></svg>
                </div>
                <p class="text-sm font-medium text-gray-600">Belum ada tahap workflow</p>
                <p class="text-xs text-gray-500 mt-1">Tambahkan versi & tahap di tab Versions / Workflow Stages</p>
            </div>
        @else
            @php
                $worldWidth = max(1100, (count($nodes) * 340) + 120);
                $worldHeight = 620;
            @endphp
            <div
                id="workflow-visual-{{ $record->getKey() }}"
                class="workflow-canvas relative h-[520px] overflow-auto bg-slate-50 select-none dark:bg-gray-950"
                style="cursor: grab; overscroll-behavior: contain;"
                data-workflow-id="{{ $record->getKey() }}"
                data-workflow-version="{{ $meta['version'] ?? 'draft' }}"
                data-world-width="{{ $worldWidth }}"
                data-world-height="{{ $worldHeight }}"
            >
                <div class="absolute inset-0 pointer-events-none" style="background-image: radial-gradient(circle, #e5e7eb 1px, transparent 1px); background-size: 24px 24px; opacity: 0.9;"></div>
                <div class="absolute inset-0 pointer-events-none hidden dark:block" style="background-image: radial-gradient(circle, #374151 1px, transparent 1px); background-size: 24px 24px; opacity: 0.25;"></div>

                <div class="workflow-toolbar sticky left-3 top-3 z-30 inline-flex items-center gap-1 rounded-xl border border-gray-200 bg-white/95 p-1.5 shadow-lg backdrop-blur dark:border-gray-700 dark:bg-gray-900/95">
                    <button type="button" data-workflow-control="zoom-in" aria-label="Perbesar" title="Perbesar" class="flex h-8 w-8 items-center justify-center rounded-lg text-sm font-bold text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800">+</button>
                    <button type="button" data-workflow-control="zoom-out" aria-label="Perkecil" title="Perkecil" class="flex h-8 w-8 items-center justify-center rounded-lg text-sm font-bold text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800">−</button>
                    <button type="button" data-workflow-control="fit" aria-label="Tampilkan seluruh workflow" title="Tampilkan seluruh workflow" class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4h4M4 4l5 5m11-1V4h-4M4 20v-4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 0h-4"/></svg>
                    </button>
                    <span class="mx-0.5 h-5 w-px bg-gray-200 dark:bg-gray-700"></span>
                    <button type="button" data-workflow-control="reset" title="Kembalikan susunan otomatis" class="flex h-8 items-center gap-1.5 rounded-lg px-2.5 text-[11px] font-semibold text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v6h6M20 20v-6h-6M5.5 15a7 7 0 0011.8 2.2M18.5 9A7 7 0 006.7 6.8"/></svg>
                        Rapikan
                    </button>
                </div>
                <div class="sticky left-full top-3 z-20 mr-3 ml-auto w-max rounded-lg border border-gray-200 bg-white/90 px-2.5 py-1.5 text-[11px] text-gray-500 shadow-sm backdrop-blur dark:border-gray-700 dark:bg-gray-900/90 dark:text-gray-400">Tarik kartu untuk memindahkan · tarik area kosong untuk menggeser kanvas</div>

                <div class="workflow-stage relative" style="width: {{ $worldWidth }}px; height: {{ $worldHeight }}px;">
                    <div class="workflow-inner absolute left-0 top-0 origin-top-left" data-scale="1" style="width: {{ $worldWidth }}px; height: {{ $worldHeight }}px; transform: scale(1);">
                        <svg class="workflow-edges pointer-events-none absolute inset-0 h-full w-full overflow-visible" aria-hidden="true">
                            <defs>
                                <marker id="workflow-arrow-gray-{{ $record->getKey() }}" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto"><path d="M0,0 L8,4 L0,8 Z" fill="#94a3b8"/></marker>
                                <marker id="workflow-arrow-emerald-{{ $record->getKey() }}" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto"><path d="M0,0 L8,4 L0,8 Z" fill="#10b981"/></marker>
                                <marker id="workflow-arrow-rose-{{ $record->getKey() }}" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto"><path d="M0,0 L8,4 L0,8 Z" fill="#f43f5e"/></marker>
                            </defs>
                            @foreach($edges as $edge)
                                @php
                                    $edgeColor = ($edge['color'] ?? null) === 'emerald' ? 'emerald' : (($edge['color'] ?? null) === 'rose' ? 'rose' : 'gray');
                                    $strokeColor = match ($edgeColor) {
                                        'emerald' => '#10b981',
                                        'rose' => '#f43f5e',
                                        default => '#94a3b8',
                                    };
                                @endphp
                                <g data-edge-id="{{ $edge['id'] }}" data-source="{{ $edge['source'] }}" data-target="{{ $edge['target'] }}" data-source-handle="{{ $edge['sourceHandle'] ?? 'output' }}">
                                    <path class="workflow-edge-path" fill="none" stroke="{{ $strokeColor }}" stroke-width="2" stroke-linecap="round" @if(($edge['style'] ?? null) === 'dashed') stroke-dasharray="7 6" @endif marker-end="url(#workflow-arrow-{{ $edgeColor }}-{{ $record->getKey() }})"/>
                                    @if($edge['label'] ?? null)
                                        <g class="workflow-edge-label">
                                            <rect width="42" height="20" rx="10" fill="white" stroke="{{ $strokeColor }}" stroke-opacity=".35"/>
                                            <text x="21" y="13.5" text-anchor="middle" fill="{{ $strokeColor }}" style="font-size: 9px; font-weight: 700;">{{ $edge['label'] }}</text>
                                        </g>
                                    @endif
                                </g>
                            @endforeach
                        </svg>

                    @foreach($nodes as $index => $node)
                        @php
                            $isTrigger = $node['type'] === 'trigger';
                            $isCondition = $node['type'] === 'condition';
                            $isAction = $node['type'] === 'action';
                            $isEnd = $node['type'] === 'end';
                            $defaultX = 80 + ($index * 340);
                            $defaultY = $isCondition ? 215 : ($isEnd ? 270 : 245);
                        @endphp
                        <div
                            class="workflow-node absolute touch-none"
                            data-node-id="{{ $node['id'] }}"
                            data-node-type="{{ $node['type'] }}"
                            data-default-x="{{ $defaultX }}"
                            data-default-y="{{ $defaultY }}"
                            style="left: {{ $defaultX }}px; top: {{ $defaultY }}px; cursor: move;"
                        >
                            <div class="relative transition-[filter,box-shadow] duration-150">
                                @if($isTrigger)
                                    <div class="w-[220px] overflow-hidden rounded-xl border border-amber-200 bg-white shadow-md dark:border-amber-700/60 dark:bg-gray-900">
                                        <div class="flex items-center gap-2 bg-amber-500 px-3 py-2 text-amber-950">
                                            <span class="inline-flex items-center justify-center w-5 h-5 rounded bg-white/20 text-[10px]">⚡</span>
                                            <span class="text-[11px] font-bold tracking-wider">TRIGGER</span>
                                        </div>
                                        <div class="px-3.5 py-3">
                                            <div class="text-[12px] font-bold leading-tight text-gray-900 dark:text-white">{{ $node['label'] }}</div>
                                            <div class="mt-1 text-[11px] leading-snug text-gray-500 dark:text-gray-400">{{ $node['subtitle'] }}</div>
                                        </div>
                                    </div>
                                @elseif($isCondition)
                                    <div class="relative flex h-[170px] w-[220px] items-center justify-center">
                                        <div class="absolute left-1/2 top-1/2 h-[124px] w-[124px] -translate-x-1/2 -translate-y-1/2 rotate-45 rounded-2xl border border-violet-700 bg-violet-600 shadow-lg"></div>
                                        <div class="relative z-10 w-[150px] text-center">
                                            <div class="inline-flex items-center gap-1 text-[9px] font-bold tracking-widest text-white bg-violet-700 px-1.5 py-0.5 rounded mb-1.5 shadow-sm">
                                                <span class="w-1.5 h-1.5 bg-white rotate-45 inline-block"></span> DECISION
                                            </div>
                                            <div class="text-xs font-bold leading-tight text-white" style="text-shadow: 0 1px 2px rgba(0,0,0,0.3);">{{ $node['label'] }}</div>
                                            <div class="text-[11px] leading-tight text-white/90 mt-1 line-clamp-2" style="text-shadow: 0 1px 2px rgba(0,0,0,0.2);">{{ $node['subtitle'] }}</div>
                                        </div>
                                    </div>
                                @elseif($isAction)
                                    <div class="w-[250px] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-md dark:border-gray-700 dark:bg-gray-900">
                                        <div class="flex items-center justify-between gap-2 bg-blue-600 px-3 py-2 text-white">
                                            <span class="flex min-w-0 items-center gap-1.5 text-[11px] font-bold tracking-wide">
                                                <span class="inline-flex items-center justify-center w-5 h-5 rounded bg-white/20 text-[10px] shrink-0">≡</span>
                                                <span class="truncate">{{ $node['label'] }}</span>
                                            </span>
                                            @if(($node['meta']['required'] ?? '') === 'Wajib')
                                                <span class="text-[9px] px-1.5 py-0.5 rounded bg-white text-blue-700 font-bold shrink-0 ml-1">WAJIB</span>
                                            @else
                                                <span class="text-[9px] px-1.5 py-0.5 rounded bg-white/20 font-bold shrink-0 ml-1">OPSIONAL</span>
                                            @endif
                                        </div>
                                        <div class="space-y-1.5 px-3.5 py-3">
                                            <div class="text-[12px] font-semibold leading-tight text-gray-900 dark:text-white">{{ $node['label'] }}</div>
                                            <div class="text-[11px] leading-snug text-gray-600 dark:text-gray-300">{{ $node['subtitle'] }}</div>
                                            <div class="text-[10px] leading-tight text-gray-500 dark:text-gray-400">Oleh: {{ $node['meta']['actor'] ?? 'Tim terkait' }}</div>
                                            <div class="flex flex-wrap gap-1.5 pt-1">
                                                <span class="inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded-full {{ ($node['meta']['required'] ?? '') === 'Wajib' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-gray-50 text-gray-500 border-gray-200' }} border">
                                                    <span class="w-1.5 h-1.5 rounded-full {{ ($node['meta']['required'] ?? '') === 'Wajib' ? 'bg-amber-500' : 'bg-gray-400' }}"></span>
                                                    {{ $node['meta']['required'] ?? '' }}
                                                </span>
                                                <span class="inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded-full bg-sky-50 text-sky-700 border border-sky-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                                                    {{ $node['meta']['sla'] ?? '' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                @elseif($isEnd)
                                    <div class="w-[140px] rounded-full border-2 border-dashed border-gray-300 bg-white px-3 py-3 text-center shadow-sm dark:border-gray-600 dark:bg-gray-900">
                                        <div class="text-xs font-bold text-gray-500">{{ $node['label'] }}</div>
                                        <div class="text-[10px] text-gray-400">{{ $node['subtitle'] }}</div>
                                    </div>
                                @endif
                                @if($isCondition)
                                    <div class="absolute -right-1.5 top-[32%] z-10 h-3 w-3 -translate-y-1/2 rounded-full border-2 border-white bg-emerald-500 shadow-sm" title="TRUE"></div>
                                    <div class="absolute -right-1.5 top-[68%] z-10 h-3 w-3 -translate-y-1/2 rounded-full border-2 border-white bg-rose-500 shadow-sm" title="FALSE"></div>
                                @elseif(!$isEnd)
                                    <div class="workflow-handle absolute -right-1.5 top-1/2 z-10 h-3 w-3 -translate-y-1/2 rounded-full border-2 border-white bg-slate-400 shadow-sm"></div>
                                @endif
                                @if($index > 0)
                                    <div class="workflow-handle absolute -left-1.5 top-1/2 z-10 h-3 w-3 -translate-y-1/2 rounded-full border-2 border-white bg-slate-400 shadow-sm"></div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    </div>
                </div>
            </div>
            <div class="px-4 py-2.5 bg-gray-50 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2 text-[11px] text-gray-500">
                <div class="flex items-center gap-3">
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-500 border border-amber-600"></span> Trigger</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rotate-45 bg-violet-600 border border-violet-700 inline-block" style="width:10px;height:10px;"></span> Decision</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded bg-blue-600 border border-blue-700"></span> Action</span>
                </div>
                <span class="font-mono text-xs">{{ $meta['steps_count'] }} langkah · v{{ $meta['version'] ?? '—' }}</span>
            </div>
        @endif
    </div>
    @if($meta['steps_count'] > 0)
        <div class="mt-4 rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                <h4 class="text-sm font-semibold text-gray-700">Detail Tahapan</h4>
                <a href="/admin/workflow-versions" class="text-xs font-medium text-primary-600 hover:underline">Kelola Versi →</a>
            </div>
            <div class="divide-y divide-gray-200">
                @php $version = $record->activeVersion() ?? $record->versions()->latest('version_number')->first(); $steps = $version?->steps()->with('conditions')->orderBy('sequence')->get() ?? collect(); @endphp
                @foreach($steps as $step)
                    <div class="px-4 py-3 flex items-start gap-3 hover:bg-gray-50">
                        <div class="shrink-0 w-7 h-7 rounded-full {{ $step->is_required ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-600' }} flex items-center justify-center text-xs font-bold">{{ $step->sequence }}</div>
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-medium text-gray-900">{{ $step->name }}</span>
                                <span class="text-[11px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 border border-gray-200">{{ $step->step_type->value }}</span>
                                <span class="text-[11px] px-1.5 py-0.5 rounded {{ $step->is_required ? 'bg-amber-100 text-amber-700 border-amber-200' : 'bg-gray-100 text-gray-600 border-gray-200' }} border">{{ $step->is_required ? 'Wajib' : 'Opsional' }}</span>
                                @if($step->sla_minutes)
                                    <span class="text-[11px] px-1.5 py-0.5 rounded bg-sky-100 text-sky-700 border border-sky-200">{{ $step->sla_minutes }} menit SLA</span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-500 mt-1">Resolver: <span class="font-mono text-gray-600">{{ $step->resolver_type ?: '—' }}</span> · Mode: {{ $step->approval_mode->value }}</div>
                            @if($step->conditions->isNotEmpty())
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach($step->conditions as $cond)
                                        <span class="inline-flex items-center gap-1 text-[11px] px-2 py-1 rounded-full bg-violet-50 text-violet-700 border border-violet-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-violet-500"></span>
                                            {{ strtoupper($cond->field_key) }} {{ $cond->operator->value }} {{ is_array($cond->value) ? implode(', ', $cond->value) : $cond->value }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
