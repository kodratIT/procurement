<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Packstub\Flow\Models\Workflow;

class FlowAutomationSeeder extends Seeder
{
    public function run(): void
    {
        config(['activitylog.enabled' => false]);

        // Clean previous demo workflows (hybrid)
        Workflow::query()->whereIn('name', [
            'PR Submit - Notify Approver (Hybrid Demo)',
            'PR Submitted - Notify Next Approver',
            'Quotation Submitted - Notify Requester',
            'Daily Overdue PR Reminder (Schedule)',
        ])->delete();

        $this->createPrSubmittedWorkflow();
        $this->createQuotationWorkflow();
        $this->createOverdueScheduleWorkflow();

        config(['activitylog.enabled' => true]);
    }

    private function createPrSubmittedWorkflow(): void
    {
        $definition = [
            'nodes' => [
                [
                    'id' => 'trigger_pr_submitted',
                    'type' => 'trigger',
                    'position' => ['x' => 50, 'y' => 100],
                    'data' => [
                        'identifier' => 'Packstub\Flow\Nodes\Triggers\RecordUpdated',
                        'label' => 'PR Submitted',
                        'config' => [
                            'model_class' => 'App\Models\PurchaseRequest',
                            'watch' => ['status'],
                            // submitted adalah status setelah ProcurementRequestSubmitter::submit()
                            // draft -> submitted (atau draft -> step_key untuk dynamic)
                            // Kosongkan from agar fire untuk setiap perubahan ke submitted
                            'from' => '',
                            'to' => 'submitted',
                            'once' => false,
                        ],
                    ],
                ],
                [
                    'id' => 'action_notify_approver',
                    'type' => 'action',
                    'position' => ['x' => 450, 'y' => 100],
                    'data' => [
                        'identifier' => 'App\Flow\Nodes\Actions\NotifyNextApproverAction',
                        'label' => 'Notify Next Approver',
                        'config' => [
                            'title' => 'PR {{ model.pr_number }} menunggu approval',
                            'body' => 'PR {{ model.pr_number }} ({{ model.title }}) sebesar Rp {{ model.total_amount }} perlu approval Anda. Requester: {{ model.requester.name }}',
                        ],
                    ],
                ],
                [
                    'id' => 'action_slack',
                    'type' => 'action',
                    'position' => ['x' => 450, 'y' => 250],
                    'data' => [
                        'identifier' => 'Packstub\Flow\Nodes\Actions\SendNotification',
                        'label' => 'Notify Procurement',
                        'config' => [
                            'title' => 'PR Baru: {{ model.pr_number }}',
                            'body' => 'PR {{ model.pr_number }} telah disubmit dan menunggu approver {{ last.notified_user_id }}',
                            'status' => 'info',
                            'recipients' => '{{ model.requester.email }}',
                            'action_label' => 'Buka PR',
                            'action_url' => '{{ model.url }}',
                        ],
                    ],
                ],
            ],
            'edges' => [
                [
                    'id' => 'e1',
                    'source' => 'trigger_pr_submitted',
                    'target' => 'action_notify_approver',
                    'sourceHandle' => 'output',
                    'targetHandle' => null,
                ],
                [
                    'id' => 'e2',
                    'source' => 'action_notify_approver',
                    'target' => 'action_slack',
                    'sourceHandle' => 'output',
                    'targetHandle' => null,
                ],
            ],
        ];

        Workflow::create([
            'name' => 'PR Submitted - Notify Next Approver',
            'description' => 'Hybrid: saat PR status draft→submitted, resolve approver via WorkflowResolver dan kirim notifikasi database. Chain ke notifikasi kedua.',
            'definition' => $definition,
            'is_active' => true,
        ]);
    }

    private function createQuotationWorkflow(): void
    {
        $definition = [
            'nodes' => [
                [
                    'id' => 'trigger_quotation',
                    'type' => 'trigger',
                    'position' => ['x' => 50, 'y' => 100],
                    'data' => [
                        'identifier' => 'Packstub\Flow\Nodes\Triggers\RecordUpdated',
                        'label' => 'Quotation Submitted',
                        'config' => [
                            'model_class' => 'App\Models\Quotation',
                            'watch' => ['status'],
                            'from' => 'draft',
                            'to' => 'submitted',
                        ],
                    ],
                ],
                [
                    'id' => 'condition_amount',
                    'type' => 'condition',
                    'position' => ['x' => 350, 'y' => 100],
                    'data' => [
                        'identifier' => 'Packstub\Flow\Nodes\Conditions\CompareValues',
                        'label' => 'Total > 10jt?',
                        'config' => [
                            'left' => '{{ model.total_amount }}',
                            'operator' => 'gte',
                            'right' => '10000000',
                        ],
                    ],
                ],
                [
                    'id' => 'action_email_high',
                    'type' => 'action',
                    'position' => ['x' => 650, 'y' => 50],
                    'data' => [
                        'identifier' => 'Packstub\Flow\Nodes\Actions\SendEmail',
                        'label' => 'Email High Value',
                        'config' => [
                            'recipient' => '{{ model.purchaseRequest.requester.email }}',
                            'subject' => 'Quotation {{ model.quotation_number }} high value perlu review',
                            'body' => "Quotation {{ model.quotation_number }} untuk PR {{ model.purchaseRequest.pr_number }} sebesar {{ model.total_amount }} dari vendor {{ model.vendor.name }} telah disubmit.\n\nSilakan review di {{ model.url }}",
                            'action_label' => 'Buka Quotation',
                            'action_url' => '{{ model.url }}',
                        ],
                    ],
                ],
                [
                    'id' => 'action_notif_normal',
                    'type' => 'action',
                    'position' => ['x' => 650, 'y' => 180],
                    'data' => [
                        'identifier' => 'Packstub\Flow\Nodes\Actions\SendNotification',
                        'label' => 'Notify Normal',
                        'config' => [
                            'title' => 'Quotation {{ model.quotation_number }} submitted',
                            'body' => 'Vendor {{ model.vendor.name }} mengajukan penawaran Rp {{ model.total_amount }} untuk PR {{ model.purchaseRequest.pr_number }}',
                            'status' => 'info',
                            'recipients' => '{{ model.purchaseRequest.requester.email }}',
                            'action_label' => 'Lihat',
                            'action_url' => '{{ model.url }}',
                        ],
                    ],
                ],
            ],
            'edges' => [
                [
                    'id' => 'e1',
                    'source' => 'trigger_quotation',
                    'target' => 'condition_amount',
                    'sourceHandle' => 'output',
                    'targetHandle' => null,
                ],
                [
                    'id' => 'e2',
                    'source' => 'condition_amount',
                    'target' => 'action_email_high',
                    'sourceHandle' => 'true',
                    'targetHandle' => null,
                ],
                [
                    'id' => 'e3',
                    'source' => 'condition_amount',
                    'target' => 'action_notif_normal',
                    'sourceHandle' => 'false',
                    'targetHandle' => null,
                ],
            ],
        ];

        Workflow::create([
            'name' => 'Quotation Submitted - Notify Requester',
            'description' => 'Hybrid: saat quotation draft→submitted, branch by total_amount: >10jt kirim email, else notifikasi DB.',
            'definition' => $definition,
            'is_active' => true,
        ]);
    }

