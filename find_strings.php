<?php
// Simple test script to find translation usages across the project
require_once 'config.php';

try {
    // Simulate admin session for testing
    $_SESSION['role'] = 'admin';

    // Test the find_translation_usages endpoint
    $baseDir = __DIR__;
    $excludeDirs = ['.git', 'vendor', 'fonts', 'node_modules'];
    $allowedExt = ['php','html','js','css','txt','tpl','phtml'];

    $matches = [];
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));

    echo "Searching for translation keys and hardcoded strings...\n\n";

    // Search for translation function calls
    echo "=== TRANSLATION FUNCTION CALLS ===\n";
    $translationMatches = [];
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));
    foreach ($rii as $file) {
        if ($file->isDir()) continue;
        $filePath = $file->getPathname();
        // Exclude paths
        $skip = false;
        foreach ($excludeDirs as $ex) {
            if (strpos($filePath, DIRECTORY_SEPARATOR.$ex.DIRECTORY_SEPARATOR) !== false) {
                $skip = true; break;
            }
        }
        if ($skip) continue;

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt)) continue;

        $lines = @file($filePath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) continue;

        foreach ($lines as $i => $line) {
            // Look for __() function calls
            if (preg_match('/__\([^)]+\)/', $line)) {
                $translationMatches[] = [
                    'file' => substr($filePath, strlen($baseDir)+1),
                    'line' => $i+1,
                    'context' => trim($line)
                ];
            }
        }
    }

    echo "Found " . count($translationMatches) . " translation function calls:\n";
    foreach (array_slice($translationMatches, 0, 20) as $match) {
        echo "- {$match['file']}:{$match['line']} - {$match['context']}\n";
    }
    if (count($translationMatches) > 20) {
        echo "... and " . (count($translationMatches) - 20) . " more\n";
    }

    echo "\n=== HARDCODED UI STRINGS ===\n";

    // Search for common hardcoded strings
    $hardcodedPatterns = [
        'Save Changes',
        'Edit',
        'Cancel',
        'Delete',
        'Add New',
        'Remove',
        'Update',
        'Create',
        'Submit',
        'Success',
        'Error',
        'Warning',
        'Info',
        'Loading',
        'Please wait',
        'No data',
        'Not found',
        'Name',
        'Phone',
        'Email',
        'Address',
        'Status',
        'Date',
        'Time',
        'Amount',
        'Description'
    ];

    $hardcodedMatches = [];
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));
    foreach ($rii as $file) {
        if ($file->isDir()) continue;
        $filePath = $file->getPathname();
        // Exclude paths
        $skip = false;
        foreach ($excludeDirs as $ex) {
            if (strpos($filePath, DIRECTORY_SEPARATOR.$ex.DIRECTORY_SEPARATOR) !== false) {
                $skip = true; break;
            }
        }
        if ($skip) continue;

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt)) continue;

        $lines = @file($filePath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) continue;

        foreach ($lines as $i => $line) {
            foreach ($hardcodedPatterns as $pattern) {
                if (stripos($line, $pattern) !== false && !preg_match('/__\([^)]*' . preg_quote($pattern, '/') . '[^)]*\)/', $line)) {
                    // Check if it's not already in a translation call
                    $hardcodedMatches[] = [
                        'file' => substr($filePath, strlen($baseDir)+1),
                        'line' => $i+1,
                        'text' => $pattern,
                        'context' => trim($line)
                    ];
                    break; // Only count once per line
                }
            }
        }
    }

    // Group by text
    $grouped = [];
    foreach ($hardcodedMatches as $match) {
        $key = $match['text'];
        if (!isset($grouped[$key])) {
            $grouped[$key] = [];
        }
        $grouped[$key][] = $match;
    }

    foreach ($grouped as $text => $matches) {
        echo "\n--- '$text' (" . count($matches) . " occurrences) ---\n";
        foreach (array_slice($matches, 0, 5) as $match) {
            echo "- {$match['file']}:{$match['line']} - {$match['context']}\n";
        }
        if (count($matches) > 5) {
            echo "... and " . (count($matches) - 5) . " more\n";
        }
    }

    echo "\n=== SUMMARY ===\n";
    echo "Translation function calls: " . count($translationMatches) . "\n";
    echo "Hardcoded strings found: " . count($hardcodedMatches) . "\n";
    echo "Unique hardcoded strings: " . count($grouped) . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>