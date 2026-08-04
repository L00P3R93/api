<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\B2C;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class B2CBalanceController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        // Log incoming request
        // Log::channel('mpesa')->info('MPESA B2C Balance: ', $request->all());

        // Safely get array from request (already decoded JSON)
        $data = $request->all();

        // Extract the AccountBalance value
        $accountBalanceString = collect($data['Result']['ResultParameters']['ResultParameter'])
            ->firstWhere('Key', 'AccountBalance')['Value'] ?? null;

        $balances = [];

        if ($accountBalanceString) {
            // Split into accounts
            $accounts = explode('&', $accountBalanceString);

            foreach ($accounts as $account) {
                $parts = explode('|', $account);

                // Example: ["Working Account", "KES", "0.00", "0.00", "0.00", "0.00"]
                $accountName = $parts[0] ?? null;
                $currency    = $parts[1] ?? null;
                $balance     = $parts[2] ?? 0.00; // Main balance

                if ($accountName) {
                    $balances[$accountName] = [
                        'currency' => $currency,
                        'balance'  => (float) $balance,
                    ];
                }
            }

            B2C::query()->create([
                'amount' => $balances['Utility Account']['balance'],
            ]);
        }
    }
}
