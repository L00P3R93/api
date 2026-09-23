<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\DisputedTransaction;
use App\Services\Concerns\BuildsFinanceQueries;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Complaints and the money they moved: what was disputed and held, what was refunded to players, the
 * house cuts reversed, and what went back to the winners.
 */
class FinanceDisputeReportService
{
    use BuildsFinanceQueries;

    /**
     * Complaints filed inside the range.
     *
     * Filters: status, type (game, tournament, jackpot), customer_id (the complainant).
     *
     * @param  array<string, string>  $filters
     */
    public function listing(FinanceDateRange $range, array $filters): FinanceListing
    {
        $base = fn () => DB::table('complaints as c')
            ->whereBetween('c.created_at', [$range->from, $range->to])
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('c.status', $filters['status']))
            ->when(isset($filters['type']), fn (Builder $query) => $query->where('c.subject_type', $filters['type']))
            ->when(isset($filters['customer_id']), fn (Builder $query) => $query->where('c.customer_id', $filters['customer_id']));

        $query = $base()
            ->selectRaw('c.*, (SELECT COALESCE(SUM(d.balance), 0) FROM disputed_transactions d WHERE d.complaint_id = c.id) as still_held')
            ->orderByDesc('c.created_at')
            ->orderByDesc('c.id');

        $summary = function () use ($base) {
            $byStatus = $base()
                ->selectRaw('c.status, COUNT(*) as complaints, SUM(c.disputed_amount) as disputed, SUM(c.held_amount) as held, SUM(c.shortfall_amount) as shortfall, SUM(c.refunded_amount) as refunded, SUM(c.house_cuts_reversed) as house_cuts_reversed, SUM(c.released_amount) as released')
                ->groupBy('c.status')
                ->get()
                ->keyBy('status');

            $shape = fn (?object $row) => [
                'complaints' => (int) ($row->complaints ?? 0),
                'disputed' => $this->money($row->disputed ?? 0),
                'held' => $this->money($row->held ?? 0),
                'shortfall' => $this->money($row->shortfall ?? 0),
                'refunded' => $this->money($row->refunded ?? 0),
                'house_cuts_reversed' => $this->money($row->house_cuts_reversed ?? 0),
                'released' => $this->money($row->released ?? 0),
            ];

            return [
                'by_status' => collect(Complaint::STATUSES)->mapWithKeys(fn (string $status) => [$status => $shape($byStatus->get($status))])->all(),
                'currently_held' => $this->money(DisputedTransaction::held()->sum('balance')),
            ];
        };

        return new FinanceListing(
            $query,
            fn (object $row) => [
                'id' => (int) $row->id,
                'complaint_id' => $row->complaint_id,
                'filed_at' => $this->moment($row->created_at),
                'customer_id' => (int) $row->customer_id,
                'subject_type' => $row->subject_type,
                'game_wallet_id' => $row->game_wallet_id === null ? null : (int) $row->game_wallet_id,
                'competition_wallet_id' => $row->competition_wallet_id === null ? null : (int) $row->competition_wallet_id,
                'reason' => $row->reason,
                'status' => $row->status,
                'disputed_amount' => $this->money($row->disputed_amount),
                'held_amount' => $this->money($row->held_amount),
                'shortfall_amount' => $this->money($row->shortfall_amount),
                'still_held' => $this->money($row->still_held),
                'refunded_amount' => $this->money($row->refunded_amount),
                'house_cuts_reversed' => $this->money($row->house_cuts_reversed),
                'released_amount' => $this->money($row->released_amount),
                'filed_by' => $row->filed_by,
                'closed_at' => $this->moment($row->closed_at),
                'closed_by' => $row->closed_by,
                'resolution_note' => $row->resolution_note,
            ],
            $summary,
            ['id', 'complaint_id', 'filed_at', 'customer_id', 'subject_type', 'game_wallet_id', 'competition_wallet_id', 'reason', 'status', 'disputed_amount', 'held_amount', 'shortfall_amount', 'still_held', 'refunded_amount', 'house_cuts_reversed', 'released_amount', 'filed_by', 'closed_at', 'closed_by', 'resolution_note'],
        );
    }
}
