<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'total' => $this->total,
            'created_at' => $this->created_at?->copy()->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'details' => SaleDetailResource::collection($this->whenLoaded('details')),
        ];
    }
}
