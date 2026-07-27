<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'unread_count' => $this->resource['unread_count'],
            'unread_by_category' => $this->resource['unread_by_category'],
            'high_priority_unread_count' => $this->resource['high_priority_unread_count'],
            'critical_unread_count' => $this->resource['critical_unread_count'],
            'latest_notification_at' => $this->resource['latest_notification_at'],
            'archived_count' => $this->resource['archived_count'],
        ];
    }
}
