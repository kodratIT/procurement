<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Pilgrim;
use App\Models\ProcurementCategory;
use App\Models\UmrahBatch;
use App\Support\ProcurementCategoryConfiguration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorInstance;
use InvalidArgumentException;

final class CategoryRelationGuard
{
    private ?ProcurementCategory $category = null;

    private ?ProcurementCategoryConfiguration $configuration = null;

    public static function forCategory(ProcurementCategory|int $category): self
    {
        return (new self)->category($category);
    }

    public function category(ProcurementCategory|int $category): self
    {
        $this->category = $category instanceof ProcurementCategory
            ? $category
            : ProcurementCategory::query()->find($category);
        $this->configuration = null;

        if (! $this->category instanceof ProcurementCategory) {
            throw new InvalidArgumentException('A valid procurement category is required.');
        }

        return $this;
    }

    public function configuration(): ProcurementCategoryConfiguration
    {
        if (! $this->category instanceof ProcurementCategory) {
            throw new InvalidArgumentException('A procurement category must be selected first.');
        }

        return $this->configuration ??= $this->category->configuration();
    }

    public function requirements(): ProcurementCategoryConfiguration
    {
        return $this->configuration();
    }

    public function requiresBatch(): bool
    {
        return $this->configuration()->requiresBatch();
    }

    public function requiresPilgrim(): bool
    {
        return $this->configuration()->requiresJamaah();
    }

    public function requiresJamaah(): bool
    {
        return $this->requiresPilgrim();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rulesForRelations(?int $officeId = null): array
    {
        $configuration = $this->configuration();
        $batchRules = ['nullable', 'integer'];
        $pilgrimRules = ['nullable', 'integer'];

        if ($configuration->requiresBatch()) {
            array_unshift($batchRules, 'required');
        }

        if ($configuration->requiresJamaah()) {
            array_unshift($pilgrimRules, 'required');
        }

        $batchRules[] = Rule::exists('umrah_batches', 'id')->where(
            fn (Builder $query): Builder => $query
                ->where('is_active', true)
                ->when($officeId !== null, fn (Builder $query): Builder => $query->where('office_id', $officeId)),
        );
        $pilgrimRules[] = Rule::exists('pilgrims', 'id')->where(
            fn (Builder $query): Builder => $query
                ->where('is_active', true)
                ->when($officeId !== null, fn (Builder $query): Builder => $query->where('office_id', $officeId)),
        );

        return [
            'umrah_batch_id' => $batchRules,
            'pilgrim_id' => $pilgrimRules,
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(?int $officeId = null): array
    {
        return $this->rulesForRelations($officeId);
    }

    /**
     * @param  array<string, mixed>  $relations
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $relations, ?int $officeId = null): array
    {
        $validator = Validator::make($relations, $this->rulesForRelations($officeId));
        $validator->after(function (ValidatorInstance $validator) use ($relations, $officeId): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $batch = isset($relations['umrah_batch_id'])
                ? UmrahBatch::query()->withoutGlobalScopes()->find($relations['umrah_batch_id'])
                : null;
            $pilgrim = isset($relations['pilgrim_id'])
                ? Pilgrim::query()->withoutGlobalScopes()->find($relations['pilgrim_id'])
                : null;

            if ($officeId !== null && $batch instanceof UmrahBatch && (int) $batch->office_id !== $officeId) {
                $validator->errors()->add('umrah_batch_id', 'Batch harus berada dalam kantor yang sama.');
            }

            if ($officeId !== null && $pilgrim instanceof Pilgrim && (int) $pilgrim->office_id !== $officeId) {
                $validator->errors()->add('pilgrim_id', 'Jamaah harus berada dalam kantor yang sama.');
            }

            if ($batch !== null && $pilgrim !== null && $pilgrim->umrah_batch_id !== $batch->getKey()) {
                $validator->errors()->add('pilgrim_id', 'Jamaah harus berasal dari batch yang dipilih.');
            }
        });
        $validator->validate();

        return $relations;
    }
}
