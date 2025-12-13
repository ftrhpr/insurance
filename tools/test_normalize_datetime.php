<?php
// Test script for the date normalization helper.
// We don't include api.php because it contains the API router and side-effects.
// The helper logic has been copied here for independent testing.

function normalizeDateTimeForDB_test($date, $time = null) {
    if (empty($date)) return null;
    if (strpos($date, 'T') !== false) {
        $date = str_replace('T', ' ', $date);
    }
    if (!empty($time) && strpos($date, ' ') === false) {
        $date = trim($date) . ' ' . trim($time);
    }
    if (strpos($date, ' ') === false) {
        $date .= ' 10:00:00';
    }
    $ts = strtotime($date);
    if ($ts === false) return null;
    return date('Y-m-d H:i:s', $ts);
}

$tests = [
    ['2025-12-12', null],
    ['2025-12-12T14:30', null],
    ['2025-12-12 14:30', null],
    ['', null],
    ['not-a-date', null],
    ['2025-12-12', '15:00'],
    ['2025-12-12T15:00', ''],
];

foreach ($tests as $t) {
    $d = $t[0];
    $time = $t[1];
    $res = normalizeDateTimeForDB_test($d, $time);
    echo "Input: date={$d} time={$time} => normalized: " . ($res === null ? 'null' : $res) . PHP_EOL;
}

?>