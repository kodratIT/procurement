<?php

return [
    'sample_shipments' => [
        'approval_route' => env('SAMPLE_SHIPMENT_APPROVAL_ROUTE', 'procurement'),
        'require_receipt_evidence' => (bool) env('SAMPLE_SHIPMENT_REQUIRE_RECEIPT_EVIDENCE', true),
    ],
];
