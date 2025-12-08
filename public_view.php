<!DOCTYPE html>
<?php
// Safely include language system with error handling
try {
    require_once 'language.php';
} catch (Exception $e) {
    // Define fallback function if language system fails
    if (!function_exists('Language::get')) {
        class Language {
            public static function get($key, $default = '') {
                // Basic fallback translations
                $fallbacks = [
                    'app.title' => 'OTOMOTORS Manager Portal',
                    'public_view.loading' => 'Loading...',
                    'public_view.service_appointment' => 'Service Appointment',
                    'public_view.otomotors_service_center' => 'OTOMOTORS Service Center',
                    'public_view.customer' => 'Customer',
                    'public_view.order_id' => 'Order ID',
                    'public_view.scheduled_time' => 'Scheduled Time',
                    'public_view.confirm_accept' => 'Confirm & Accept',
                    'public_view.request_another_time' => 'Request Another Time',
                    'public_view.response_recorded' => 'Response Recorded',
                    'public_view.thank_you_response' => 'Thank you for letting us know.',
                    'public_view.current_status' => 'Current Status',
                    'public_view.see_you_soon' => 'See you soon! Get directions here:',
                    'public_view.open_google_maps' => 'Open in Google Maps App',
                    'public_view.service_complete' => 'Your service is complete. How did we do?',
                    'public_view.tell_us_experience' => 'Tell us about your experience...',
                    'public_view.submit_review' => 'Submit Review',
                    'public_view.thank_you' => 'Thank You!',
                    'public_view.appreciate_feedback' => 'We appreciate your feedback.',
                    'public_view.call_support' => 'Call Support',
                    'public_view.request_reschedule' => 'Request Reschedule',
                    'public_view.preferred_date_time' => 'Tell us your preferred date and time',
                    'public_view.preferred_date_time_label' => 'Preferred Date & Time',
                    'public_view.additional_comments' => 'Additional Comments (Optional)',
                    'public_view.let_us_know' => 'Let us know any specific requirements or reasons for rescheduling...',
                    'public_view.cancel' => 'Cancel',
                    'public_view.submit_request' => 'Submit Request',
                    'public_view.appointment_not_found' => 'Appointment Not Found',
                    'public_view.link_expired' => 'This link may be expired or invalid.',
                    'public_view.valued_customer' => 'Valued Customer',
                    'public_view.to_be_determined' => 'TBD',
                    'public_view.service_completed' => 'Service Completed'
                ];
                return $fallbacks[$key] ?? $default ?: $key;
            }
        }
    }
}
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Language::get('app.title'); ?> | Service Status</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: '#0ea5e9',
                        success: '#10b981',
                        warning: '#f59e0b',
                        danger: '#ef4444',
                    },
                }
            }
        }
    </script>
    
    <style> 
        .star-rating i { 
            display: inline-block; 
            transition: transform 0.1s, color 0.2s;
            cursor: pointer;
            font-size: 1.75rem;
        }
        
        .star-rating i:hover { 
            transform: scale(1.15);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4 font-sans">

    <!-- Loading State -->
    <div id="loader" class="text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto"></div>
        <p class="mt-3 text-gray-500 font-medium"><?php echo Language::get('public_view.loading'); ?></p>
    </div>

    <!-- Main Card -->
    <div id="card" class="hidden max-w-lg w-full bg-white rounded-2xl shadow-xl overflow-hidden transition-all duration-500" style="opacity:0; transform:scale(0.95);">
        <!-- Header -->
        <div id="header-bg" class="bg-gradient-to-br from-sky-500 to-sky-600 text-white px-6 py-10 text-center relative">
            <div class="relative z-10">
                <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-3 backdrop-blur-sm" id="header-icon-container">
                    <i data-lucide="calendar-check" class="w-8 h-8"></i>
                </div>
                <h1 id="header-title" class="text-2xl font-bold mb-1"><?php echo Language::get('public_view.service_appointment'); ?></h1>
                <p class="opacity-90 text-sm"><?php echo Language::get('public_view.otomotors_service_center'); ?></p>
            </div>
        </div>

        <!-- Content -->
        <div class="p-6">
            <div class="text-center mb-6">
                <h2 id="user-name" class="text-xl font-bold text-gray-800"><?php echo Language::get('public_view.customer'); ?></h2>
                <div class="flex flex-col items-center gap-2 mt-3">
                    <div class="bg-gray-100 px-4 py-2 rounded-full border-2 border-gray-200 flex items-center gap-2">
                        <i data-lucide="car" class="w-4 h-4 text-gray-600"></i>
                        <span id="plate" class="font-bold text-gray-700">---</span>
                    </div>
                    <small class="text-gray-500">
                        <?php echo Language::get('public_view.order_id'); ?>: <span id="order-id" class="font-bold">#0</span>
                    </small>
                </div>
            </div>

            <!-- SCENARIO 1: APPOINTMENT CONFIRMATION (Not Completed) -->
            <div id="appointment-section" class="hidden">
                <!-- Date Display -->
                <div class="bg-sky-50 border-2 border-sky-100 rounded-xl p-4 flex items-center gap-3 mb-4">
                    <div class="bg-white p-3 rounded-lg shadow-sm min-w-[80px] text-center">
                        <small id="date-month" class="block text-xs uppercase font-bold text-sky-600">---</small>
                        <div id="date-day" class="text-3xl font-bold text-sky-600">--</div>
                    </div>
                    <div>
                        <small class="block text-xs uppercase font-bold text-sky-600 mb-1"><?php echo Language::get('public_view.scheduled_time'); ?></small>
                        <div id="date-time" class="text-2xl font-bold text-gray-800">--:--</div>
                    </div>
                </div>

                <!-- Actions -->
                <div id="action-area" class="space-y-3">
                    <button onclick="submitResponse('Confirmed')" class="w-full bg-gradient-to-r from-sky-500 to-sky-600 text-white py-3 px-4 rounded-xl font-bold hover:from-sky-600 hover:to-sky-700 transition-all flex items-center justify-center gap-2">
                        <i data-lucide="check-circle" class="w-5 h-5"></i>
                        <span><?php echo Language::get('public_view.confirm_accept'); ?></span>
                    </button>
                    <button onclick="openRescheduleModal()" class="w-full bg-white border-2 border-gray-200 text-gray-700 py-3 px-4 rounded-xl font-bold hover:bg-gray-50 transition-all">
                        <?php echo Language::get('public_view.request_another_time'); ?>
                    </button>
                </div>

                <!-- Success State -->
                <div id="success-message" class="hidden text-center py-4">
                    <div class="w-20 h-20 bg-green-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="check" class="w-10 h-10 text-green-500"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2"><?php echo Language::get('public_view.response_recorded'); ?></h3>
                    <p class="text-gray-500"><?php echo Language::get('public_view.thank_you_response'); ?></p>
                    
                    <div class="mt-4 p-4 bg-gray-50 rounded-xl border border-gray-200">
                        <small class="block text-xs uppercase font-bold text-gray-500 mb-1"><?php echo Language::get('public_view.current_status'); ?></small>
                        <p id="response-status" class="font-bold text-gray-800"></p>
                    </div>

                    <!-- Location Button & Map (Shows only if Confirmed) -->
                    <div id="location-box" class="hidden mt-4 pt-4 border-t border-gray-200">
                        <p class="text-gray-500 mb-3"><?php echo Language::get('public_view.see_you_soon'); ?></p>
                        <!-- Embedded Map -->
                        <div class="aspect-video mb-3 rounded-xl overflow-hidden border border-gray-200">
                            <iframe class="w-full h-full" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="https://maps.google.com/maps?q=OTOMOTORS+Tbilisi&t=&z=15&ie=UTF8&iwloc=&output=embed"></iframe>
                        </div>
                        <a href="https://maps.app.goo.gl/4im27hK1oo1v65H2A" target="_blank" class="w-full inline-flex items-center justify-center gap-2 border-2 border-sky-500 text-sky-600 py-3 px-4 rounded-xl font-bold hover:bg-sky-50 transition-all">
                            <i data-lucide="map-pin" class="w-5 h-5"></i>
                            <?php echo Language::get('public_view.open_google_maps'); ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- SCENARIO 2: REVIEW SECTION (Completed) -->
            <div id="review-section" class="hidden">
                <!-- Review Form -->
                <div id="review-form">
                    <p class="text-center text-gray-500 mb-4"><?php echo Language::get('public_view.service_complete'); ?></p>
                    
                    <div class="flex justify-center gap-2 mb-4 star-rating">
                        <i data-lucide="star" class="w-10 h-10 text-gray-300 fill-gray-300 cursor-pointer hover:text-yellow-400 hover:fill-yellow-400" onclick="setRating(1)" data-rating="1"></i>
                        <i data-lucide="star" class="w-10 h-10 text-gray-300 fill-gray-300 cursor-pointer hover:text-yellow-400 hover:fill-yellow-400" onclick="setRating(2)" data-rating="2"></i>
                        <i data-lucide="star" class="w-10 h-10 text-gray-300 fill-gray-300 cursor-pointer hover:text-yellow-400 hover:fill-yellow-400" onclick="setRating(3)" data-rating="3"></i>
                        <i data-lucide="star" class="w-10 h-10 text-gray-300 fill-gray-300 cursor-pointer hover:text-yellow-400 hover:fill-yellow-400" onclick="setRating(4)" data-rating="4"></i>
                        <i data-lucide="star" class="w-10 h-10 text-gray-300 fill-gray-300 cursor-pointer hover:text-yellow-400 hover:fill-yellow-400" onclick="setRating(5)" data-rating="5"></i>
                    </div>
                    <input type="hidden" id="rating-value" value="0">

                    <textarea id="review-comment" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 mb-3 focus:border-sky-500 focus:outline-none" rows="4" placeholder="<?php echo Language::get('public_view.tell_us_experience'); ?>"></textarea>

                    <button onclick="submitReview()" class="w-full bg-gradient-to-r from-sky-500 to-sky-600 text-white py-3 px-4 rounded-xl font-bold hover:from-sky-600 hover:to-sky-700 transition-all flex items-center justify-center gap-2">
                        <i data-lucide="send" class="w-5 h-5"></i>
                        <?php echo Language::get('public_view.submit_review'); ?>
                    </button>
                </div>

                <!-- Review Submitted State -->
                <div id="review-success" class="hidden text-center py-4">
                    <div class="w-20 h-20 bg-yellow-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="star" class="w-10 h-10 text-yellow-500 fill-yellow-500"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2"><?php echo Language::get('public_view.thank_you'); ?></h3>
                    <p class="text-gray-500"><?php echo Language::get('public_view.appreciate_feedback'); ?></p>
                </div>
            </div>

        </div>

        <!-- Footer -->
        <div class="bg-gray-50 text-center py-4 border-t border-gray-200">
            <a href="tel:+995511144486" class="inline-flex items-center gap-2 text-sky-600 font-bold hover:text-sky-700 transition-colors">
                <i data-lucide="phone" class="w-5 h-5"></i>
                <?php echo Language::get('public_view.call_support'); ?>
            </a>
        </div>
    </div>

    <!-- Reschedule Modal -->
    <div id="reschedule-modal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-start justify-between">
                    <div>
                        <h5 class="text-lg font-bold"><?php echo Language::get('public_view.request_reschedule'); ?></h5>
                        <p class="text-gray-500 text-sm mt-1"><?php echo Language::get('public_view.preferred_date_time'); ?></p>
                    </div>
                    <button onclick="closeRescheduleModal()" class="text-gray-400 hover:text-gray-600">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label for="reschedule-date" class="block font-bold text-gray-700 mb-2"><?php echo Language::get('public_view.preferred_date_time_label'); ?></label>
                    <input id="reschedule-date" type="datetime-local" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:border-sky-500 focus:outline-none">
                </div>
                <div>
                    <label for="reschedule-comment" class="block font-bold text-gray-700 mb-2"><?php echo Language::get('public_view.additional_comments'); ?></label>
                    <textarea id="reschedule-comment" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:border-sky-500 focus:outline-none" rows="4" placeholder="<?php echo Language::get('public_view.let_us_know'); ?>"></textarea>
                </div>
            </div>
            <div class="p-6 border-t border-gray-200 flex gap-3">
                <button onclick="closeRescheduleModal()" class="flex-1 bg-white border-2 border-gray-200 text-gray-700 py-3 px-4 rounded-xl font-bold hover:bg-gray-50"><?php echo Language::get('public_view.cancel'); ?></button>
                <button onclick="submitReschedule()" class="flex-1 bg-gradient-to-r from-sky-500 to-sky-600 text-white py-3 px-4 rounded-xl font-bold hover:from-sky-600 hover:to-sky-700"><?php echo Language::get('public_view.submit_request'); ?></button>
            </div>
        </div>
    </div>

    <!-- Error State -->
    <div id="error-state" class="hidden text-center max-w-md w-full bg-white rounded-2xl shadow-xl p-8">
        <div class="w-20 h-20 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-4">
            <i data-lucide="alert-triangle" class="w-10 h-10 text-red-500"></i>
        </div>
        <h3 class="text-xl font-bold mb-2"><?php echo Language::get('public_view.appointment_not_found'); ?></h3>
        <p class="text-gray-500"><?php echo Language::get('public_view.link_expired'); ?></p>
    </div>

    <script>
        const API_URL = 'api.php'; 
        const urlParams = new URLSearchParams(window.location.search);
        const id = urlParams.get('id');
        let currentStars = 0;

        // Language strings for JavaScript
        const lang = {
            connection_error: '<?php echo addslashes(Language::get('public_view.connection_error', 'Connection error.')); ?>',
            select_star_rating: '<?php echo addslashes(Language::get('public_view.select_star_rating', 'Please select a star rating.')); ?>',
            error_submitting_review: '<?php echo addslashes(Language::get('public_view.error_submitting_review', 'Error submitting review.')); ?>',
            status_confirmed: '<?php echo addslashes(Language::get('public_view.status_confirmed', '✅ Confirmed')); ?>',
            status_reschedule_requested: '<?php echo addslashes(Language::get('public_view.status_reschedule_requested', '📅 Reschedule Requested')); ?>',
            status_pending: '<?php echo addslashes(Language::get('public_view.status_pending', '⏳ Pending')); ?>',
            sending: '<?php echo addslashes(Language::get('public_view.sending', 'Sending...')); ?>',
            select_date_time: '<?php echo addslashes(Language::get('public_view.select_date_time', 'Please select your preferred date and time')); ?>'
        };

        async function init() {
            if(!id || !/^\d+$/.test(id)) {
                console.error('Invalid or missing ID in URL');
                return showError();
            }

            console.log('Fetching transfer ID:', id);

            try {
                const res = await fetch(`${API_URL}?action=get_public_transfer&id=${id}`);
                console.log('Response status:', res.status);
                
                const data = await res.json();
                console.log('Response data:', data);

                if(data.error || !data.id) {
                    console.error('Error in response:', data.error || 'No ID in data');
                    return showError();
                }

                renderData(data);
            } catch(e) {
                console.error('Fetch error:', e);
                showError();
            }
        }

        function renderData(data) {
            const loader = document.getElementById('loader');
            const card = document.getElementById('card');
            
            loader.style.opacity = '0';
            setTimeout(() => loader.classList.add('hidden'), 300);

            card.classList.remove('hidden');
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'scale(1)';
            }, 100);

            // Sanitize and set text content (prevents XSS)
            document.getElementById('user-name').textContent = String(data.name || '<?php echo Language::get('public_view.valued_customer'); ?>').substring(0, 100);
            document.getElementById('plate').textContent = String(data.plate || '---').substring(0, 20);
            document.getElementById('order-id').textContent = '#' + (parseInt(data.id) || '0');

            // SCENARIO CHECK: If Completed, show Review. Else show Appointment logic.
            if (data.status === 'Completed') {
                showReviewUI(data);
            } else {
                showAppointmentUI(data);
            }
            
            lucide.createIcons();
        }

        function showAppointmentUI(data) {
            document.getElementById('appointment-section').classList.remove('hidden');
            
            if(data.serviceDate) {
                const cleanDate = data.serviceDate.replace(' ', 'T');
                const date = new Date(cleanDate);
                document.getElementById('date-month').innerText = date.toLocaleString('en-US', { month: 'short' }).toUpperCase();
                document.getElementById('date-day').innerText = date.getDate();
                document.getElementById('date-time').innerText = date.toLocaleString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            } else {
                document.getElementById('date-time').innerText = "<?php echo Language::get('public_view.to_be_determined'); ?>";
            }

            if(data.userResponse && data.userResponse !== 'Pending') {
                showSuccess(data.userResponse);
            }
        }

        function showReviewUI(data) {
            // Change Header to Green/Success Style
            const header = document.getElementById('header-bg');
            header.className = 'bg-gradient-to-br from-green-500 to-green-600 text-white px-6 py-10 text-center relative';
            document.getElementById('header-title').innerText = "<?php echo Language::get('public_view.service_completed'); ?>";
            
            // Replace Icon
            const iconContainer = document.getElementById('header-icon-container');
            iconContainer.innerHTML = '<i data-lucide="check-circle" class="w-8 h-8"></i>';

            document.getElementById('review-section').classList.remove('hidden');

            // If already reviewed
            if (data.reviewStars) {
                document.getElementById('review-form').classList.add('hidden');
                document.getElementById('review-success').classList.remove('hidden');
                // Render static stars in success message
                const container = document.querySelector('#review-success h3');
                const existingStars = document.getElementById('static-stars');
                if(!existingStars) {
                    const starDiv = document.createElement('div');
                    starDiv.id = 'static-stars';
                    starDiv.className = 'mb-2 flex justify-center gap-1';
                    for(let i = 0; i < 5; i++) {
                        const star = document.createElement('i');
                        star.setAttribute('data-lucide', 'star');
                        star.className = `w-6 h-6 ${i < data.reviewStars ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'}`;
                        starDiv.appendChild(star);
                    }
                    container.parentNode.insertBefore(starDiv, container.nextSibling);
                    lucide.createIcons();
                }
            }
        }

        // --- APPOINTMENT LOGIC ---
        function openRescheduleModal() {
            document.getElementById('reschedule-modal').classList.remove('hidden');
            // Set minimum date to today
            const now = new Date();
            const minDate = now.toISOString().slice(0, 16);
            document.getElementById('reschedule-date').min = minDate;
            lucide.createIcons();
        }

        function closeRescheduleModal() {
            document.getElementById('reschedule-modal').classList.add('hidden');
            document.getElementById('reschedule-date').value = '';
            document.getElementById('reschedule-comment').value = '';
        }

        async function submitReschedule() {
            const desiredDate = document.getElementById('reschedule-date').value;
            const comment = document.getElementById('reschedule-comment').value;

            if (!desiredDate) {
                alert(lang.select_date_time);
                return;
            }

            const modal = document.getElementById('reschedule-modal');
            const submitBtn = modal.querySelector('button[onclick="submitReschedule()"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 animate-spin inline mr-2"></i> ${lang.sending}`;
            lucide.createIcons();

            try {
                await fetch(`${API_URL}?action=user_respond`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        id: id, 
                        response: 'Reschedule Requested',
                        reschedule_date: desiredDate,
                        reschedule_comment: comment
                    })
                });
                closeRescheduleModal();
                showSuccess('Reschedule Requested');
            } catch(e) {
                alert(lang.connection_error);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                lucide.createIcons();
            }
        }

        async function submitResponse(status) {
            const btnArea = document.getElementById('action-area');
            const originalContent = btnArea.innerHTML;
            btnArea.innerHTML = `<div class="flex justify-center py-4"><i data-lucide="loader-2" class="w-6 h-6 animate-spin text-sky-600"></i></div>`;
            lucide.createIcons();

            try {
                await fetch(`${API_URL}?action=user_respond`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, response: status })
                });
                showSuccess(status);
            } catch(e) {
                alert(lang.connection_error);
                btnArea.innerHTML = originalContent;
                lucide.createIcons();
            }
        }

        function showSuccess(status) {
            document.getElementById('action-area').classList.add('hidden');
            document.getElementById('success-message').classList.remove('hidden');
            const statusMap = { 'Confirmed': lang.status_confirmed, 'Reschedule Requested': lang.status_reschedule_requested, 'Pending': lang.status_pending };
            document.getElementById('response-status').innerText = statusMap[status] || status;
            if (status === 'Confirmed') document.getElementById('location-box').classList.remove('hidden');
            else document.getElementById('location-box').classList.add('hidden');
            lucide.createIcons();
        }

        // --- REVIEW LOGIC ---
        function setRating(n) {
            currentStars = n;
            document.getElementById('rating-value').value = n;
            const stars = document.querySelectorAll('.star-rating i');
            stars.forEach((icon, i) => {
                if (i < n) {
                    icon.classList.remove('text-gray-300', 'fill-gray-300');
                    icon.classList.add('text-yellow-400', 'fill-yellow-400');
                } else {
                    icon.classList.remove('text-yellow-400', 'fill-yellow-400');
                    icon.classList.add('text-gray-300', 'fill-gray-300');
                }
            });
        }

        async function submitReview() {
            if (currentStars === 0) return alert(lang.select_star_rating);
            const comment = document.getElementById('review-comment').value;
            const btn = document.querySelector('#review-form button');
            
            btn.disabled = true;
            btn.innerHTML = `<i data-lucide="loader-2" class="w-5 h-5 animate-spin inline mr-2"></i> ${lang.sending}`;
            lucide.createIcons();

            try {
                await fetch(`${API_URL}?action=submit_review`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, stars: currentStars, comment: comment })
                });
                document.getElementById('review-form').classList.add('hidden');
                document.getElementById('review-success').classList.remove('hidden');
                lucide.createIcons();
            } catch(e) {
                alert(lang.error_submitting_review);
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="send" class="w-5 h-5"></i> <?php echo Language::get('public_view.submit_review'); ?>';
                lucide.createIcons();
            }
        }

        function showError() {
            document.getElementById('loader').classList.add('hidden');
            document.getElementById('error-state').classList.remove('hidden');
            lucide.createIcons();
        }

        init();
    </script>
</body>
</html>