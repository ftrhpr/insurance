<?php
/**
 * Language Management System
 * Handles loading and accessing language strings
 */

class Language {
    private static $language = 'en';
    private static $strings = [];
    private static $loaded = false;

    /**
     * Initialize language system
     */
    public static function init($language = null) {
        if ($language) {
            self::$language = $language;
        } elseif (isset($_SESSION['language'])) {
            self::$language = $_SESSION['language'];
        } elseif (isset($_GET['lang'])) {
            self::$language = $_GET['lang'];
            $_SESSION['language'] = self::$language;
        }

        self::loadLanguage();
    }

    /**
     * Load language file
     */
    private static function loadLanguage() {
        $langFile = __DIR__ . '/languages/' . self::$language . '.json';

        if (file_exists($langFile)) {
            $content = file_get_contents($langFile);
            self::$strings = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // Fallback to English if JSON is invalid
                self::$language = 'en';
                self::loadLanguage();
            }
        } else {
            // Fallback to English if language file doesn't exist
            self::$language = 'en';
            $fallbackFile = __DIR__ . '/languages/en.json';
            if (file_exists($fallbackFile)) {
                self::$strings = json_decode(file_get_contents($fallbackFile), true);
            }
        }

        self::$loaded = true;
    }

    /**
     * Get a language string by key
     * Supports dot notation: 'app.title', 'common.save'
     */
    public static function get($key, $default = '') {
        if (!self::$loaded) {
            self::init();
        }

        $keys = explode('.', $key);
        $value = self::$strings;

        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default ?: $key;
            }
        }

        return $value;
    }

    /**
     * Get current language code
     */
    public static function getCurrentLanguage() {
        return self::$language;
    }

    /**
     * Get available languages
     */
    public static function getAvailableLanguages() {
        $languages = [];
        $langDir = __DIR__ . '/languages/';

        if (is_dir($langDir)) {
            $files = glob($langDir . '*.json');
            foreach ($files as $file) {
                $code = basename($file, '.json');
                $languages[$code] = self::getLanguageName($code);
            }
        }

        return $languages;
    }

    /**
     * Get language name from code
     */
    private static function getLanguageName($code) {
        $names = [
            'en' => 'English',
            'ka' => 'ქართული (Georgian)',
            'ru' => 'Русский (Russian)',
            'de' => 'Deutsch (German)',
            'fr' => 'Français (French)',
            'es' => 'Español (Spanish)',
            'it' => 'Italiano (Italian)',
            'tr' => 'Türkçe (Turkish)',
            'ar' => 'العربية (Arabic)',
            'zh' => '中文 (Chinese)',
            'ja' => '日本語 (Japanese)',
            'ko' => '한국어 (Korean)'
        ];

        return $names[$code] ?? ucfirst($code);
    }

    /**
     * Save language strings
     */
    public static function saveLanguage($language, $strings) {
        $langFile = __DIR__ . '/languages/' . $language . '.json';

        // Ensure directory exists
        $langDir = dirname($langFile);
        if (!is_dir($langDir)) {
            mkdir($langDir, 0755, true);
        }

        $json = json_encode($strings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return file_put_contents($langFile, $json) !== false;
    }

    /**
     * Get all strings for current language
     */
    public static function getAllStrings() {
        if (!self::$loaded) {
            self::init();
        }
        return self::$strings;
    }

    /**
     * Set a specific language string
     */
    public static function setString($key, $value) {
        $keys = explode('.', $key);
        $array = &self::$strings;

        foreach ($keys as $k) {
            if (!isset($array[$k])) {
                $array[$k] = [];
            }
            $array = &$array[$k];
        }

        $array = $value;

        // Save to file
        self::saveLanguage(self::$language, self::$strings);
    }

    /**
     * Delete a language
     */
    public static function deleteLanguage($language) {
        $langFile = __DIR__ . '/languages/' . $language . '.json';
        if (file_exists($langFile) && $language !== 'en') {
            return unlink($langFile);
        }
        return false;
    }
}

// Initialize language system
Language::init();
?>