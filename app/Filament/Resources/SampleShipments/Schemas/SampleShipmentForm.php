<?php

declare(strict_types=1);

namespace App\Filament\Resources\SampleShipments\Schemas;

use App\Enums\SampleShipmentCondition;
use App\Models\CostCenter;
use App\Models\Office;
use App\Models\ProcurementItem;
use App\Models\ProcurementVariant;
use App\Models\PurchaseOrder;
use App\Models\SampleShipment;
use App\Models\User;
use App\Services\AccessContextService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

final class SampleShipmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('purchase_order_id')
                    ->label('Origin purchase order')
                    ->options(fn (): array => PurchaseOrder::query()
                        ->whereIn('status', [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_ISSUED])
                        ->where('office_id', app(AccessContextService::class)->id())
                        ->with('vendor')
                        ->orderByDesc('updated_at')
                        ->get()
                        ->mapWithKeys(fn (PurchaseOrder $order): array => [$order->id => $order->po_number.' — '.($order->vendor?->name ?? 'Vendor')])
                        ->all())
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('sender_office_id')
                    ->label('Sender office')
                    ->options(fn (): array => app(AccessContextService::class)->allowedOffices()->pluck('name', 'id')->all())
                    ->default(fn (): ?int => app(AccessContextService::class)->id())
                    ->disabled()
                    ->dehydrated()
                    ->required(),
                Select::make('receiver_office_id')
                    ->label('Receiving office')
                    ->options(fn (): array => Office::query()
                        ->where('is_active', true)
                        ->where('id', '!=', app(AccessContextService::class)->id())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('sender_id')
                    ->label('Responsible sender')
                    ->options(fn (): array => self::usersForOffice(app(AccessContextService::class)->id()))
                    ->default(fn (): ?int => Auth::id())
                    ->disabled()
                    ->dehydrated()
                    ->required(),
                Select::make('receiver_id')
                    ->label('Responsible receiver')
                    ->options(fn (Get $get): array => self::usersForOffice($get->integer('receiver_office_id')))
                    ->searchable()
                    ->preload(),
                Select::make('cost_center_id')
                    ->label('Cost center')
                    ->options(fn (): array => CostCenter::query()
                        ->where('office_id', app(AccessContextService::class)->id())
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload(),
                TextInput::make('purpose')->required()->maxLength(255),
                DatePicker::make('requested_at')->default(now())->required(),
                DatePicker::make('planned_ship_date'),
                TextInput::make('tracking_no')->maxLength(100),
                TextInput::make('shipping_cost')->numeric()->minValue(0)->default(0)->prefix('IDR'),
                Select::make('approval_route')
                    ->options([
                        SampleShipment::APPROVAL_ROUTE_PROCUREMENT => 'Procurement only',
                        SampleShipment::APPROVAL_ROUTE_FINANCE => 'Procurement and finance',
                    ])
                    ->default(config('procurement.sample_shipments.approval_route', SampleShipment::APPROVAL_ROUTE_PROCUREMENT))
                    ->required(),
                Select::make('condition')
                    ->options(self::conditionOptions())
                    ->default(SampleShipmentCondition::Good->value)
                    ->required(),
                Repeater::make('lines')
                    ->label('Sample items')
                    ->schema([
                        Select::make('procurement_item_id')
                            ->label('Item')
                            ->options(fn (): array => ProcurementItem::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('procurement_variant_id')
                            ->label('Variant')
                            ->options(fn (Get $get): array => ProcurementVariant::query()
                                ->where('item_id', $get->integer('procurement_item_id'))
                                ->active()
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (ProcurementVariant $variant): array => [$variant->id => $variant->name.' — '.$variant->value])
                                ->all())
                            ->searchable()
                            ->preload(),
                        TextInput::make('quantity')->numeric()->minValue(0.01)->required(),
                        Select::make('condition')->options(self::conditionOptions())->default(SampleShipmentCondition::Good->value)->required(),
                        Textarea::make('notes')->maxLength(255),
                    ])
                    ->columns(2)
                    ->minItems(1)
                    ->required(),
                FileUpload::make('evidence')
                    ->label('Shipment evidence')
                    ->multiple()
                    ->storeFiles(false)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                    ->maxSize(10240),
                Textarea::make('notes')->maxLength(255)->columnSpanFull(),
            ])
            ->columns(2);
    }

    /** @return array<int|string, string> */
    private static function usersForOffice(?int $officeId): array
    {
        if ($officeId === null || $officeId < 1) {
            return [];
        }

        return User::query()
            ->where('is_active', true)
            ->whereHas('assignments', fn (Builder $query): Builder => $query->where('office_id', $officeId)->currentlyActive())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<string, string> */
    private static function conditionOptions(): array
    {
        return collect(SampleShipmentCondition::values())->mapWithKeys(fn (string $value): array => [$value => str($value)->headline()])->all();
    }
}
