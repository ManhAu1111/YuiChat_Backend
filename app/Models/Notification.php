<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification as BaseDatabaseNotification;

/**
 * Custom Notification model explicitly defining the notifications table.
 * By extending Laravel's BaseDatabaseNotification, we ensure compatibility 
 * with $user->notifications() and $user->unreadNotifications().
 */
class Notification extends BaseDatabaseNotification
{
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'id' => 'string',
    ];

    // Bạn có thể thêm các relashipships, scopes hoặc custom methods ở đây
    // Ví dụ: Scope để lấy các thông báo thuộc một module cụ thể
}
