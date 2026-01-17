<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'model',
        'system_prompt',
        'last_message',
        'token_count',
        'query_type',
        'temperature_used',
        'model_version',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function messages()
    {
        return $this->hasMany(AiMessage::class);
    }
}

