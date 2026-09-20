<?php

namespace App\Services;

use App\Models\CompetitionTransaction;
use App\Services\Concerns\BuildsFinanceQueries;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Drill-downs into single games and competitions: what was staked, what the house took and what
 * was paid out or is still held.
 */
class FinanceGameReportService
{
    use BuildsFinanceQueries;

    /** Customer id the game system uses for the house on game transactions. */
    private const HOUSE_CUSTOMER_ID = 1;

    /**
     * One row per game, dated by when the game wallet was created.
     *
     * house_take is stakes minus what players were paid and refunded minus anything still in the game
     * wallet, so it is only shown once the game is closed. A negative take means players were paid
     * or refunded more than they staked.
     *
     * Filters: outcome (open, completed, dropped, refunded, closed_empty), game_type, players.
     * `exclude_test` drops games any test customer staked in.
     *
     * @param  array<string, string>  $filters
     */
    public function games(FinanceDateRange $range, array $filters): FinanceListing
    {
        $base = fn () => $this->gameBase($range, $filters);

        $query = $base()->orderByDesc('x.id');

        $summary = function () use ($base) {
            $totals = 'COUNT(*) as games, COALESCE(SUM(x.stakes), 0) as stakes, COALESCE(SUM(x.paid_to_players), 0) as paid_to_players, COALESCE(SUM(x.refunded), 0) as refunded, COALESCE(SUM(x.house_take), 0) as house_take';

            $byOutcome = DB::query()->fromSub($base()->select('x.*'), 'y')
                ->selectRaw('y.outcome as `group`, COUNT(*) as games, COALESCE(SUM(y.stakes), 0) as stakes, COALESCE(SUM(y.paid_to_players), 0) as paid_to_players, COALESCE(SUM(y.refunded), 0) as refunded, COALESCE(SUM(y.house_take), 0) as house_take')
                ->groupBy('y.outcome')->get();

            $byPlayers = DB::query()->fromSub($base()->select('x.*'), 'y')
                ->selectRaw('y.players as `group`, COUNT(*) as games, COALESCE(SUM(y.stakes), 0) as stakes, COALESCE(SUM(y.paid_to_players), 0) as paid_to_players, COALESCE(SUM(y.refunded), 0) as refunded, COALESCE(SUM(y.house_take), 0) as house_take')
                ->groupBy('y.players')->orderBy('y.players')->get();

            $all = $base()->selectRaw($totals)->first();

            $shape = fn ($row) => [
                'games' => (int) $row->games,
                'stakes' => $this->money($row->stakes),
                'paid_to_players' => $this->money($row->paid_to_players),
                'refunded' => $this->money($row->refunded),
                'house_take' => $this->money($row->house_take),
            ];

            return $shape($all) + [
                'by_outcome' => $byOutcome->mapWithKeys(fn ($row) => [$row->group => $shape($row)])->all(),
                'by_players' => $byPlayers->mapWithKeys(fn ($row) => [(string) $row->group => $shape($row)])->all(),
            ];
        };

        return new FinanceListing(
            $query->select('x.*'),
            fn (object $row) => [
                'id' => (int) $row->id,
                'game_id' => $row->game_id,
                'game_type' => (int) $row->game_type,
                'outcome' => $row->outcome,
                'players' => (int) $row->players,
                'stakes' => $this->money($row->stakes),
                'paid_to_players' => $this->money($row->paid_to_players),
                'house_payout' => $this->money($row->house_payout),
                'refunded' => $this->money($row->refunded),
                'escrow_balance' => $this->money($row->balance),
                'house_take' => $row->house_take === null ? null : $this->money($row->house_take),
                'created_at' => $this->moment($row->created_at),
            ],
            $summary,
            ['id', 'game_id', 'game_type', 'outcome', 'players', 'stakes', 'paid_to_players', 'house_payout', 'refunded', 'escrow_balance', 'house_take', 'created_at'],
        );
    }

