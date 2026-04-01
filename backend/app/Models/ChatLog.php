<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'question',
        'response',
        'visitor_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }
}
