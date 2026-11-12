<?php
return ['eve'=>[
 'client_id'=>env('EVE_CLIENT_ID'),'client_secret'=>env('EVE_CLIENT_SECRET'),'callback_url'=>env('EVE_CALLBACK_URL'),
 'scopes'=>array_filter(explode(' ',env('EVE_SCOPES','publicData'))),
 'admin_character_ids'=>array_filter(array_map('intval',explode(',',env('BUYBACK_ADMIN_CHARACTER_IDS','')))),
]];