<?php

namespace Database\Factories;

use App\Models\ProcurementField;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestFieldValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequestFieldValue>
 */
class PurchaseRequestFieldValueFactory extends Factory
{
    protected $model = PurchaseRequestFieldValue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_request_id' => PurchaseRequest::factory(),
            'field_id' => ProcurementField::factory(),
            'value' => 'example',
        ];
    }
}
