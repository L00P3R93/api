<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TopPlayersToday extends Command
{
    protected $signature = 'players:top {--date= : Day to report on (Y-m-d), defaults to today} {--limit=10}';

    protected $description = 'Show the players with the most games played today (single games, tournaments and jackpots)';

    /**
     * Mirrors StatsService::playedByPlayerStats() for every customer at once: a single game counts
     * when 2-4 players staked in it, and a competition counts for the tournament (3-5 rounds) and
     * jackpot (13, 17, 21 rounds) round sets that endpoint reports.
     */
    public function handle(): int
    {
        $day = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        $from = $day->copy()->startOfDay();
        $to = $day->copy()->endOfDay();
        $limit = max(1, (int) $this->option('limit'));

        $qualifyingGames = DB::table('game_transactions')
            ->where('payment_type', 'deposit')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('game_wallet_id')
            ->havingRaw('COUNT(DISTINCT customer_id) IN (2, 3, 4)')
            ->select('game_wallet_id');

        $single = DB::table('game_transactions')
            ->where('payment_type', 'deposit')
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('game_wallet_id', $qualifyingGames)
            ->groupBy('customer_id')
            ->selectRaw('customer_id, COUNT(DISTINCT game_wallet_id) as single_games, 0 as tournaments, 0 as jackpots');

        $competitions = DB::table('competition_transactions as ct')
            ->join('competition_wallets as cw', 'cw.id', '=', 'ct.competition_wallet_id')
            ->where('ct.payment_type', '!=', 'payout')
            ->whereBetween('ct.created_at', [$from, $to])
            ->groupBy('ct.customer_id')
            ->selectRaw('ct.customer_id, 0 as single_games,
                COUNT(DISTINCT CASE WHEN cw.game_type = 1 AND cw.jp_rounds IN (3, 4, 5) THEN cw.id END) as tournaments,
                COUNT(DISTINCT CASE WHEN cw.game_type = 2 AND cw.jp_rounds IN (13, 17, 21) THEN cw.id END) as jackpots');

        $rows = DB::query()
            ->fromSub($single->unionAll($competitions), 'p')
            ->leftJoin('customers as c', 'c.id', '=', 'p.customer_id')
            ->groupBy('p.customer_id', 'c.name', 'c.phone_no')
            ->selectRaw('p.customer_id, c.name, c.phone_no,
                SUM(p.single_games) as single_games, SUM(p.tournaments) as tournaments, SUM(p.jackpots) as jackpots,
                SUM(p.single_games + p.tournaments + p.jackpots) as total')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('No games played on '.$day->toDateString().'.');

            return self::SUCCESS;
        }

        $this->info('Top '.$rows->count().' players on '.$day->toDateString());
        $this->table(
            ['#', 'Customer', 'Name', 'Phone', 'Single games', 'Tournaments', 'Jackpots', 'Total'],
            $rows->values()->map(fn (object $row, int $index) => [
                $index + 1,
                $row->customer_id,
                $row->name,
                $row->phone_no,
                (int) $row->single_games,
                (int) $row->tournaments,
                (int) $row->jackpots,
                (int) $row->total,
            ])->all(),
        );

        return self::SUCCESS;
    }
}
