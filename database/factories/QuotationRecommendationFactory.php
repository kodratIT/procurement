<?php

namespace Database\Factories;

use App\Models\Office;
use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Models\QuotationRecommendation;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuotationRecommendation> */
class QuotationRecommendationFactory extends Factory
{
    protected $model = QuotationRecommendation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'purchase_request_id' => PurchaseRequest::factory(),
            'quotation_id' => Quotation::factory(),
            'vendor_id' => Vendor::factory(),
            'recommended_by_id' => User::factory(),
            'office_id' => Office::factory(),
            'version' => 1,
            'reason' => fake()->sentence(12),
            'evidence_attachment_ids' => [],
            'comparison_snapshot' => [],
        ];
    }
}
