<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReferralWithdrawalResource;
use App\Models\Customer;
use App\Models\ReferralWithdrawal;
use App\Services\ReferralWithdrawalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ReferralWithdrawalController extends Controller
{
    public function __construct(private ReferralWithdrawalService $withdrawals) {}

    /**
     * Every referral withdrawal, newest first. Filter by status and customer.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(ReferralWithdrawal::STATUSES)],
            'customer_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $withdrawals = ReferralWithdrawal::query()
            ->with(['ledgerEntry:id,entry_id'])
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['customer_id']), fn (Builder $query) => $query->where('customer_id', $filters['customer_id']))
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 50))
            ->withQueryString();

        return ReferralWithdrawalResource::collection($withdrawals);
    }

    /**
     * Pay part or all of the customer's referral wallet to their M-Pesa number, from the referral shortcode.
     */
    public function store(Request $request, string $encryptedIdentifier): JsonResponse
    {
        $request->validate(['amount' => ['required', 'integer', 'min:1']]);

        $customer = $this->customer($encryptedIdentifier);

        if (! $customer) {
            return response()->json(['success' => false, 'message' => 'Customer not found'], 404);
        }

        $result = $this->withdrawals->withdraw($customer, $request->integer('amount'), $this->actorFor($request));

        return response()->json(array_filter([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => isset($result['withdrawal']) ? ReferralWithdrawalResource::make($result['withdrawal']) : null,
        ], fn ($value) => $value !== null), $result['status_code']);
    }

    /**
     * The customer's referral withdrawals, newest first.
     */
    public function customerWithdrawals(Request $request, string $encryptedIdentifier): AnonymousResourceCollection|JsonResponse
    {
        $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:200']]);

        $customer = $this->customer($encryptedIdentifier);

        if (! $customer) {
            return response()->json(['success' => false, 'message' => 'Customer not found'], 404);
        }

        $withdrawals = ReferralWithdrawal::where('customer_id', $customer->id)
            ->with(['ledgerEntry:id,entry_id'])
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        return ReferralWithdrawalResource::collection($withdrawals);
    }

    private function customer(string $identifier): ?Customer
    {
        return Customer::where('id', $identifier)->orWhere('account_no', $identifier)->first();
    }
}
