<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SettingTranslation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'setting_id',
        'locale',
        'value',
    ];

    /**
     * Get the setting that owns the translation.
     */
    public function setting()
    {
        return $this->belongsTo(Setting::class);
    }
}
