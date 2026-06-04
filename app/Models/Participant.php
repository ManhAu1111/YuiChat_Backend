<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\HybridRelations;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Message;

class Participant extends Model
{
    use HasFactory;
    use HybridRelations;

    // Override getConnectionName to ensure it always uses the default SQL connection (mariadb/sqlite)
    // instead of inheriting the mongodb connection from HybridRelations when queried via Message
    public function getConnectionName()
    {
        return config('database.default');
    }

    public $timestamps = false;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'role',
        'last_read_message_id',
        'last_delivered_message_id',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lastReadMessage()
    {
        return $this->belongsTo(Message::class, 'last_read_message_id');
    }

    public function lastDeliveredMessage()
    {
        return $this->belongsTo(Message::class, 'last_delivered_message_id');
    }
}

