<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Status | OTOMOTORS</title>
    
    <!-- Bootstrap 5.3 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    
    <style> 
        :root {
            --bs-primary: #573BFF;
            --bs-success: #17904b;
            --bs-warning: #FFA800;
            --bs-danger: #FF6171;
        }
        
        body { 
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .star-rating i { 
            display: inline-block; 
            transition: transform 0.1s, color 0.2s;
            cursor: pointer;
            color: #d1d5db;
            font-size: 1.75rem;
        }
        
        .star-rating i:hover { 
            transform: scale(1.15);
        }
        
        .star-rating i.active { 
            color: #FFA800 !important;
        }
        
        .card-hope {
            border-radius: 24px;
            border: none;
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
            max-width: 500px;
            width: 100%;
        }
        
        .card-header-hope {
            background: linear-gradient(135deg, var(--bs-primary), #8662FF);
            color: #fff;
            padding: 2.5rem 2rem;
            border-radius: 24px 24px 0 0;
            position: relative;
            overflow: hidden;
        }
        
        .card-header-hope::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.1);
            transform: skewY(-6deg);
            transform-origin: top left;
        }
        
        .icon-circle {
            width: 70px;
            height: 70px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            backdrop-filter: blur(10px);
        }
        
        .btn-hope {
            border-radius: 12px;
            padding: 1rem 2rem;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-primary-hope {
            background: linear-gradient(135deg, var(--bs-primary), #8662FF);
            color: #fff;
        }
        
        .btn-primary-hope:hover {
            background: linear-gradient(135deg, #4a2ed9, #7050f2);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(87, 59, 255, 0.4);
        }
        
        .btn-success-hope {
            background: var(--bs-success);
            color: #fff;
        }
        
        .btn-success-hope:hover {
            background: #138e3d;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(23, 144, 75, 0.4);
        }
        
        .btn-outline-hope {
            background: #fff;
            border: 2px solid #e9ecef;
            color: #6c757d;
        }
        
        .btn-outline-hope:hover {
            background: #f8f9fa;
            border-color: #dee2e6;
            color: #495057;
        }
        
        .badge-plate {
            background: #f8f9fa;
            color: #495057;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.85rem;
            border: 2px solid #e9ecef;
        }
        
        .date-box {
            background: rgba(87, 59, 255, 0.1);
            border: 2px solid rgba(87, 59, 255, 0.2);
            border-radius: 16px;
            padding: 1.5rem;
        }
        
        .date-icon {
            background: #fff;
            padding: 1rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            min-width: 80px;
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- Loading State -->
    <div id="loader" class="text-center">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-3 text-muted fw-medium">Loading...</p>
    </div>

    <!-- Main Card -->
    <div id="card" class="card-hope" style="display:none; opacity:0; transform:scale(0.95); transition:all 0.5s ease;">
        <!-- Header -->
        <div id="header-bg" class="card-header-hope text-center position-relative">
            <div class="position-relative" style="z-index: 10;">
                <div class="icon-circle" id="header-icon-container">
                    <i id="header-icon" class="fas fa-calendar-check fa-2x text-white"></i>
                </div>
                <h1 id="header-title" class="h2 fw-bold mb-2">Service Appointment</h1>
                <p class="mb-0 opacity-75">OTOMOTORS Service Center</p>
            </div>
        </div>

        <!-- Content -->
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <h2 id="user-name" class="h4 fw-bold text-dark">Customer</h2>
                <div class="d-flex flex-column align-items-center gap-2 mt-3">
                    <div class="badge-plate">
                        <i class="fas fa-car me-2"></i>
                        <span id="plate">---</span>
                    </div>
                    <small class="text-muted">
                        Order ID: <span id="order-id" class="fw-bold">#0</span>
                    </small>
                </div>
            </div>

            <!-- SCENARIO 1: APPOINTMENT CONFIRMATION (Not Completed) -->
            <div id="appointment-section" class="d-none">
                <!-- Date Display -->
                <div class="date-box d-flex align-items-center gap-3 mb-4">
                    <div class="date-icon">
                        <small id="date-month" class="d-block text-uppercase fw-bold text-primary opacity-75" style="font-size: 0.65rem;">---</small>
                        <div id="date-day" class="h2 fw-bold mb-0 text-primary">--</div>
                    </div>
                    <div>
                        <small class="d-block text-uppercase fw-bold text-primary opacity-75" style="font-size: 0.7rem;">Scheduled Time</small>
                        <div id="date-time" class="h4 fw-bold mb-0 text-dark">--:--</div>
                    </div>
                </div>

                <!-- Actions -->
                <div id="action-area" class="d-grid gap-3 mt-4">
                    <button onclick="submitResponse('Confirmed')" class="btn btn-primary-hope btn-hope d-flex align-items-center justify-content-center gap-2">
                        <i class="fas fa-check-circle"></i>
                        <span>Confirm & Accept</span>
                    </button>
                    <button onclick="openRescheduleModal()" class="btn btn-outline-hope btn-hope">
                        Request Another Time
                    </button>
                </div>

                <!-- Success State -->
                <div id="success-message" class="d-none text-center py-4">
                    <div class="icon-circle mx-auto mb-4" style="width: 80px; height: 80px; background: rgba(23, 144, 75, 0.1); color: var(--bs-success);">
                        <i class="fas fa-check fa-2x"></i>
                    </div>
                    <h3 class="h4 fw-bold mb-2">Response Recorded</h3>
                    <p class="text-muted">Thank you for letting us know.</p>
                    
                    <div class="mt-4 p-3 bg-light rounded-3 border">
                        <small class="d-block text-uppercase fw-bold text-muted mb-1" style="font-size: 0.7rem;">Current Status</small>
                        <p id="response-status" class="fw-bold mb-0"></p>
                    </div>

                    <!-- Location Button & Map (Shows only if Confirmed) -->
                    <div id="location-box" class="d-none mt-4 pt-4 border-top">
                        <p class="text-muted mb-3">See you soon! Get directions here:</p>
                        <!-- Embedded Map -->
                        <div class="ratio ratio-16x9 mb-3 rounded-3 overflow-hidden border" style="max-height: 250px;">
                            <iframe frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="https://maps.google.com/maps?q=OTOMOTORS+Tbilisi&t=&z=15&ie=UTF8&iwloc=&output=embed"></iframe>
                        </div>
                        <a href="https://maps.app.goo.gl/4im27hK1oo1v65H2A" target="_blank" class="btn btn-outline-primary btn-hope w-100 d-flex align-items-center justify-content-center gap-2">
                            <i class="fas fa-map-marker-alt"></i>
                            Open in Google Maps App
                        </a>
                    </div>
                </div>
            </div>

            <!-- SCENARIO 2: REVIEW SECTION (Completed) -->
            <div id="review-section" class="d-none">
                <!-- Review Form -->
                <div id="review-form">
                    <p class="text-center text-muted mb-4">Your service is complete. How did we do?</p>
                    
                    <div class="d-flex justify-content-center gap-2 mb-4 star-rating">
                        <i class="fas fa-star" onclick="setRating(1)" data-rating="1"></i>
                        <i class="fas fa-star" onclick="setRating(2)" data-rating="2"></i>
                        <i class="fas fa-star" onclick="setRating(3)" data-rating="3"></i>
                        <i class="fas fa-star" onclick="setRating(4)" data-rating="4"></i>
                        <i class="fas fa-star" onclick="setRating(5)" data-rating="5"></i>
                    </div>
                    <input type="hidden" id="rating-value" value="0">

                    <textarea id="review-comment" class="form-control mb-3" rows="4" placeholder="Tell us about your experience..."></textarea>

                    <button onclick="submitReview()" class="btn btn-primary-hope btn-hope w-100">
                        <i class="fas fa-paper-plane me-2"></i> Submit Review
                    </button>
                </div>

                <!-- Review Submitted State -->
                <div id="review-success" class="d-none text-center py-4">
                    <div class="icon-circle mx-auto mb-3" style="width: 80px; height: 80px; background: rgba(255, 168, 0, 0.1); color: var(--bs-warning);">
                        <i class="fas fa-star fa-2x"></i>
                    </div>
                    <h3 class="h4 fw-bold mb-2">Thank You!</h3>
                    <p class="text-muted">We appreciate your feedback.</p>
                </div>
            </div>

        </div>

        <!-- Footer -->
        <div class="card-footer bg-light text-center border-top">
            <a href="tel:+995511144486" class="btn btn-link text-primary text-decoration-none fw-bold">
                <i class="fas fa-phone me-2"></i> Call Support
            </a>
        </div>
    </div>

    <!-- Reschedule Modal -->
    <div class="modal fade" id="reschedule-modal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fw-bold">Request Reschedule</h5>
                        <p class="text-muted mb-0" style="font-size: 0.85rem;">Tell us your preferred date and time</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="reschedule-date" class="form-label fw-bold">Preferred Date & Time</label>
                        <input id="reschedule-date" type="datetime-local" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="reschedule-comment" class="form-label fw-bold">Additional Comments (Optional)</label>
                        <textarea id="reschedule-comment" class="form-control" rows="4" placeholder="Let us know any specific requirements or reasons for rescheduling..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-hope btn-hope" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary-hope btn-hope" onclick="submitReschedule()">Submit Request</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Error State -->
    <div id="error-state" class="d-none text-center">
        <div class="icon-circle mx-auto mb-3" style="width: 80px; height: 80px; background: rgba(255, 97, 113, 0.1); color: var(--bs-danger);">
            <i class="fas fa-exclamation-triangle fa-2x"></i>
        </div>
        <h3 class="h4 fw-bold mb-2">Appointment Not Found</h3>
        <p class="text-muted">This link may be expired or invalid.</p>
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
        }
        function showAppointmentUI(data) {
            document.getElementById('appointment-section').classList.remove('d-none');
            
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
            header.style.background = 'linear-gradient(135deg, var(--bs-success), #138e3d)';
            document.getElementById('header-title').innerText = "Service Completed";
            
            // Replace Icon
            const iconContainer = document.getElementById('header-icon-container');
            iconContainer.innerHTML = '<i class="fas fa-check-circle fa-2x text-white"></i>';

            document.getElementById('review-section').classList.remove('d-none');

            // If already reviewed
            if (data.reviewStars) {
                document.getElementById('review-form').classList.add('d-none');
                document.getElementById('review-success').classList.remove('d-none');
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
            const modal = new bootstrap.Modal(document.getElementById('reschedule-modal'));
            modal.show();
            // Set minimum date to today
            const now = new Date();
            const minDate = now.toISOString().slice(0, 16);
            document.getElementById('reschedule-date').min = minDate;
            lucide.createIcons();
        }

        function closeRescheduleModal() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('reschedule-modal'));
            if (modal) modal.hide();
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
            document.getElementById('action-area').classList.add('d-none');
            document.getElementById('success-message').classList.remove('d-none');
            const statusMap = { 'Confirmed': '✅ Confirmed', 'Reschedule Requested': '📅 Reschedule Requested', 'Pending': '⏳ Pending' };
            document.getElementById('response-status').innerText = statusMap[status] || status;
            if (status === 'Confirmed') document.getElementById('location-box').classList.remove('d-none');
            else document.getElementById('location-box').classList.add('d-none');
        }

        // --- REVIEW LOGIC ---
        function setRating(n) {
            currentStars = n;
            document.getElementById('rating-value').value = n;
            const stars = document.querySelectorAll('.star-rating i');
            stars.forEach((icon, i) => {
                if (i < n) {
                    icon.classList.add('active');
                } else {
                    icon.classList.remove('active');
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
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>