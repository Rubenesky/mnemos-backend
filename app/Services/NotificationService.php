<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;

/**
 * Creates internal notification records for users.
 */
class NotificationService
{
    /**
     * Create a notification for a single recipient.
     */
    public function notify(User $recipient, string $type, array $data): void
    {
        AppNotification::create([
            'user_id' => $recipient->id,
            'type' => $type,
            'data' => $data,
            'read_at' => null,
        ]);
    }

    /**
     * Create a notification for every user with role=admin.
     */
    public function notifyAdmins(string $type, array $data): void
    {
        User::where('role', 'admin')->each(function (User $admin) use ($type, $data) {
            $this->notify($admin, $type, $data);
        });
    }
}
