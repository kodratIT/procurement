<?php

declare(strict_types=1);

namespace App\Filament\Imports;

use App\Models\Pilgrim;
use App\Models\UmrahBatch;
use App\Models\User;
use App\Services\AccessContextService;
use App\Services\MultiOfficeAuthorization;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PilgrimImporter extends Importer
{
    protected static ?string $model = Pilgrim::class;

    protected static bool $shouldPreventFormulaInjection = true;

    /** @return array<int, ImportColumn> */
    public static function getColumns(): array
    {
        return [
            ImportColumn::make('umrah_batch_id')
                ->label('Batch Umrah')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('name')
                ->label('Nama lengkap')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('passport_no')
                ->label('Nomor paspor')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:50']),
            ImportColumn::make('phone')
                ->label('Nomor telepon')
                ->rules(['nullable', 'string', 'max:30']),
            ImportColumn::make('status')
                ->label('Status')
                ->rules(['nullable', Rule::in(array_keys(Pilgrim::STATUSES))]),
            ImportColumn::make('is_active')
                ->label('Aktif')
                ->boolean()
                ->rules(['nullable', 'boolean']),
        ];
    }

    /** @return array<string, array<mixed>> */
    public function getValidationRules(): array
    {
        $officeId = $this->officeId();
        $batchId = (int) ($this->data['umrah_batch_id'] ?? 0);
        $rules = parent::getValidationRules();
        $rules['office_id'] = ['required', 'integer', Rule::exists('offices', 'id')->where(
            fn (Builder $query): Builder => $query->where('is_active', true),
        )];
        $rules['umrah_batch_id'][] = Rule::exists('umrah_batches', 'id')->where(
            fn (Builder $query): Builder => $query
                ->where('office_id', $officeId)
                ->where('is_active', true)
                ->whereIn('status', [UmrahBatch::STATUS_PLANNED, UmrahBatch::STATUS_OPEN]),
        );
        $rules['passport_no'][] = Rule::unique('pilgrims', 'passport_no')->where(
            fn (Builder $query): Builder => $query->where('umrah_batch_id', $batchId),
        );

        return $rules;
    }

    protected function beforeValidate(): void
    {
        $officeId = $this->officeId();
        $this->data['office_id'] = $officeId;

        $batch = UmrahBatch::query()
            ->withoutGlobalScopes()
            ->whereKey($this->data['umrah_batch_id'] ?? null)
            ->where('office_id', $officeId)
            ->availableForNewPilgrims()
            ->first();

        if (! $batch instanceof UmrahBatch) {
            throw ValidationException::withMessages([
                'umrah_batch_id' => 'Batch tidak aktif atau tidak berada dalam scope kantor.',
            ]);
        }
    }

    protected function beforeCreate(): void
    {
        $this->record?->forceFill(['office_id' => $this->officeId()]);
    }

    public function resolveRecord(): ?Model
    {
        return new Pilgrim;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return "Jamaah berhasil diimpor: {$import->successful_rows} baris.";
    }

    private function officeId(): int
    {
        $officeId = (int) ($this->options['office_id'] ?? app(AccessContextService::class)->id());

        if ($officeId < 1) {
            throw ValidationException::withMessages([
                'office_id' => 'Kantor aktif wajib dipilih sebelum impor.',
            ]);
        }

        $user = Auth::user();
        if ($user instanceof User
            && ! app(MultiOfficeAuthorization::class)->canCreate($user, ['office_id' => $officeId])) {
            throw ValidationException::withMessages([
                'office_id' => 'Kantor tidak berada dalam scope assignment aktif.',
            ]);
        }

        return $officeId;
    }
}
