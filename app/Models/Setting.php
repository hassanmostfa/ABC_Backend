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
    const TRANSLATABLE_KEYS = ['about', 'terms_and_conditions', 'subscription_terms_and_conditions', 'app_ordering_disabled_message'];

    /**
     * Keys whose value is a JSON array of email addresses
     */
    const EMAIL_LIST_KEYS = ['complaint_action_emails'];

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

    /**
     * Parse a stored email list (JSON array, or comma/newline separated string)
     * into unique, trimmed, lowercase addresses.
     *
     * @return array<int, string>
     */
    public static function parseEmailList(?string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        $items = is_array($decoded) ? $decoded : preg_split('/[,;\s]+/', $raw);

        $emails = array_map(fn ($email) => is_string($email) ? strtolower(trim($email)) : '', $items);

        return array_values(array_unique(array_filter($emails)));
    }

    /**
     * Get an email list setting; falls back to $default only when the setting row does not exist.
     *
     * @param array<int, string> $default
     * @return array<int, string>
     */
    public static function getEmailList(string $key, array $default = []): array
    {
        $setting = self::where('key', $key)->first();

        return $setting ? self::parseEmailList($setting->value) : $default;
    }
}
