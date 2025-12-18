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


                                        left: 'prev,next today',
                                        center: 'title',
                                        right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                                    },
                                    events: [
                                        <?php foreach ($cases as $c): ?>
                                        {
                                            title: '<?php echo addslashes($c['plate'] . ' - ' . $c['name']); ?>',
                                            start: '<?php echo date('Y-m-d\TH:i:s', strtotime($c['due_date'])); ?>',
                                            url: 'edit_case.php?id=<?php echo $c['id']; ?>',
                                            color: '<?php echo ($c['status'] === 'Completed') ? '#22c55e' : (($c['status'] === 'Issue') ? '#ef4444' : ((strtotime($c['due_date']) < strtotime('today')) ? '#f59e42' : '#2563eb')); ?>',
                                            extendedProps: {
                                                status: '<?php echo addslashes($c['status']); ?>',
                                                phone: '<?php echo addslashes($c['phone']); ?>',
                                                amount: '<?php echo addslashes($c['amount']); ?>',
                                                serviceDate: '<?php echo addslashes($c['service_date']); ?>'
                                            }
                                        },
                                        <?php endforeach; ?>
                                    ],
                                    eventClick: function(info) {
                                        info.jsEvent.preventDefault();
                                        if (info.event.url) {
                                            window.location.href = info.event.url;
                                        }
                                    },
                                    eventDidMount: function(info) {
                                        // Tooltip for event details
                                        var tooltip = document.createElement('div');
                                        tooltip.className = 'absolute z-50 p-2 bg-white border border-slate-200 rounded shadow text-xs text-slate-700 hidden';
                                        tooltip.innerHTML =
                                            '<div><b>Plate:</b> ' + info.event.title.split(' - ')[0] + '</div>' +
                                            '<div><b>Customer:</b> ' + info.event.title.split(' - ')[1] + '</div>' +
                                            '<div><b>Status:</b> ' + info.event.extendedProps.status + '</div>' +
                                            '<div><b>Due:</b> ' + info.event.start.toLocaleString() + '</div>' +
                                            '<div><b>Phone:</b> ' + info.event.extendedProps.phone + '</div>' +
                                            '<div><b>Amount:</b> ' + info.event.extendedProps.amount + '₾</div>' +
                                            '<div><b>Service Date:</b> ' + (info.event.extendedProps.serviceDate ? info.event.extendedProps.serviceDate : '-') + '</div>';
                                        document.body.appendChild(tooltip);
                                        info.el.addEventListener('mouseenter', function(e) {
                                            tooltip.style.left = (e.pageX + 10) + 'px';
                                            tooltip.style.top = (e.pageY + 10) + 'px';
                                            tooltip.classList.remove('hidden');
                                        });
                                        info.el.addEventListener('mousemove', function(e) {
                                            tooltip.style.left = (e.pageX + 10) + 'px';
                                            tooltip.style.top = (e.pageY + 10) + 'px';
                                        });
                                        info.el.addEventListener('mouseleave', function() {
                                            tooltip.classList.add('hidden');
                                        });
                                    }
                                });
                                calendar.render();
                                // Stats
                                var total = <?php echo count($cases); ?>;
                                var today = 0, overdue = 0, upcoming = 0;
                                var now = new Date();
                                <?php foreach ($cases as $c): ?>
                                    (function(){
                                        var due = new Date('<?php echo date('Y-m-d\TH:i:s', strtotime($c['due_date'])); ?>');
                                        if (due.toDateString() === now.toDateString()) today++;
                                        else if (due < now) overdue++;
                                        else upcoming++;
                                    })();
                                <?php endforeach; ?>
                                document.getElementById('stat-total-cases').textContent = total;
                                document.getElementById('stat-due-today').textContent = today;
                                document.getElementById('stat-overdue').textContent = overdue;
                                document.getElementById('stat-upcoming').textContent = upcoming;
                                if(window.lucide) lucide.createIcons();
                            });
                        </script>
                    </body>
                    </html>
