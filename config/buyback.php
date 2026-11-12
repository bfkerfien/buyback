<?php

return [
    // The Jita market region.
    'region_id' => env('BUYBACK_REGION_ID', '10000002'),
    // Buy-side valuation is the conservative default for a buyback service.
    'price_side' => env('BUYBACK_PRICE_SIDE', 'buy'),
    'payout_percent' => (float) env('BUYBACK_PAYOUT_PERCENT', 90),
];
