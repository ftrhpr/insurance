<?php
// Clean up SMS templates to remove extra text/signatures
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

echo "<h2>SMS Templates Cleanup Tool</h2>";
echo "<p>Removing extra text and signatures from SMS templates...</p>";
echo "<hr>";

// Database credentials
$db_host = 'localhost';
$db_name = 'otoexpre_userdb';
$db_user = 'otoexpre_userdb';
$db_pass = 'p52DSsthB}=0AeZ#';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Database connection successful<br><br>";

    // Clean templates - remove signatures and extra text
    $cleanTemplates = [
        'registered' => 'გამარჯობა {name}, თქვენი სერვისის რეგისტრაცია მოხდა. ავტომობილი: {plate}. თანხა: {amount}₾',
        'called' => 'გამარჯობა {name}, დაგიკავშირდით ჩვენი მენეჯერი. ავტომობილი: {plate}',
        'contacted' => 'გამარჯობა {name}, თქვენ დაგიკავშირდით. ავტომობილი: {plate}. მალე მოგაწვდით დეტალურ ინფორმაციას.',
        'schedule' => 'გამარჯობა {name}, თქვენი სერვისის თარიღი: {date}. ავტომობილი: {plate}. დაადასტურეთ ან გადაავადეთ: {link}',
        'parts_ordered' => 'გამარჯობა {name}, თქვენი ნაწილები შეკვეთილია. ავტომობილი: {plate}',
        'parts_arrived' => 'გამარჯობა {name}, თქვენი ნაწილები მივიდა. დაადასტურეთ თქვენი ვიზიტი: {link}',
        'rescheduled' => 'გამარჯობა, კლიენტმა {name} მოითხოვა თარიღის შეცვლა. ავტომობილი: {plate}',
        'reschedule_accepted' => 'გამარჯობა {name}, თქვენი თარიღის შეცვლის მოთხოვნა მიღებულია. ახალი თარიღი: {date}',
        'completed' => 'გამარჯობა {name}, თქვენი სერვისი დასრულდა. გთხოვთ შეაფასოთ ჩვენი მომსახურება',
        'issue' => 'გამარჯობა {name}, დაფიქსირდა პრობლემა. ავტომობილი: {plate}. ჩვენ დაგიკავშირდებით.',
        'system' => 'სისტემური შეტყობინება: {count} ახალი განაცხადი დაემატა OTOMOTORS პორტალში.'
    ];

    echo "<h3>Updating SMS Templates:</h3>";
    foreach ($cleanTemplates as $slug => $content) {
        $stmt = $pdo->prepare("UPDATE sms_templates SET content = ? WHERE slug = ?");
        $stmt->execute([$content, $slug]);
        echo "✓ Updated {$slug}<br>";
    }

    echo "<br><h3>Cleanup Complete!</h3>";
    echo "<p>All SMS templates now contain only template content without extra signatures or promotional text.</p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>