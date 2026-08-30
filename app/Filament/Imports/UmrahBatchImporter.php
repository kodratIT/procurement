<?php

declare(strict_types=1);

namespace App\Filament\Imports;

use App\Models\UmrahBatch;
use App\Models\User;
use App\Services\MultiOfficeAuthorization;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UmrahBatchImporter extends Importer
{
    protected static ?string $model = UmrahBatch::class;

    protected static bool $shouldPreventFormulaInjection = true;

    /** @return array<int, ImportColumn> */
    public static function getColumns(): array
    {
        return [
            ImportColumn::make('code')
                ->label('Kode')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:50']),
            ImportColumn::make('name')
                ->label('Nama')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('departure_date')
                ->label('Tanggal keberangkatan')
                ->requiredMapping()
                ->rules(['required', 'date']),
            ImportColumn::make('return_date')
                ->label('Tanggal kepulangan')
                ->rules(['nullable', 'date']),
            ImportColumn::make('capacity')
                ->label('Kapasitas')
                ->numeric()
                ->rules(['nullable', 'integer', 'min:1']),
            ImportColumn::make('pilgrim_count')
                ->label('Jumlah jamaah')
                ->numeric()
                ->rules(['nullable', 'integer', 'min:0']),
            ImportColumn::make('status')
                ->label('Status')
                ->rules(['nullable', Rule::in(array_keys(UmrahBatch::STATUSES))]),
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
        $rules = parent::getValidationRules();
        $rules['office_id'] = ['required', 'integer', Rule::exists('offices', 'id')->where(
            fn (Builder $query): Builder => $query->where('is_active', true),
        )];
        $rules['code'][] = Rule::unique('umrah_batches', 'code')->where(
            fn (Builder $query): Builder => $query->where('office_id', $officeId),
        );

        return $rules;
    }

    protected function beforeValidate(): void
    {
        $this->data['office_id'] = $this->officeId();
    }

    protected function beforeCreate(): void
    {
        $this->record?->forceFill(['office_id' => $this->officeId()]);
    }

    public function resolveRecord(): ?Model
    {
        return new UmrahBatch;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return "Batch umrah berhasil diimpor: {$import->successful_rows} baris.";
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
