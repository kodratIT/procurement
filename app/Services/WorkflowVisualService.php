<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Support\Str;

class WorkflowVisualService
{
    /**
     * Build visual data for a workflow's active version (or latest).
     *
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function build(Workflow $workflow): array
    {
        $version = $workflow->activeVersion() ?? $workflow->versions()->latest('version_number')->first();
        $steps = $version?->steps()->with('conditions')->orderBy('sequence')->get() ?? collect();

        $nodes = [];
        $edges = [];

        // Trigger node (always present) - friendly Indonesian
        $nodes[] = [
            'id' => 'trigger',
            'type' => 'trigger',
            'label' => 'PENGAJUAN DIBUAT',
            'subtitle' => 'PR diajukan, memulai alur persetujuan',
            'icon' => '⚡',
            'color' => 'amber',
        ];

        $prevId = 'trigger';
        $prevHandle = 'output';

        foreach ($steps as $step) {
            /** @var WorkflowStep $step */
            $conditions = $step->conditions;

            // If step has conditions, insert a condition node before the action
            if ($conditions->isNotEmpty()) {
                $condId = 'cond_'.$step->id;
                $condLabel = $this->conditionLabel($step);
                $nodes[] = [
                    'id' => $condId,
                    'type' => 'condition',
                    'label' => $condLabel['title'],
                    'subtitle' => $condLabel['subtitle'],
                    'icon' => '◇',
                    'color' => 'violet',
                    'handles' => ['true', 'false'],
                ];

                // Connect previous to condition
                $edges[] = [
                    'id' => 'e_'.$prevId.'_'.$condId,
                    'source' => $prevId,
                    'target' => $condId,
                    'sourceHandle' => $prevHandle,
                    'label' => null,
                ];

                // Condition true -> action
                $actionId = 'step_'.$step->id;
                $nodes[] = $this->actionNode($step);

                $edges[] = [
                    'id' => 'e_'.$condId.'_'.$actionId.'_true',
                    'source' => $condId,
                    'target' => $actionId,
                    'sourceHandle' => 'true',
                    'label' => 'TRUE',
                    'color' => 'emerald',
                ];

                // Condition false -> skip to next (will be handled by connecting to next node's condition or action)
                // We need to remember that false branch bypasses this action
                // For linear visualization, we connect false directly to next step's entry point
                // To keep it simple, we create a bypass edge that will be connected to next iteration's prev
                // Store false branch target for next loop
                $prevId = $actionId;
                $prevHandle = 'output';

                // Also create a phantom edge for false path - we store it to be connected to next node
                // Instead of immediate, we will handle false edge when we know next node id
                // For now, push a pending false edge marker
                // We handle by adding an edge from cond false to next step in next iteration
                // Simplify: if next step exists, that edge will be added before next step's true path
                // To achieve, we need to defer: store condId as prevFalseNode
                // For this iteration, we set a separate tracker
                // Easier: next iteration will add edge from previous cond false to its own condition/action
                // But we need to know previous cond id
                // Let's store it in a variable and handle at start of next loop
                // Quick hack: add a pending false edge that points to a placeholder, will be replaced
                // Instead, we will directly add edge from cond false to next step when next step is created
                // So we need to keep $pendingFalse = $condId
                // We use a static variable via payload
                $nodes[count($nodes) - 2]['pendingFalse'] = $condId; // mark condition node
                // We will handle false connection in next iteration's preamble
                // For now, we need to keep reference: store in auxiliary array
                // We'll use a simple approach: keep $lastConditionFalseId
                $this->pendingFalseId = $condId;

                continue;
            }

            // No condition: direct action node
            $actionId = 'step_'.$step->id;
            $nodes[] = $this->actionNode($step);

