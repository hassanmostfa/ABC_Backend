<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComplaintCommunication extends Model
{
    protected $fillable = [
        'complaint_id',
        'direction',
        'channel',
        'subject',
        'body',
        'is_authorized',
        'is_unauthorized_flagged',
        'recipient',
        'sent_by',
    ];

    protected $casts = [
        'is_authorized' => 'boolean',
        'is_unauthorized_flagged' => 'boolean',
    ];

    public function complaint()
    {
        return $this->belongsTo(Complaint::class);
    }

    public function sentBy()
    {
        return $this->belongsTo(Admin::class, 'sent_by');
    }
}
