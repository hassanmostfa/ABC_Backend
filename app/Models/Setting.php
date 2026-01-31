<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Setting extends Model
{
    use HasFactory;

    /**
     * Keys that use translations (en/ar)
     */
    const TRANSLATABLE_KEYS = ['about', 'terms_and_conditions'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Get the translations for the setting.
     */
    public function translations()
    {
        return $this->hasMany(SettingTranslation::class);
    }

    /**
     * Get a setting value by key (for non-translatable settings)
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getValue(string $key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Get a translated setting value by key and locale
     *
     * @param string $key
     * @param string $locale (en|ar)
     * @param mixed $default
     * @return mixed
     */
    public static function getTranslatedValue(string $key, string $locale = 'en', $default = null)
    {
        $setting = self::with('translations')->where('key', $key)->first();

        if (!$setting) {
            return $default;
        }

        if (in_array($key, self::TRANSLATABLE_KEYS)) {
            $translation = $setting->translations->firstWhere('locale', $locale);
            return $translation?->value ?? $default;
        }

        return $setting->value ?? $default;
    }
}
