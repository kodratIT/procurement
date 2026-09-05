<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseRequests\Pages;

use App\Filament\Resources\PurchaseRequestResource;
use App\Services\ProcurementRequestDraftSaver;
use App\Services\ProcurementRequestSubmitter;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreatePurchaseRequest extends CreateRecord
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

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Purchase request berhasil dibuat';
    }

    protected function handleRecordCreation(array $data): Model
    {
        $action = $data['submit_action'] ?? 'draft';
        unset($data['submit_action']);

        $record = app(ProcurementRequestDraftSaver::class)->save($data);

        if ($action === 'submit') {
            try {
                $record = app(ProcurementRequestSubmitter::class)->submit($record);

                Notification::make()
                    ->title('Purchase request diajukan')
                    ->body('Request berhasil diajukan ke Pengadaan dan akan diteruskan ke Keuangan sesuai workflow.')
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
