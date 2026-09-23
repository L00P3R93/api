<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListComplaintsRequest;
use App\Http\Requests\StoreComplaintRequest;
use App\Http\Resources\ComplaintResource;
use App\Models\Complaint;
use App\Services\ComplaintService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ComplaintController extends Controller
{
    public function __construct(private ComplaintService $complaints) {}

    /**
     * Newest first, 50 per page by default.
     */
    public function index(ListComplaintsRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $complaints = Complaint::query()
            ->with('disputedTransactions')
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['subject_type']), fn (Builder $query) => $query->where('subject_type', $filters['subject_type']))
            ->when(isset($filters['customer_id']), fn (Builder $query) => $query->where('customer_id', $filters['customer_id']))
            ->when(isset($filters['game_wallet_id']), fn (Builder $query) => $query->where('game_wallet_id', $filters['game_wallet_id']))
            ->when(isset($filters['competition_wallet_id']), fn (Builder $query) => $query->where('competition_wallet_id', $filters['competition_wallet_id']))
            ->when(isset($filters['from']), fn (Builder $query) => $query->where('created_at', '>=', $filters['from'].' 00:00:00'))
            ->when(isset($filters['to']), fn (Builder $query) => $query->where('created_at', '<=', $filters['to'].' 23:59:59'))
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 50))
            ->withQueryString();

        return ComplaintResource::collection($complaints);
    }

    /**
     * File a complaint. The disputed winnings are held in dispute escrow straight away.
     */
    public function store(StoreComplaintRequest $request): JsonResponse
    {
        $complaint = $this->complaints->file($request->validated(), $this->actorFor($request));

        return response()->json(['success' => true, 'data' => ComplaintResource::make($complaint)], 201);
    }

    public function show(string $encryptedIdentifier): JsonResponse
    {
        $complaint = Complaint::with('disputedTransactions')->find($encryptedIdentifier);

        if (! $complaint) {
            return response()->json(['success' => false, 'message' => 'Complaint not found'], 404);
        }

        return response()->json(['success' => true, 'data' => ComplaintResource::make($complaint)]);
    }
}
