<?php

use App\Services\ExciseDutyService;
use App\Services\LedgerService;

it('takes 5% of the gross deposit, rounded half up to the cent', function (float $gross, float $excise, float $net) {
    $amounts = (new ExciseDutyService(new LedgerService))->calculate($gross, 0.05);

    expect($amounts)->toBe(['gross' => $gross, 'rate' => 0.05, 'excise' => $excise, 'net' => $net]);
})->with([
    'KES 1' => [1.0, 0.05, 0.95],
    'KES 10' => [10.0, 0.5, 9.5],
    'KES 99' => [99.0, 4.95, 94.05],
    'KES 100' => [100.0, 5.0, 95.0],
    'KES 101' => [101.0, 5.05, 95.95],
    'half cent rounds up' => [10.1, 0.51, 9.59],
    'KES 150,000' => [150000.0, 7500.0, 142500.0],
]);

it('takes nothing at a zero rate', function () {
    $amounts = (new ExciseDutyService(new LedgerService))->calculate(100.0, 0.0);

    expect($amounts['excise'])->toBe(0.0)
        ->and($amounts['net'])->toBe(100.0);
});
