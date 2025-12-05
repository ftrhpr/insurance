<?php
// setup.php - System setup page
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Required - OTOMOTORS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-2xl w-full bg-white rounded-2xl shadow-2xl p-8">
        <div class="text-center mb-6">
            <div class="bg-orange-100 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4">
                <i data-lucide="alert-triangle" class="w-10 h-10 text-orange-600"></i>
            </div>
            <h1 class="text-3xl font-bold text-slate-800 mb-2">System Setup Required</h1>
            <p class="text-slate-600">The database needs to be initialized before you can use the portal.</p>
        </div>
        
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
            <h3 class="font-bold text-blue-900 mb-3 flex items-center gap-2">
                <i data-lucide="list-checks" class="w-5 h-5"></i>
                Setup Steps:
            </h3>
            <ol class="list-decimal list-inside space-y-2 text-blue-800">
                <li>First, test your database connection: <a href="test_db_connection.php" class="underline font-bold">test_db_connection.php</a></li>
                <li>Then, run the database setup: <a href="fix_db_all.php" class="underline font-bold">fix_db_all.php</a></li>
                <li>Finally, <a href="login.php" class="underline font-bold">login here</a></li>
            </ol>
        </div>
        
        <div class="bg-slate-50 rounded-lg p-6">
            <h3 class="font-bold text-slate-800 mb-3">Default Credentials:</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-xs text-slate-500 mb-1">Username</p>
                    <p class="font-mono font-bold text-slate-900">admin</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 mb-1">Password</p>
                    <p class="font-mono font-bold text-slate-900">admin123</p>
                </div>
            </div>
        </div>
        
        <div class="mt-6 flex gap-4">
            <a href="test_db_connection.php" class="flex-1 bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors text-center">
                Test Database
            </a>
            <a href="fix_db_all.php" class="flex-1 bg-slate-900 text-white px-6 py-3 rounded-lg font-semibold hover:bg-slate-800 transition-colors text-center">
                Setup Database
            </a>
        </div>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
