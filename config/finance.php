<?php

$testCustomerIds = env('FINANCE_TEST_CUSTOMER_IDS');

return [

    /*
    |--------------------------------------------------------------------------
    | Test Customer IDs
    |--------------------------------------------------------------------------
    |
    | Customers listed here are excluded from customer stats and financial
    | reports. Set FINANCE_TEST_CUSTOMER_IDS to a comma separated list to
    | override. The default matches the historic "id below 120" rule, which
    | also covers the house customer (id 1).
    |
    */

    'test_customer_ids' => $testCustomerIds === null || $testCustomerIds === ''
        ? range(1, 500)
        : array_values(array_filter(array_map('intval', explode(',', $testCustomerIds)))),

];
