<?php
// includes/footer.php - Shared footer and scripts
?>
        </main>
    </div>

    <!-- Toast Notification Container -->
    <div id="toast-container" class="fixed bottom-6 right-6 z-50 flex flex-col gap-3 pointer-events-none"></div>

    <!-- Notification Prompt (Banner) -->
    <div id="notification-prompt" class="hidden fixed top-24 right-4 z-50 max-w-sm w-full bg-white border border-slate-200 shadow-2xl rounded-2xl p-4 animate-in slide-in-from-right-10 fade-in duration-500">
        <div class="flex items-start gap-4">
            <div class="bg-primary-50 p-3 rounded-xl">
                <i data-lucide="bell-ring" class="w-6 h-6 text-primary-600"></i>
            </div>
            <div class="flex-1">
                <h3 class="font-bold text-slate-800 text-sm">Enable Notifications</h3>
                <p class="text-xs text-slate-500 mt-1 leading-relaxed">Don't miss out! Get instant alerts when new transfers or messages arrive.</p>
                <div class="mt-3 flex gap-2">
                    <button onclick="window.enableNotifications()" class="flex-1 bg-slate-900 text-white px-4 py-2 rounded-lg text-xs font-bold hover:bg-slate-800 transition-all shadow-sm">Allow</button>
                    <button onclick="document.getElementById('notification-prompt').remove()" class="px-3 py-2 text-slate-400 hover:text-slate-600 text-xs font-medium transition-colors">Later</button>
                </div>
            </div>
            <button onclick="document.getElementById('notification-prompt').remove()" class="text-slate-300 hover:text-slate-500"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
    <script>
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                document.getElementById('loading-screen').style.opacity = '0';
                setTimeout(() => {
                    document.getElementById('loading-screen').classList.add('hidden');
                    document.getElementById('app-content').classList.remove('hidden');
                    if(window.lucide) lucide.createIcons();
                }, 500);
            }, 300);
        });
    </script>
</body>
</html>
