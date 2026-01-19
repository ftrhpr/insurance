<?php
$config = include 'config.php';
try {
    $pdo = new PDO(
        'mysql:host='.$config['db_host'].';dbname='.$config['db_name'].';charset=utf8mb4',
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $sql = file_get_contents('add_payments_table.sql');
    $pdo->exec($sql);
    echo 'Payments table created successfully!';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>