    private function createOverdueScheduleWorkflow(): void
    {
        $definition = [
            'nodes' => [
                [
                    'id' => 'trigger_schedule',
                    'type' => 'trigger',
                    'position' => ['x' => 50, 'y' => 100],
                    'data' => [
                        'identifier' => 'Packstub\Flow\Nodes\Triggers\Schedule',
                        'label' => 'Daily 09:00',
                        'config' => [
                            'expression' => '0 9 * * *',
                            'timezone' => 'Asia/Jakarta',
                        ],
                    ],
                ],
                [
                    'id' => 'action_find',
                    'type' => 'action',
                    'position' => ['x' => 350, 'y' => 100],
                    'data' => [
                        'identifier' => 'Packstub\Flow\Nodes\Actions\FindRecords',
                        'label' => 'Find Overdue PRs',
                        'config' => [
                            'model_class' => 'App\Models\PurchaseRequest',
                            'conditions' => [
                                ['attribute' => 'status', 'operator' => '=', 'value' => 'submitted'],
                            ],
                            'order_by' => 'required_date',
                            'direction' => 'asc',
                            'limit' => 50,
                        ],
                    ],
                ],
                [
                    'id' => 'action_loop',
                    'type' => 'action',
                    'position' => ['x' => 650, 'y' => 100],
                    'data' => [
                        'identifier' => 'Packstub\Flow\Nodes\Actions\ForEachLoop',
                        'label' => 'For Each PR',
                        'config' => [
                            'items' => '{{ last.records }}',
                            'item_key' => 'item',
                            'max_iterations' => 50,
                        ],
                    ],
                ],
                [
                    'id' => 'action_reminder',
                    'type' => 'action',
                    'position' => ['x' => 950, 'y' => 50],
                    'data' => [
                        'identifier' => 'Packstub\Flow\Nodes\Actions\SendNotification',
                        'label' => 'Send Reminder',
                        'config' => [
                            'title' => 'PR Overdue: {{ item.pr_number }}',
                            'body' => 'PR {{ item.pr_number }} ({{ item.title }}) status {{ item.status }} sudah lewat required_date {{ item.required_date }}. Total Rp {{ item.total_amount }}',
                            'status' => 'warning',
                            'recipients' => '{{ item.requester.email }}',
                            'action_label' => 'Buka PR',
                            'action_url' => '{{ item.url }}',
                        ],
                    ],
                ],
                [
                    'id' => 'action_log',
                    'type' => 'action',
                    'position' => ['x' => 950, 'y' => 220],
                    'data' => [
                        'identifier' => 'Packstub\Flow\Nodes\Actions\WriteLog',
                        'label' => 'Log Done',
                        'config' => [
                            'level' => 'info',
                            'message' => 'Overdue reminder checked {{ last.count }} PRs at {{ now }}',
                        ],
                    ],
                ],
            ],
            'edges' => [
                [
                    'id' => 'e1',
                    'source' => 'trigger_schedule',
                    'target' => 'action_find',
                    'sourceHandle' => 'output',
                    'targetHandle' => null,
                ],
                [
                    'id' => 'e2',
                    'source' => 'action_find',
                    'target' => 'action_loop',
                    'sourceHandle' => 'output',
                    'targetHandle' => null,
                ],
                [
                    'id' => 'e3',
                    'source' => 'action_loop',
                    'target' => 'action_reminder',
                    'sourceHandle' => 'body',
                    'targetHandle' => null,
                ],
                [
                    'id' => 'e4',
                    'source' => 'action_loop',
                    'target' => 'action_log',
                    'sourceHandle' => 'done',
                    'targetHandle' => null,
                ],
            ],
        ];

        Workflow::create([
            'name' => 'Daily Overdue PR Reminder (Schedule)',
            'description' => 'Schedule tiap hari 09:00 Asia/Jakarta: Find PR submitted + loop kirim reminder. Demo queue & scheduling.',
            'definition' => $definition,
            'is_active' => true,
        ]);
    }
}
