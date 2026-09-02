<?php

return [

    /*
    |--------------------------------------------------------------------------
    | House Wallet ID
    |--------------------------------------------------------------------------
    |
    | The ID of the wallet that receives house cuts from game and competition
    | transactions. This should match the wallet ID in the database.
    |
    */

    'house_wallet_id' => (int) env('HOUSE_WALLET_ID', 1),

];
