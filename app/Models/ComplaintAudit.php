<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComplaintAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'complaint_id',
        'field_name',
        'old_value',
        'new_value',
        'changed_by',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function complaint()
    {
        return $this->belongsTo(Complaint::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(Admin::class, 'changed_by');
    }
}
