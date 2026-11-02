<?php

namespace App\Http\Resources;

use App\Models\OrderJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderJob
 */
class OrderJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'title' => $this->title,
            'status' => $this->status,
            // Installers can't open orders, so the job carries what they need to reach the customer
            'customer' => $this->whenLoaded('order', fn () => [
                'name' => $this->order->customer_name,
                'phone' => $this->order->customer_phone,
                'address' => $this->order->address,
            ]),
            'installer' => new UserResource($this->whenLoaded('installer')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