    /**
     * One row per competition (all its players' wallets together), dated by when the first wallet was created.
     *
     * pool is entries minus the house cut, and unaccounted is pool minus prizes paid minus what is
     * still held in open wallets. The house cut is only known for entries linked to their ledger
     * cut (from the Phase 0 fixes onward), so older competitions show the whole entry as pool.
     *
     * Filters: game_type (1 tournament, 2 jackpot), jp_rounds.
     * `exclude_test` drops competitions any test customer played in.
     *
     * @param  array<string, string>  $filters
     */
    public function competitions(FinanceDateRange $range, array $filters): FinanceListing
    {
        $base = fn () => $this->competitionBase($range, $filters);

        $query = $base()->orderByRaw('MIN(cw.id) DESC');

        $summary = function () use ($base) {
            $rows = DB::query()->fromSub($base(), 'k')
                ->selectRaw('k.game_type, k.jp_rounds, COUNT(*) as competitions, COALESCE(SUM(k.players), 0) as players, COALESCE(SUM(k.entries), 0) as entries, COALESCE(SUM(k.house_cut), 0) as house_cut, COALESCE(SUM(k.prizes_paid), 0) as prizes_paid, COALESCE(SUM(k.outstanding), 0) as outstanding')
                ->groupBy('k.game_type', 'k.jp_rounds')
                ->orderBy('k.game_type')->orderBy('k.jp_rounds')
                ->get();

            $shape = fn ($row) => [
                'competitions' => (int) $row->competitions,
                'players' => (int) $row->players,
                'entries' => $this->money($row->entries),
                'house_cut' => $this->money($row->house_cut),
                'prizes_paid' => $this->money($row->prizes_paid),
                'outstanding' => $this->money($row->outstanding),
            ];

            $totals = ['competitions' => 0, 'players' => 0, 'entries' => 0.0, 'house_cut' => 0.0, 'prizes_paid' => 0.0, 'outstanding' => 0.0];
            $groups = [];
            foreach ($rows as $row) {
                $figures = $shape($row);
                $groups[] = ['type' => $this->competitionType((int) $row->game_type), 'rounds' => (int) $row->jp_rounds] + $figures;
                foreach ($totals as $key => $value) {
                    $totals[$key] = $value + $figures[$key];
                }
            }

            return array_map(fn ($value) => is_float($value) ? round($value, 2) : $value, $totals) + ['by_type_and_rounds' => $groups];
        };

        return new FinanceListing(
            $query,
            function (object $row) {
                $entries = $this->money($row->entries);
                $houseCut = $this->money($row->house_cut);
                $pool = round($entries - $houseCut, 2);

                return [
                    'cmp_uid' => $row->cmp_uid,
                    'competition_id' => $row->competition_id,
                    'type' => $this->competitionType((int) $row->game_type),
                    'rounds' => (int) $row->jp_rounds,
                    'players' => (int) $row->players,
                    'entries' => $entries,
                    'house_cut' => $houseCut,
                    'pool' => $pool,
                    'prizes_paid' => $this->money($row->prizes_paid),
                    'outstanding' => $this->money($row->outstanding),
                    'unaccounted' => round($pool - (float) $row->prizes_paid - (float) $row->outstanding, 2),
                    'started_at' => $this->moment($row->started_at),
                ];
            },
            $summary,
            ['cmp_uid', 'competition_id', 'type', 'rounds', 'players', 'entries', 'house_cut', 'pool', 'prizes_paid', 'outstanding', 'unaccounted', 'started_at'],
        );
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function gameBase(FinanceDateRange $range, array $filters): Builder
    {
        $excluded = $this->excludedCustomerIds($range);
        $house = self::HOUSE_CUSTOMER_ID;

        $perGame = DB::table('game_wallets as gw')
            ->join('game_transactions as gt', 'gt.game_wallet_id', '=', 'gw.id')
            ->whereBetween('gw.created_at', [$range->from, $range->to])
            ->when($excluded !== [], fn (Builder $query) => $query->whereNotExists(
                fn (Builder $test) => $test->select(DB::raw(1))
                    ->from('game_transactions as t2')
                    ->whereColumn('t2.game_wallet_id', 'gw.id')
                    ->where('t2.payment_type', 'deposit')
                    ->whereIn('t2.customer_id', $excluded)
            ))
            ->selectRaw("gw.id, gw.game_id, gw.game_type, gw.status, gw.balance, gw.created_at,
                COUNT(DISTINCT CASE WHEN gt.payment_type = 'deposit' THEN gt.customer_id END) as players,
                COALESCE(SUM(CASE WHEN gt.payment_type = 'deposit' THEN gt.amount END), 0) as stakes,
                COALESCE(SUM(CASE WHEN gt.payment_type IN ('payout', 'payout|dropped') AND gt.customer_id <> {$house} THEN gt.amount END), 0) as paid_to_players,
                COALESCE(SUM(CASE WHEN gt.payment_type IN ('payout', 'payout|dropped') AND gt.customer_id = {$house} THEN gt.amount END), 0) as house_payout,
                COALESCE(SUM(CASE WHEN gt.payment_type LIKE 'refund%' THEN gt.amount END), 0) as refunded,
                COALESCE(SUM(CASE WHEN gt.payment_type LIKE '%dropped' THEN 1 END), 0) as dropped_rows")
            ->groupBy('gw.id', 'gw.game_id', 'gw.game_type', 'gw.status', 'gw.balance', 'gw.created_at');

        $classified = DB::query()->fromSub($perGame, 'g')
            ->selectRaw("g.*,
                CASE WHEN g.status = 1 THEN 'open'
                     WHEN g.dropped_rows > 0 THEN 'dropped'
                     WHEN g.refunded > 0 AND g.paid_to_players = 0 THEN 'refunded'
                     WHEN g.paid_to_players > 0 THEN 'completed'
                     ELSE 'closed_empty' END as outcome,
                CASE WHEN g.status = 1 THEN NULL ELSE g.stakes - g.paid_to_players - g.refunded - g.balance END as house_take");

        return DB::query()->fromSub($classified, 'x')
            ->when(isset($filters['outcome']), fn (Builder $query) => $query->where('x.outcome', $filters['outcome']))
            ->when(isset($filters['game_type']), fn (Builder $query) => $query->where('x.game_type', (int) $filters['game_type']))
            ->when(isset($filters['players']), fn (Builder $query) => $query->where('x.players', (int) $filters['players']));
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function competitionBase(FinanceDateRange $range, array $filters): Builder
    {
        $excluded = $this->excludedCustomerIds($range);

        $houseCut = "(SELECT COALESCE(SUM(l.credit), 0) FROM ledger_entries l
            JOIN competition_transactions c2 ON c2.id = l.referenceable_id AND l.referenceable_type = ?
            JOIN competition_wallets w2 ON w2.id = c2.competition_wallet_id
            WHERE l.entry_type = 'house_cut' AND w2.cmp_uid = cw.cmp_uid)";
        $outstanding = '(SELECT COALESCE(SUM(x.balance), 0) FROM competition_wallets x WHERE x.cmp_uid = cw.cmp_uid AND x.status = 1)';

        return DB::table('competition_wallets as cw')
            ->leftJoin('competition_transactions as ct', 'ct.competition_wallet_id', '=', 'cw.id')
            ->whereNotNull('cw.cmp_uid')
            ->where('cw.cmp_uid', '!=', '')
            ->whereBetween('cw.created_at', [$range->from, $range->to])
            ->when($excluded !== [], fn (Builder $query) => $query->whereNotExists(
                fn (Builder $test) => $test->select(DB::raw(1))
                    ->from('competition_wallets as t2')
                    ->whereColumn('t2.cmp_uid', 'cw.cmp_uid')
                    ->whereIn('t2.customer_id', $excluded)
            ))
            ->when(isset($filters['game_type']), fn (Builder $query) => $query->where('cw.game_type', (int) $filters['game_type']))
            ->when(isset($filters['jp_rounds']), fn (Builder $query) => $query->where('cw.jp_rounds', (int) $filters['jp_rounds']))
            ->selectRaw("cw.cmp_uid, MIN(cw.competition_id) as competition_id, cw.game_type, MAX(cw.jp_rounds) as jp_rounds, MIN(cw.created_at) as started_at,
                COUNT(DISTINCT cw.customer_id) as players,
                COALESCE(SUM(CASE WHEN ct.payment_type NOT IN ('payout', 'win', 'loss') THEN ct.amount END), 0) as entries,
                COALESCE(SUM(CASE WHEN ct.payment_type = 'payout' THEN ct.amount END), 0) as prizes_paid,
                {$houseCut} as house_cut, {$outstanding} as outstanding", [CompetitionTransaction::class])
            ->groupBy('cw.cmp_uid', 'cw.game_type');
    }

    private function competitionType(int $gameType): string
    {
        return match ($gameType) {
            1 => 'tournament',
            2 => 'jackpot',
            default => 'other',
        };
    }
}
