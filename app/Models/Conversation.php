<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_one_id',
        'user_two_id',
        'last_message',
    ];

    // Relationship to the first user in the conversation
    public function userOne()
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    // Relationship to the second user in the conversation
    public function userTwo()
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    // Relationship to the messages in this conversation
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    // Get the latest message in the conversation
    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latest();
    }
}
