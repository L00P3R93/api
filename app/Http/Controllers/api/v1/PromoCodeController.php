<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePromoCodeRequest;
use App\Models\PromoCode;
use App\Services\PromoCodeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromoCodeController extends Controller
{
    public function __construct(private PromoCodeService $promoCodes) {}

    /**
     * Every promo code, newest first, with signups and bonuses paid. Filter by status.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:active,expired,deactivated'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $status = $filters['status'] ?? null;

        $codes = PromoCode::query()
            ->withCount(['credits', 'customers'])
            ->when($status === PromoCode::STATUS_ACTIVE, fn (Builder $query) => $query->usable())
            ->when($status === PromoCode::STATUS_EXPIRED, fn (Builder $query) => $query->whereNull('deactivated_at')->where('expires_at', '<=', now()))
            ->when($status === PromoCode::STATUS_DEACTIVATED, fn (Builder $query) => $query->whereNotNull('deactivated_at'))
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 50))
            ->withQueryString();

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $codes->getCollection()->map(fn (PromoCode $promoCode) => $this->promoCodes->present($promoCode))->values(),
                'pagination' => [
                    'page' => $codes->currentPage(),
                    'per_page' => $codes->perPage(),
                    'total' => $codes->total(),
                    'last_page' => $codes->lastPage(),
                ],
            ],
        ]);
    }

    public function store(StorePromoCodeRequest $request): JsonResponse
    {
        $promoCode = $this->promoCodes->create($request->validated(), $this->actorFor($request));

        return response()->json(['success' => true, 'data' => $this->promoCodes->present($promoCode)], 201);
    }

    /**
     * Whether a player can sign up with this code right now, for the signup screen. 404 when it is
     * unknown, expired, deactivated or used up, or when the promotion is off.
     */
    public function lookup(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string|max:30']);

        $promoCode = $this->promoCodes->findUsable((string) $request->string('code'));

        if (! $promoCode) {
            return response()->json(['success' => false, 'message' => 'Promo code not valid'], 404);
        }

        return response()->json(['success' => true, 'data' => [
            'code' => $promoCode->code,
            'expires_at' => $promoCode->expires_at->toIso8601String(),
        ]]);
    }

    /**
     * Stop a code from being used. Players who signed up with it and are not yet verified will not get
     * the bonus.
     */
    public function deactivate(Request $request, string $encryptedIdentifier): JsonResponse
    {
        $promoCode = PromoCode::find($encryptedIdentifier);

        if (! $promoCode) {
            return response()->json(['success' => false, 'message' => 'Promo code not found'], 404);
        }

        if (! $this->promoCodes->deactivate($promoCode, $this->actorFor($request))) {
            return response()->json(['success' => false, 'message' => 'Promo code is already deactivated', 'data' => $this->promoCodes->present($promoCode)], 409);
        }

        return response()->json(['success' => true, 'data' => $this->promoCodes->present($promoCode->fresh())]);
    }
}
