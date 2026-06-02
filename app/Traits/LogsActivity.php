<?php

namespace App\Traits;

use App\Models\ActivityLog;

trait LogsActivity
{
    protected function logActivity(string $action, ?object $entity = null, array $metadata = []): void
    {
        ActivityLog::create([
            'user_id'     => auth()->id(),
            'action'      => $action,
            'entity_type' => $entity ? class_basename($entity) : null,
            'entity_id'   => $entity?->id,
            'metadata'    => !empty($metadata) ? $metadata : null,
            'ip_address'  => request()->ip(),
        ]);
    }
}