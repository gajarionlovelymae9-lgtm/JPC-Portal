<?php

namespace App\Http\Resources\Api\V1\Hr;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class HrAchievementCollection extends ResourceCollection
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'items' => $this->collection,
        ];
    }
}
