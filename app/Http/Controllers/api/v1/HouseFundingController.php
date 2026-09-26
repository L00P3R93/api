<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DepositResolutionResource;
use App\Models\Deposit;
use App\Models\HouseFunding;
use App\Services\HouseFundingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HouseFundingController extends Controller
{
    public function __construct(private HouseFundingService $houseFunding) {}

    /**
     * Credit an unmatched deposit (owner money paid to the paybill) to the house wallet as house funding.
     */
    public function store(Request $request, string $encryptedIdentifier): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        return $this->respond($this->houseFunding->fund((int) $encryptedIdentifier, $validated['note'], $this->actorFor($request)));
    }

    /**
     * Void a house funding: reverse the credit and put the deposit back to unmatched.
     */
    public function void(Request $request, string $encryptedIdentifier): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        return $this->respond($this->houseFunding->void((int) $encryptedIdentifier, $validated['reason'], $this->actorFor($request)));
    }

    /**
     * A refused request carries `code` (`already_resolved`, `insufficient_house_balance`).
     *
     * @param  array{success: bool, message: string, status_code: int, deposit?: Deposit, house_funding?: HouseFunding, code?: string}  $result
     */
    private function respond(array $result): JsonResponse
    {
        $deposit = $result['deposit'] ?? null;
        $funding = $result['house_funding'] ?? null;

        return response()->json(array_filter([
            'success' => $result['success'],
            'code' => $result['code'] ?? null,
            'message' => $result['message'],
            'data' => $deposit || $funding ? array_filter([
                'house_funding' => $funding ? $this->houseFunding->present($funding) : null,
                'deposit' => $deposit ? [
                    'id' => $deposit->id,
                    'trans_id' => $deposit->trans_id,
                    'amount' => (float) $deposit->trans_amount,
                    'bill_ref_no' => $deposit->bill_ref_no,
                    'status' => (int) $deposit->status,
                    'resolution' => $deposit->resolution ? DepositResolutionResource::make($deposit->resolution) : null,
                ] : null,
            ], fn ($value) => $value !== null) : null,
        ], fn ($value) => $value !== null), $result['status_code']);
    }
}