            // If previous was a condition with pending false, connect its false to this action as well (bypass)
            if (isset($this->pendingFalseId)) {
                $edges[] = [
                    'id' => 'e_'.$this->pendingFalseId.'_'.$actionId.'_false',
                    'source' => $this->pendingFalseId,
                    'target' => $actionId,
                    'sourceHandle' => 'false',
                    'label' => 'FALSE',
                    'color' => 'rose',
                    'style' => 'dashed',
                ];
                unset($this->pendingFalseId);
            }

            $edges[] = [
                'id' => 'e_'.$prevId.'_'.$actionId,
                'source' => $prevId,
                'target' => $actionId,
                'sourceHandle' => $prevHandle,
                'label' => null,
            ];

            $prevId = $actionId;
            $prevHandle = 'output';
        }

        // If last condition had pending false without following step, close it to end
        if (isset($this->pendingFalseId)) {
            // create an end node for false path
            $nodes[] = [
                'id' => 'end_skip',
                'type' => 'end',
                'label' => 'END',
                'subtitle' => 'Skipped',
                'icon' => '○',
                'color' => 'gray',
            ];
            $edges[] = [
                'id' => 'e_'.$this->pendingFalseId.'_end_skip_false',
                'source' => $this->pendingFalseId,
                'target' => 'end_skip',
                'sourceHandle' => 'false',
                'label' => 'FALSE',
                'color' => 'rose',
            ];
            unset($this->pendingFalseId);
        }

        // If no steps, show empty state handling outside
        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'meta' => [
                'workflow_id' => $workflow->id,
                'workflow_code' => $workflow->code,
                'version' => $version?->version_number,
                'version_status' => $version?->status->value ?? null,
                'steps_count' => $steps->count(),
                'has_conditions' => $steps->flatMap(fn ($s) => $s->conditions)->isNotEmpty(),
            ],
        ];
    }

    private ?string $pendingFalseId = null;

    /**
     * @return array{title: string, subtitle: string}
     */
    private function conditionLabel(WorkflowStep $step): array
    {
        $conds = $step->conditions;
        if ($conds->isEmpty()) {
            return ['title' => 'PERLU PERSETUJUAN?', 'subtitle' => 'Cek apakah tahap ini diperlukan'];
        }

        // Human friendly: "Jika total belanja ≥ Rp 5.000.000"
        $parts = $conds->map(function ($c): string {
            $op = $c->operator->value ?? (string) $c->operator;
            $field = $this->humanizeField($c->field_key);
            $rawVal = is_array($c->value) ? ($c->value[0] ?? $c->value) : $c->value;
            // Handle array value for between/in
            if (is_array($c->value) && $op === 'between' && count($c->value) === 2) {
                $v1 = $this->formatValue($field, $c->value[0]);
                $v2 = $this->formatValue($field, $c->value[1]);

                return "{$field} antara {$v1} – {$v2}";
            }
            $val = $this->formatValue($field, $rawVal);
            $opText = match ($op) {
                'equals' => 'adalah',
                'not_equals' => 'bukan',
                'gte' => '≥',
                'lte' => '≤',
                'between' => 'antara',
                'in' => 'salah satu dari',
                default => $op,
            };

            return trim("{$field} {$opText} {$val}");
        })->implode(' dan ');

        $title = Str::limit($parts, 32, '...');

        return [
            'title' => $title ?: 'CEK SYARAT',
            'subtitle' => 'Hanya jika '.Str::limit($parts, 40),
        ];
    }

    private function humanizeField(string $fieldKey): string
    {
        return match ($fieldKey) {
            'total_amount' => 'Total belanja',
            'amount' => 'Nilai pengajuan',
            'category_id' => 'Kategori',
            'priority' => 'Prioritas',
            'department_id' => 'Departemen',
            default => str_replace('_', ' ', $fieldKey),
        };
    }

    private function formatValue(string $field, mixed $value): string
    {
        if (in_array($field, ['Total belanja', 'Nilai pengajuan'], true) && is_numeric($value)) {
            return 'Rp '.number_format((float) $value, 0, ',', '.');
        }
        if (is_array($value)) {
            return implode(', ', array_map(fn ($v) => (string) $v, $value));
        }

        return (string) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function actionNode(WorkflowStep $step): array
    {
        $typeLabel = $step->step_type->value ?? (string) $step->step_type;
        $typeColor = match ($typeLabel) {
            'approval' => 'blue',
            'review' => 'sky',
            'informational' => 'emerald',
            default => 'blue',
        };

        $task = $this->humanizeTask($step);
        $actor = $this->humanizeResolver($step->resolver_type);
        $time = $this->humanizeSla($step->sla_minutes);
        $nature = $step->is_required ? 'Wajib' : 'Opsional';

        return [
            'id' => 'step_'.$step->id,
            'type' => 'action',
            'label' => $step->name,
            'subtitle' => $task,
            'icon' => $step->is_required ? '✓' : '○',
            'color' => $typeColor,
            'meta' => [
                'sequence' => $step->sequence,
                'type' => $this->humanizeStepType($typeLabel),
                'mode' => $this->humanizeMode($step->approval_mode->value ?? ''),
                'resolver' => $actor,
                'required' => $nature,
                'sla' => $time,
                'task' => $task,
                'actor' => $actor,
            ],
        ];
    }

    private function humanizeTask(WorkflowStep $step): string
    {
        $name = strtolower($step->name);
        $type = $step->step_type->value ?? (string) $step->step_type;

        // Based on name keywords
        if (str_contains($name, 'kepala divisi')) {
            return 'Memeriksa dan menyetujui dari sisi operasional';
        }
        if (str_contains($name, 'purchasing')) {
            return 'Memproses pembelian & menghubungi vendor';
        }
        if (str_contains($name, 'keuangan') || str_contains($name, 'finance') || str_contains($name, 'manager')) {
            return 'Menyetujui dari sisi anggaran & keuangan';
        }
        if (str_contains($name, 'procurement review')) {
            return 'Memeriksa kelengkapan berkas pengajuan';
        }

        return match ($type) {
            'review' => 'Memeriksa kelengkapan pengajuan',
            'approval' => 'Memberikan persetujuan',
            'informational' => 'Menerima informasi untuk ditindaklanjuti',
            'final_approval' => 'Persetujuan akhir',
            default => 'Memproses tahap ini',
        };
    }

    private function humanizeResolver(?string $resolver): string
    {
        return match ($resolver) {
            'role_in_request_office' => 'Atasan di kantor pemohon',
            'role_in_budget_owner_office' => 'Atasan di kantor pemilik anggaran',
            'specific_user' => 'Pengguna yang ditunjuk',
            'department_head' => 'Kepala departemen',
            'branch_head' => 'Kepala cabang',
            'cost_center_owner' => 'Penanggung jawab cost center',
            'nominal_role' => 'Peran terkait',
            default => 'Tim terkait',
        };
    }

    private function humanizeStepType(string $type): string
    {
        return match ($type) {
            'review' => 'Pemeriksaan',
            'approval' => 'Persetujuan',
            'informational' => 'Informasi',
            'conditional' => 'Kondisional',
            'final_approval' => 'Persetujuan Akhir',
            default => ucfirst($type),
        };
    }

    private function humanizeMode(string $mode): string
    {
        return match ($mode) {
            'sequential' => 'Berurutan',
            'parallel_all' => 'Paralel (semua setuju)',
            'parallel_any' => 'Paralel (salah satu)',
            default => $mode ?: 'Berurutan',
        };
    }

    private function humanizeSla(?int $minutes): string
    {
        if ($minutes === null || $minutes === 0) {
            return 'Tanpa batas waktu';
        }
        if ($minutes < 60) {
            return "{$minutes} menit";
        }
        if ($minutes < 1440) {
            $h = (int) ceil($minutes / 60);

            return "{$h} jam";
        }
        $d = (int) ceil($minutes / 1440);

        return "{$d} hari";
    }
}
