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
     * A single game counts once per game wallet the customer staked in; a tournament or jackpot
     * counts once per competition wallet the customer entered.
     */
    public function handle(): int
    {
        $day = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        $from = $day->copy()->startOfDay();
        $to = $day->copy()->endOfDay();
        $limit = max(1, (int) $this->option('limit'));

        $single = DB::table('game_transactions')
            ->where('payment_type', 'deposit')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('customer_id')
            ->selectRaw('customer_id, COUNT(DISTINCT game_wallet_id) as single_games, 0 as tournaments, 0 as jackpots');

        $competitions = DB::table('competition_transactions as ct')
            ->join('competition_wallets as cw', 'cw.id', '=', 'ct.competition_wallet_id')
            ->whereNotIn('ct.payment_type', ['payout', 'win', 'loss'])
            ->whereBetween('ct.created_at', [$from, $to])
            ->groupBy('ct.customer_id')
            ->selectRaw('ct.customer_id, 0 as single_games,
                COUNT(DISTINCT CASE WHEN cw.game_type = 1 THEN cw.id END) as tournaments,
                COUNT(DISTINCT CASE WHEN cw.game_type = 2 THEN cw.id END) as jackpots');

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
