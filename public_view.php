<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Status | OTOMOTORS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style> 
        body { font-family: 'Inter', sans-serif; } 
        .star-rating span { display: inline-block; transition: transform 0.1s; }
        .star-rating span:hover { transform: scale(1.1); }
        .star-rating svg { transition: all 0.2s; }
        .star-rating svg.active { fill: #FBBF24 !important; color: #FBBF24 !important; }
    </style>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen p-4">

    <!-- Loading State -->
    <div id="loader" class="text-center transition-opacity duration-300">
        <div class="w-10 h-10 border-4 border-indigo-200 border-t-indigo-600 rounded-full animate-spin mx-auto mb-4"></div>
        <p class="text-gray-500 text-sm font-medium">Loading...</p>
    </div>

    <!-- Main Card -->
    <div id="card" class="hidden max-w-md w-full bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100 transition-all duration-500 transform scale-95 opacity-0">
        <!-- Header -->
        <div id="header-bg" class="bg-indigo-600 p-6 text-white text-center relative overflow-hidden transition-colors duration-500">
            <div class="absolute top-0 left-0 w-full h-full bg-white/10 transform -skew-y-6 origin-top-left"></div>
            <div class="relative z-10">
                <div class="bg-white/20 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3 backdrop-blur-sm transition-all duration-500" id="header-icon-container">
                    <i id="header-icon" data-lucide="calendar-check" class="w-8 h-8 text-white"></i>
                </div>
                <h1 id="header-title" class="text-2xl font-bold">Service Appointment</h1>
                <p class="text-indigo-100 text-sm mt-1 font-medium">OTOMOTORS Service Center</p>
            </div>
        </div>

        <!-- Content -->
        <div class="p-6 space-y-6">
            <div class="text-center space-y-2">
                <h2 id="user-name" class="text-xl font-bold text-gray-800">Customer</h2>
                <div class="flex flex-col items-center gap-2">
                    <div class="inline-flex items-center gap-2 bg-gray-100 px-3 py-1 rounded-full text-xs font-mono font-bold text-gray-500 border border-gray-200">
                        <i data-lucide="car" class="w-3 h-3"></i>
                        <span id="plate">---</span>
                    </div>
                    <div class="text-[10px] font-mono text-gray-400 bg-gray-50 px-2 py-0.5 rounded border border-gray-200">
                        Order ID: <span id="order-id">#0</span>
                    </div>
                </div>
            </div>

            <!-- SCENARIO 1: APPOINTMENT CONFIRMATION (Not Completed) -->
            <div id="appointment-section" class="hidden space-y-6">
                <!-- Date Display -->
                <div class="bg-indigo-50 border border-indigo-100 rounded-2xl p-5 flex items-center gap-5">
                    <div class="bg-white p-3 rounded-xl text-indigo-600 shadow-sm text-center min-w-[70px] border border-indigo-50">
                        <span id="date-month" class="block text-[10px] font-bold uppercase tracking-wider text-indigo-400">---</span>
                        <span id="date-day" class="block text-2xl font-extrabold leading-none mt-0.5">--</span>
                    </div>
                    <div>
                        <p class="text-xs text-indigo-400 font-bold uppercase tracking-wide mb-0.5">Scheduled Time</p>
                        <p id="date-time" class="text-xl font-bold text-indigo-900">--:--</p>
                    </div>
                </div>

                <!-- Actions -->
                <div id="action-area" class="space-y-3 pt-2">
                    <button onclick="submitResponse('Confirmed')" class="group w-full bg-indigo-600 hover:bg-indigo-700 text-white py-4 rounded-xl font-bold shadow-lg shadow-indigo-200 active:scale-95 transition-all flex items-center justify-center gap-3">
                        <span class="bg-white/20 p-1 rounded-full"><i data-lucide="check" class="w-4 h-4"></i></span>
                        <span>Confirm & Accept</span>
                    </button>
                    <button onclick="openRescheduleModal()" class="w-full bg-white border-2 border-gray-200 text-gray-500 hover:border-gray-300 hover:bg-gray-50 hover:text-gray-700 py-4 rounded-xl font-bold active:scale-95 transition-all text-sm">
                        Request Another Time
                    </button>
                </div>

                <!-- Success State -->
                <div id="success-message" class="hidden text-center py-6">
                    <div class="w-20 h-20 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-4 animate-[bounce_1s_ease-in-out_1]">
                        <i data-lucide="check" class="w-10 h-10"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-1">Response Recorded</h3>
                    <p class="text-gray-500 text-sm">Thank you for letting us know.</p>
                    
                    <div class="mt-6 p-4 bg-gray-50 rounded-xl border border-gray-100 inline-block w-full">
                        <p class="text-xs text-gray-400 uppercase font-bold tracking-wider mb-1">Current Status</p>
                        <p id="response-status" class="text-sm font-semibold text-gray-700"></p>
                    </div>

                    <!-- Location Button & Map (Shows only if Confirmed) -->
                    <div id="location-box" class="hidden mt-6 pt-6 border-t border-gray-100">
                        <p class="text-sm text-gray-500 mb-3 font-medium">See you soon! Get directions here:</p>
                        <!-- Embedded Map -->
                        <div class="w-full h-48 bg-gray-200 rounded-xl overflow-hidden mb-3 border border-gray-200 shadow-sm relative">
                            <div class="absolute inset-0 flex items-center justify-center bg-gray-100 -z-10 animate-pulse">Loading Map...</div>
                            <iframe width="100%" height="100%" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="https://maps.google.com/maps?q=OTOMOTORS+Tbilisi&t=&z=15&ie=UTF8&iwloc=&output=embed"></iframe>
                        </div>
                        <a href="https://maps.app.goo.gl/4im27hK1oo1v65H2A" target="_blank" class="group w-full bg-white border border-indigo-100 text-indigo-600 hover:bg-indigo-50 hover:border-indigo-200 py-3.5 rounded-xl font-bold flex items-center justify-center gap-2 transition-all shadow-sm">
                            <div class="bg-indigo-100 p-1.5 rounded-full group-hover:bg-indigo-200 transition-colors"><i data-lucide="map-pin" class="w-4 h-4"></i></div>
                            Open in Google Maps App
                        </a>
                    </div>
                </div>
            </div>

            <!-- SCENARIO 2: REVIEW SECTION (Completed) -->
            <div id="review-section" class="hidden space-y-6">
                <!-- Review Form -->
                <div id="review-form">
                    <p class="text-center text-gray-500 text-sm mb-6">Your service is complete. How did we do?</p>
                    
                    <div class="flex justify-center gap-2 mb-6 star-rating">
                        <span onclick="setRating(1)" style="cursor: pointer;"><i data-lucide="star" class="w-10 h-10 text-gray-300"></i></span>
                        <span onclick="setRating(2)" style="cursor: pointer;"><i data-lucide="star" class="w-10 h-10 text-gray-300"></i></span>
                        <span onclick="setRating(3)" style="cursor: pointer;"><i data-lucide="star" class="w-10 h-10 text-gray-300"></i></span>
                        <span onclick="setRating(4)" style="cursor: pointer;"><i data-lucide="star" class="w-10 h-10 text-gray-300"></i></span>
                        <span onclick="setRating(5)" style="cursor: pointer;"><i data-lucide="star" class="w-10 h-10 text-gray-300"></i></span>
                    </div>
                    <input type="hidden" id="rating-value" value="0">

                    <textarea id="review-comment" class="w-full p-4 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none resize-none h-32" placeholder="Tell us about your experience..."></textarea>

                    <button onclick="submitReview()" class="mt-4 w-full bg-indigo-600 hover:bg-indigo-700 text-white py-4 rounded-xl font-bold shadow-lg shadow-indigo-200 active:scale-95 transition-all">
                        Submit Review
                    </button>
                </div>

                <!-- Review Submitted State -->
                <div id="review-success" class="hidden text-center py-8">
                    <div class="w-20 h-20 bg-yellow-100 text-yellow-600 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="star" class="w-10 h-10 fill-current"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-1">Thank You!</h3>
                    <p class="text-gray-500 text-sm">We appreciate your feedback.</p>
                </div>
            </div>

        </div>

        <!-- Footer -->
        <div class="bg-gray-50 p-4 text-center border-t border-gray-100">
            <a href="tel:+995511144486" class="text-xs font-semibold text-indigo-500 hover:text-indigo-700 flex items-center justify-center gap-1">
                <i data-lucide="phone" class="w-3 h-3"></i> Call Support
            </a>
        </div>
    </div>

    <!-- Reschedule Modal -->
    <div id="reschedule-modal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/50 backdrop-blur-sm">
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 space-y-5 animate-in fade-in zoom-in-95 duration-300">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-xl font-bold text-slate-800">Request Reschedule</h3>
                        <p class="text-sm text-slate-500 mt-1">Tell us your preferred date and time</p>
                    </div>
                    <button onclick="closeRescheduleModal()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Preferred Date & Time</label>
                        <input id="reschedule-date" type="datetime-local" class="w-full p-3 border border-slate-200 rounded-xl text-sm focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Additional Comments (Optional)</label>
                        <textarea id="reschedule-comment" class="w-full p-3 border border-slate-200 rounded-xl text-sm focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none resize-none" rows="4" placeholder="Let us know any specific requirements or reasons for rescheduling..."></textarea>
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <button onclick="closeRescheduleModal()" class="flex-1 px-4 py-3 text-slate-600 hover:bg-slate-50 rounded-xl font-medium transition-colors border border-slate-200">
                        Cancel
                    </button>
                    <button onclick="submitReschedule()" class="flex-1 px-4 py-3 bg-indigo-600 text-white hover:bg-indigo-700 rounded-xl font-bold shadow-lg shadow-indigo-200 transition-all active:scale-95">
                        Submit Request
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Error State -->
    <div id="error-state" class="hidden text-center max-w-xs">
        <div class="w-16 h-16 bg-red-100 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
            <i data-lucide="alert-triangle" class="w-8 h-8"></i>
        </div>
        <h3 class="text-gray-800 font-bold text-lg">Appointment Not Found</h3>
        <p class="text-gray-500 text-sm mt-2 leading-relaxed">This link may be expired or invalid.</p>
    </div>

    <script>
        const API_URL = 'api.php'; 
        const urlParams = new URLSearchParams(window.location.search);
        const id = urlParams.get('id');
        let currentStars = 0;

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
            
            loader.classList.add('opacity-0');
            setTimeout(() => loader.classList.add('hidden'), 300);

            card.classList.remove('hidden');
            setTimeout(() => {
                card.classList.remove('scale-95', 'opacity-0');
                card.classList.add('scale-100', 'opacity-100');
            }, 100);

            // Sanitize and set text content (prevents XSS)
            document.getElementById('user-name').textContent = String(data.name || 'Valued Customer').substring(0, 100);
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
                document.getElementById('date-month').innerText = date.toLocaleString('en-US', { month: 'short' });
                document.getElementById('date-day').innerText = date.getDate();
                document.getElementById('date-time').innerText = date.toLocaleString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            } else {
                document.getElementById('date-time').innerText = "TBD";
            }

            if(data.userResponse && data.userResponse !== 'Pending') {
                showSuccess(data.userResponse);
            }
        }

        function showReviewUI(data) {
            // Change Header to Green/Success Style
            const header = document.getElementById('header-bg');
            header.classList.remove('bg-indigo-600');
            header.classList.add('bg-emerald-600');
            document.getElementById('header-title').innerText = "Service Completed";
            
            // Replace Icon
            const iconContainer = document.getElementById('header-icon-container');
            iconContainer.innerHTML = '<i data-lucide="check-circle" class="w-8 h-8 text-white"></i>';

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
                    starDiv.className = 'mb-2';
                    for(let i = 0; i < 5; i++) {
                        const star = document.createElement('i');
                        star.setAttribute('data-lucide', 'star');
                        star.className = `w-5 h-5 inline-block ${i < data.reviewStars ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'}`;
                        starDiv.appendChild(star);
                    }
                    container.parentNode.insertBefore(starDiv, container.nextSibling);
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
                alert('Please select your preferred date and time');
                return;
            }

            const modal = document.getElementById('reschedule-modal');
            const submitBtn = modal.querySelector('button[onclick="submitReschedule()"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin inline mr-2"></i> Sending...';
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
                alert("Connection error.");
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }

        async function submitResponse(status) {
            const btnArea = document.getElementById('action-area');
            const originalContent = btnArea.innerHTML;
            btnArea.innerHTML = `<div class="flex justify-center py-4"><i data-lucide="loader-2" class="w-6 h-6 animate-spin text-indigo-600"></i></div>`;
            lucide.createIcons();

            try {
                await fetch(`${API_URL}?action=user_respond`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, response: status })
                });
                showSuccess(status);
            } catch(e) {
                alert("Connection error.");
                btnArea.innerHTML = originalContent;
                lucide.createIcons();
            }
        }

        function showSuccess(status) {
            document.getElementById('action-area').classList.add('hidden');
            document.getElementById('success-message').classList.remove('hidden');
            const statusMap = { 'Confirmed': '✅ Confirmed', 'Reschedule Requested': '📅 Reschedule Requested', 'Pending': '⏳ Pending' };
            document.getElementById('response-status').innerText = statusMap[status] || status;
            if (status === 'Confirmed') document.getElementById('location-box').classList.remove('hidden');
            else document.getElementById('location-box').classList.add('hidden');
            lucide.createIcons();
        }

        // --- REVIEW LOGIC ---
        function setRating(n) {
            currentStars = n;
            const stars = document.querySelectorAll('.star-rating svg');
            stars.forEach((svg, i) => {
                if (i < n) {
                    svg.classList.add('active');
                    svg.classList.remove('text-gray-300');
                    svg.classList.add('text-yellow-400');
                    svg.style.fill = 'currentColor';
                } else {
                    svg.classList.remove('active', 'text-yellow-400');
                    svg.classList.add('text-gray-300');
                    svg.style.fill = 'none';
                }
            });
        }

        async function submitReview() {
            if (currentStars === 0) return alert("Please select a star rating.");
            const comment = document.getElementById('review-comment').value;
            const btn = document.querySelector('#review-form button');
            
            btn.disabled = true;
            btn.innerHTML = `<i data-lucide="loader-2" class="w-5 h-5 animate-spin inline mr-2"></i> Sending...`;
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
                alert("Error submitting review.");
                btn.disabled = false;
                btn.innerText = "Submit Review";
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