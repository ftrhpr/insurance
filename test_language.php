<?php
require_once 'language.php';

// Test different languages
$testLanguages = ['en', 'ka', 'ru'];

echo "<h1>Language System Test</h1>";
echo "<p>Current language: " . Language::getCurrentLanguage() . "</p>";

foreach ($testLanguages as $lang) {
    echo "<h2>Testing language: $lang</h2>";

    // Temporarily switch language
    $original = Language::getCurrentLanguage();
    Language::init($lang);

    echo "<ul>";
    echo "<li>App Title: " . Language::get('app.title') . "</li>";
    echo "<li>Dashboard: " . Language::get('navigation.dashboard') . "</li>";
    echo "<li>Save Button: " . Language::get('common.save') . "</li>";
    echo "<li>Status - Completed: " . Language::get('status.completed') . "</li>";
    echo "</ul>";

    // Switch back
    Language::init($original);
}

echo "<h2>Available Languages:</h2>";
echo "<pre>";
print_r(Language::getAvailableLanguages());
echo "</pre>";
?>