<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComplaintAttachment extends Model
{
    protected $fillable = [
        'complaint_id',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'attachment_type',
        'notes',
        'uploaded_by',
    ];

    public function complaint()
    {
        return $this->belongsTo(Complaint::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(Admin::class, 'uploaded_by');
    }
}
