<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestFieldValue extends Model
{
    use HasFactory;

    protected $fillable = ['purchase_request_id', 'field_id', 'value', 'file_path'];

    protected $hidden = ['value', 'file_path'];

    public function field(): BelongsTo
    {
        return $this->belongsTo(ProcurementCategoryField::class, 'field_id');
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    /** Decode the normalized JSON payload without exposing storage details. */
    public function decodedValue(): mixed
    {
        if ($this->value === null) {
            return null;
        }

        $decoded = json_decode($this->value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $this->value;
    }

    public function setDecodedValue(mixed $value): void
    {
        $this->value = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
