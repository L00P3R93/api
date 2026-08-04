<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompetitionWalletResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'competition_id' => $this->competition_id,
            'game_type' => $this->game_type,
            'customer_id' => $this->customer_id,
            'level' => $this->level,
            'balance' => $this->balance,
            'status' => $this->status,
        ];
    }
}
