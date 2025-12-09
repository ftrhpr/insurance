<?php
require_once 'config.php';

try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    echo "DB connect failed: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

echo "Connected to DB\n";

// Test JSON support
$use_json = false;
try {
    $res = $pdo->query("SELECT JSON_EXTRACT('[{\"vendor\":\"x\"}]', '$[0].vendor') AS v");
    $val = $res->fetchColumn();
    if ($val !== false) {
        echo "JSON functions appear to be supported. Sample JSON_EXTRACT returned: " . $val . PHP_EOL;
        $use_json = true;
    }
} catch (Exception $ex) {
    echo "JSON functions not supported (or test query failed): " . $ex->getMessage() . PHP_EOL;
    $use_json = false;
}

// Count transfers with parts
try {
    if ($use_json) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM transfers WHERE JSON_LENGTH(parts) > 0");
    } else {
        $stmt = $pdo->query("SELECT COUNT(*) FROM transfers WHERE parts IS NOT NULL AND parts <> '[]'");
    }
    $count = (int)$stmt->fetchColumn();
    echo "Transfers with parts: $count\n";
} catch (Exception $e) {
    echo "Failed to count transfers with parts: " . $e->getMessage() . PHP_EOL;
}

// Try a vendor search example
$vendor = 'ACME';
try {
    if ($use_json) {
        $ps = $pdo->prepare("SELECT COUNT(*) FROM transfers WHERE JSON_CONTAINS(parts, JSON_QUOTE(?), '$[*].vendor') OR parts LIKE ?");
        $ps->execute([$vendor, '%' . $vendor . '%']);
    } else {
        $ps = $pdo->prepare("SELECT COUNT(*) FROM transfers WHERE parts LIKE ?");
        $ps->execute(['%' . $vendor . '%']);
    }
    $vcount = (int)$ps->fetchColumn();
    echo "Transfers matching vendor '$vendor': $vcount\n";
} catch (Exception $e) {
    echo "Vendor search failed: " . $e->getMessage() . PHP_EOL;
}

// Try export simulation (fetch first 5 rows)
try {
    $sql = "SELECT id, plate, parts FROM transfers WHERE " . ($use_json ? "JSON_LENGTH(parts) > 0" : "parts IS NOT NULL AND parts <> '[]'") . " ORDER BY id DESC LIMIT 5";
    $r = $pdo->query($sql);
    $sample = $r->fetchAll(PDO::FETCH_ASSOC);
    echo "Sample export rows: " . count($sample) . "\n";
    foreach ($sample as $row) {
        echo "ID: {$row['id']} Plate: {$row['plate']} PartsLen: " . strlen($row['parts']) . "\n";
    }
} catch (Exception $e) {
    echo "Export simulation failed: " . $e->getMessage() . PHP_EOL;
}

// Additional debug: show first 10 transfers and raw parts content to diagnose empty results
try {
    echo "\n--- Debug: first 10 transfers (raw parts) ---\n";
    $dbg = $pdo->query("SELECT id, plate, parts FROM transfers ORDER BY id DESC LIMIT 10");
    $rowsDbg = $dbg->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rowsDbg as $r) {
        $raw = $r['parts'];
        $len = is_null($raw) ? 'NULL' : strlen($raw);
        echo "ID: {$r['id']} Plate: {$r['plate']} PartsRawLen: {$len}\n";
        echo "PartsRaw: ";
        var_export($raw);
        echo "\n";
        $decoded = json_decode($raw, true);
        echo "json_decode => ";
        var_export($decoded);
        echo "\n---\n";
    }
} catch (Exception $e) {
    echo "Debug fetch failed: " . $e->getMessage() . PHP_EOL;
}

// Show column type for parts
try {
    $col = $pdo->prepare("SELECT COLUMN_TYPE, DATA_TYPE FROM information_schema.columns WHERE table_schema = ? AND table_name = 'transfers' AND column_name = 'parts'");
    $dbName = defined('DB_NAME') ? DB_NAME : '';
    $col->execute([$dbName]);
    $ctype = $col->fetch(PDO::FETCH_ASSOC);
    echo "\nparts column info: ";
    var_export($ctype);
    echo "\n";
} catch (Exception $e) {
    echo "Failed to read information_schema for parts column: " . $e->getMessage() . PHP_EOL;
}

echo "Smoke test complete.\n";

?>