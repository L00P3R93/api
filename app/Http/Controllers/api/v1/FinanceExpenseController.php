<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFinanceExpenseRequest;
use App\Http\Requests\VoidFinanceExpenseRequest;
use App\Models\FinanceExpense;
use App\Services\FinanceExpenseService;
use Illuminate\Http\JsonResponse;

class FinanceExpenseController extends Controller
{
    public function __construct(private FinanceExpenseService $expenses) {}

    public function store(StoreFinanceExpenseRequest $request): JsonResponse
    {
        $expense = $this->expenses->record($request->validated(), $this->actorFor($request));

        return response()->json(['success' => true, 'data' => $this->present($expense)], 201);
    }

    /**
     * Void an expense. It is kept, flagged and left out of totals; enter a corrected one if needed.
     */
    public function void(VoidFinanceExpenseRequest $request, string $encryptedIdentifier): JsonResponse
    {
        $expense = FinanceExpense::find($encryptedIdentifier);

        if (! $expense) {
            return response()->json(['success' => false, 'message' => 'Expense not found'], 404);
        }

        $voided = $this->expenses->void($expense->id, $request->validated('reason'), $this->actorFor($request));

        if ($voided === null) {
            return response()->json(['success' => false, 'message' => 'Expense is already voided'], 409);
        }

        return response()->json(['success' => true, 'data' => $this->present($voided)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(FinanceExpense $expense): array
    {
        return [
            'id' => $expense->id,
            'expense_date' => $expense->expense_date->toDateString(),
            'category' => $expense->category,
            'amount' => (float) $expense->amount,
            'description' => $expense->description,
            'reference' => $expense->reference,
            'status' => $expense->isVoided() ? 'voided' : 'active',
            'entered_by' => $expense->entered_by,
            'voided_at' => $expense->voided_at?->toIso8601String(),
            'voided_by' => $expense->voided_by,
            'void_reason' => $expense->void_reason,
        ];
    }
}
