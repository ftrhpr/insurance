<?php
// calendar.php - Calendar view for cases with due dates
session_start();
require_once 'config.php';
require_once 'language.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch all cases with due dates
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->query("SELECT id, plate, name, due_date, status FROM transfers WHERE due_date IS NOT NULL ORDER BY due_date ASC");
    $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

?><!DOCTYPE html>
<html lang="<?php echo get_current_language(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('calendar.title', 'Due Date Calendar'); ?> - OTOMOTORS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.378.0/dist/umd/lucide.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
    <style>
        .fc-event { cursor: pointer; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <div class="max-w-5xl mx-auto py-8 px-4">
        <h1 class="text-2xl font-bold mb-6 flex items-center gap-2">
            <i data-lucide="calendar" class="w-7 h-7 text-blue-600"></i>
            <?php echo __('calendar.title', 'Due Date Calendar'); ?>
        </h1>
        <div id="calendar"></div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                height: 700,
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: [
                    <?php foreach ($cases as $c): ?>
                    {
                        title: '<?php echo addslashes($c['plate'] . ' - ' . $c['name']); ?>',
                        start: '<?php echo date('Y-m-d\TH:i:s', strtotime($c['due_date'])); ?>',
                        url: 'edit_case.php?id=<?php echo $c['id']; ?>',
                        color: '<?php echo ($c['status'] === 'Completed') ? '#22c55e' : (($c['status'] === 'Issue') ? '#ef4444' : '#2563eb'); ?>',
                    },
                    <?php endforeach; ?>
                ],
                eventClick: function(info) {
                    info.jsEvent.preventDefault();
                    if (info.event.url) {
                        window.location.href = info.event.url;
                    }
                }
            });
            calendar.render();
            if(window.lucide) lucide.createIcons();
        });
    </script>
</body>
</html>
