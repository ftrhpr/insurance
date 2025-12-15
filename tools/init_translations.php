<?php
// Run this script from CLI (php tools/init_translations.php) to initialize default translations in the DB.
require_once __DIR__ . '/../language.php';

$ok = initialize_default_translations();
if ($ok) {
    echo "Default translations initialized successfully.\n";
} else {
    echo "Failed to initialize translations. Check error logs.\n";
}
