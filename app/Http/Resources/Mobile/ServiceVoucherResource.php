<?php

namespace App\Http\Resources\Mobile;

use App\Traits\ManagesFileUploads;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceVoucherResource extends JsonResource
{
    use ManagesFileUploads;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'service_name' => $this->service_name,
            'amount' => (float) $this->amount,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'service' => $this->when($this->relationLoaded('service') && $this->service, function () {
                return [
                    'id' => $this->service->id,
                    'name' => $this->service->name,
                    'image' => $this->getFileUrl($this->service->image, 'public', 'no-image.png'),
                    'price' => (float) $this->service->price,
                ];
            }),
            'created_at' => \format_datetime_app_tz($this->created_at),
        ];
    }
}
