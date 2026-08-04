<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaderboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $customerArr = [
            'id' => $this->id,
            'name' => $this->name,
        ];

        $singlePlayed = $this->gameWalletTransactions;
        if ($singlePlayed) {
            $customerArr = array_merge($customerArr, [
                'single_wins' => $singlePlayed->where('payment_type', 'payout')->count(),
            ]);
        }

        $competitionPlayed = $this->allCompetitionTransactions;
        if ($competitionPlayed) {
            $customerArr = array_merge($customerArr, [
                'competition_wins' => $competitionPlayed->where('payment_type', 'win')->count(),
            ]);
        }
        return $customerArr;
    }
}
