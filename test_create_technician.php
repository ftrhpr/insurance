<?php
// Test script to create a technician user
require_once 'config.php';

try {
    $pdo = getDBConnection();

    // Check if technician user already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute(['technician_test']);
    if ($stmt->fetch()) {
        echo "Technician test user already exists.\n";
        exit;
    }

    // Create technician user
    $hashed_password = password_hash('tech123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        'technician_test',
        $hashed_password,
        'Test Technician',
        'technician@test.com',
        'technician',
        'active'
    ]);

    echo "✅ Technician user created successfully!\n";
    echo "Username: technician_test\n";
    echo "Password: tech123\n";
    echo "Role: technician\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>