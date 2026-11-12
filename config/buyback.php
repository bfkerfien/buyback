<?php

return [
    // The Jita market region.
    'region_id' => env('BUYBACK_REGION_ID', '10000002'),
    'jita_station_id' => (int) env('BUYBACK_JITA_STATION_ID', 60003760),
    'esi_compatibility_date' => env('EVE_ESI_COMPATIBILITY_DATE', '2026-11-12'),
    // Buy-side valuation is the conservative default for a buyback service.
    'price_side' => env('BUYBACK_PRICE_SIDE', 'buy'),
    'payout_percent' => (float) env('BUYBACK_PAYOUT_PERCENT', 90),
    'eve_user_agent' => env('EVE_USER_AGENT', ''),
];
