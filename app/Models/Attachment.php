<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'file_url',
        'file_type',
        'source_type',
        'file_hash',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}

