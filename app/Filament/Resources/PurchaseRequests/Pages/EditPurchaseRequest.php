<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseRequests\Pages;

use App\Filament\Resources\PurchaseRequestResource;
use App\Models\PurchaseRequest;
use App\Services\ProcurementRequestDraftSaver;
use App\Services\ProcurementRequestSubmitter;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditPurchaseRequest extends EditRecord
{
    protected static string $resource = PurchaseRequestResource::class;

    public function hasDatabaseTransactions(): bool
    {
        return false;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Purchase request berhasil diperbarui';
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (PurchaseRequest $record): bool => $record->status === PurchaseRequest::STATUS_DRAFT),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord()->loadMissing([
            'requester',
            'office',
            'branch',
            'department',
            'items',
            'attachments',
            'fieldValues',
        ]);

        // These fields are display-only in the form, so Filament cannot hydrate
        // them from model attributes automatically. Fill them explicitly from
        // the request snapshot so Edit mirrors the Create context.
        $data['requester_display'] = $record->requester?->name;
        $data['office_display'] = $record->office?->name;
        $data['branch_display'] = $record->branch?->name;
        $data['department_display'] = $record->department?->name;

        $data['items'] = $record->items
            ->map(static fn ($item): array => [
                'procurement_item_id' => $item->procurement_item_id,
                'procurement_unit_id' => $item->procurement_unit_id,
                'procurement_variant_id' => $item->procurement_variant_id,
                'item_name' => $item->item_name,
                'description' => $item->description,
                'unit_name' => $item->unit_name,
                'variant_name' => $item->variant_name,
                'variant_value' => $item->variant_value,
                'specifications' => $item->specifications,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'notes' => $item->notes,
            ])
            ->values()
            ->all();

        // Existing paths are display state only. DraftSaver accepts only new
        // UploadedFile instances, so saving without a new upload preserves the
        // current attachments while still showing them in the edit form.
        $data['attachments'] = $record->attachments
            ->pluck('path')
            ->values()
            ->all();

        $data['fields'] = $record->fieldValues
            ->mapWithKeys(fn ($value): array => [$value->field_key => $value->value])
            ->all();

        // ponytail: default pilihan aksi ke draft agar edit tidak auto-submit
        $data['submit_action'] ??= 'draft';

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof PurchaseRequest) {
            abort(404);
        }

        $action = $data['submit_action'] ?? 'draft';
        unset($data['submit_action']);

        $record = app(ProcurementRequestDraftSaver::class)->save($data, $record);

        if ($action === 'submit') {
            try {
                $record = app(ProcurementRequestSubmitter::class)->submit($record);

                Notification::make()
                    ->title('Purchase request diajukan')
                    ->body('Perbaikan berhasil diajukan kembali ke Pengadaan → Keuangan.')
                    ->success()
                    ->send();
            } catch (ValidationException $exception) {
                Notification::make()
                    ->title('Validasi submit gagal')
                    ->body(collect($exception->errors())->flatten()->implode(' '))
                    ->danger()
                    ->send();

                throw $exception;
            } catch (AuthorizationException $exception) {
                Notification::make()
                    ->title('Aksi submit tidak diizinkan')
                    ->body($exception->getMessage())
                    ->danger()
                    ->send();

                throw $exception;
            }
        }

        return $record;
    }
}
