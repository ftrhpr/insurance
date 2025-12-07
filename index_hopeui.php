<?php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$current_user_name = $_SESSION['full_name'] ?? 'User';
$current_user_role = $_SESSION['role'] ?? 'viewer';
?>
<!doctype html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>OTOMOTORS Manager Portal | Hope UI Dashboard</title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="assets/images/favicon.ico" />
    
    <!-- Bootstrap 5.3 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- FIREBASE SDKs -->
    <script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-messaging-compat.js"></script>

    <style>
        /* Hope UI Color Scheme */
        :root {
            --bs-primary: #573BFF;
            --bs-primary-rgb: 87, 59, 255;
            --bs-secondary: #7C8DB0;
            --bs-success: #17904b;
            --bs-info: #00C3F9;
            --bs-warning: #FFA800;
            --bs-danger: #FF6171;
            --bs-dark: #1E2139;
            --bs-body-bg: #f8f9fa;
            --sidebar-width: 260px;
            --sidebar-mini-width: 80px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bs-body-bg);
            overflow-x: hidden;
        }
        
        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: #fff;
            box-shadow: 2px 0 15px rgba(0,0,0,0.08);
            z-index: 1040;
            transition: all 0.3s ease-in-out;
            overflow-y: auto;
        }
        
        .sidebar-mini .sidebar {
            width: var(--sidebar-mini-width);
        }
        
        .sidebar::-webkit-scrollbar {
            width: 5px;
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(0,0,0,0.1);
            border-radius: 10px;
        }
        
        .sidebar-header {
            padding: 1.5rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .logo-title {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--bs-primary), #8662FF);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0;
        }
        
        .sidebar-toggle {
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .sidebar-toggle:hover {
            background: #f8f9fa;
        }
        
        .sidebar-body {
            padding: 1rem 0;
        }
        
        .navbar-nav .nav-item {
            margin: 0.25rem 1rem;
        }
        
        .navbar-nav .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            color: #7C8DB0;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .navbar-nav .nav-link i {
            margin-right: 0.75rem;
            font-size: 1.1rem;
            width: 24px;
            text-align: center;
        }
        
        .navbar-nav .nav-link.active {
            background: linear-gradient(135deg, var(--bs-primary) 0%, #8662FF 100%);
            color: #fff;
            box-shadow: 0 4px 12px rgba(87, 59, 255, 0.3);
        }
        
        .navbar-nav .nav-link:hover:not(.active) {
            background: #f8f9fa;
            color: var(--bs-primary);
            transform: translateX(5px);
        }
        
        /* Main Content Area */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: all 0.3s ease-in-out;
        }
        
        .sidebar-mini .main-content {
            margin-left: var(--sidebar-mini-width);
        }
        
        /* Top Navbar */
        .iq-navbar {
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            z-index: 1030;
        }
        
        .navbar-custom {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .navbar-custom .left-panel {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .navbar-custom .right-panel {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .hamburger-toggle {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--bs-dark);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .hamburger-toggle:hover {
            background: #f8f9fa;
        }
        
        /* Hope UI Cards */
        .card {
            border-radius: 16px;
            border: none;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
        }
        
        .card:hover {
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
            transform: translateY(-4px);
        }
        
        .card-header {
            background: transparent;
            border-bottom: 1px solid #f0f0f0;
            padding: 1.5rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .card-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--bs-dark);
            margin: 0;
        }
        
        /* Hope UI Buttons */
        .btn {
            border-radius: 10px;
            padding: 0.65rem 1.5rem;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--bs-primary), #8662FF);
            color: #fff;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #4a2ed9, #7050f2);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(87, 59, 255, 0.4);
        }
        
        .btn-success {
            background: var(--bs-success);
            color: #fff;
        }
        
        .btn-success:hover {
            background: #138e3d;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(23, 144, 75, 0.4);
        }
        
        .btn-info {
            background: var(--bs-info);
            color: #fff;
        }
        
        .btn-warning {
            background: var(--bs-warning);
            color: #fff;
        }
        
        .btn-danger {
            background: var(--bs-danger);
            color: #fff;
        }
        
        /* Hope UI Badges */
        .badge {
            padding: 0.5rem 0.9rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.75rem;
            letter-spacing: 0.3px;
        }
        
        .badge-new { background: var(--bs-warning); color: #fff; }
        .badge-processing { background: #FFC107; color: #fff; }
        .badge-called { background: #9C27B0; color: #fff; }
        .badge-parts-ordered { background: #2196F3; color: #fff; }
        .badge-parts-arrived { background: var(--bs-info); color: #fff; }
        .badge-scheduled { background: #FF9800; color: #fff; }
        .badge-completed { background: var(--bs-success); color: #fff; }
        .badge-issue { background: var(--bs-danger); color: #fff; }
        
        /* Hope UI Table */
        .table-hope {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
        }
        
        .table-hope thead {
            background: linear-gradient(135deg, var(--bs-primary) 0%, #8662FF 100%);
            color: #fff;
        }
        
        .table-hope thead th {
            padding: 1rem 1.5rem;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
        }
        
        .table-hope tbody tr {
            transition: all 0.2s ease;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .table-hope tbody tr:hover {
            background: #f8f9fa;
        }
        
        .table-hope tbody td {
            padding: 1.25rem 1.5rem;
            vertical-align: middle;
            font-size: 0.9rem;
        }
        
        /* Form Controls */
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #e0e0e0;
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 0.2rem rgba(87, 59, 255, 0.15);
        }
        
        /* Dropdown Menu */
        .dropdown-menu {
            border-radius: 12px;
            border: none;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            padding: 0.5rem;
        }
        
        .dropdown-item {
            border-radius: 8px;
            padding: 0.6rem 1rem;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        
        .dropdown-item:hover {
            background: #f8f9fa;
            color: var(--bs-primary);
        }
        
        /* Avatar */
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--bs-primary), #8662FF);
            color: #fff;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        /* Toast Notifications */
        .toast-container {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 9999;
        }
        
        .toast-custom {
            min-width: 300px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            border: none;
            margin-bottom: 1rem;
        }
        
        .toast-custom .toast-header {
            background: linear-gradient(135deg, var(--bs-primary), #8662FF);
            color: #fff;
            border: none;
            border-radius: 12px 12px 0 0;
            font-weight: 600;
        }
        
        /* Loading Spinner */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.95);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.3s ease;
        }
        
        .loading-overlay.hidden {
            opacity: 0;
            pointer-events: none;
        }
        
        .spinner-hope {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--bs-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Modal */
        .modal-content {
            border-radius: 16px;
            border: none;
            box-shadow: 0 12px 40px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--bs-primary), #8662FF);
            color: #fff;
            border-radius: 16px 16px 0 0;
            border: none;
            padding: 1.5rem;
        }
        
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        
        .modal-title {
            font-weight: 700;
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .modal-footer {
            border: none;
            padding: 1.5rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
        
        /* Utility Classes */
        .text-muted {
            color: #7C8DB0 !important;
        }
        
        .cursor-pointer {
            cursor: pointer;
        }
        
        .hidden {
            display: none !important;
        }
    </style>
</head>
<body>
    
    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay">
        <div class="text-center">
            <div class="spinner-hope"></div>
            <p class="mt-3 fw-bold" style="color: var(--bs-primary);">Loading OTOMOTORS...</p>
        </div>
    </div>
    
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="d-flex align-items-center">
                <h4 class="logo-title">OTOMOTORS</h4>
            </div>
            <div class="sidebar-toggle" onclick="toggleSidebar()">
                <i class="fas fa-chevron-left"></i>
            </div>
        </div>
        
        <div class="sidebar-body">
            <ul class="navbar-nav" id="sidebar-menu">
                <li class="nav-item">
                    <a class="nav-link active" href="#" onclick="switchView('dashboard'); return false;">
                        <i class="fas fa-th-large"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#" onclick="switchView('vehicles'); return false;">
                        <i class="fas fa-car"></i>
                        <span>Vehicles</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="templates.php">
                        <i class="fas fa-comment-dots"></i>
                        <span>SMS Templates</span>
                    </a>
                </li>
                <?php if ($current_user_role === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-users"></i>
                        <span>User Management</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </aside>
    
    <!-- Main Content -->
    <div class="main-content" id="main-content">
        
        <!-- Top Navbar -->
        <nav class="iq-navbar">
            <div class="navbar-custom">
                <div class="left-panel">
                    <button class="hamburger-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h5 class="mb-0 fw-bold" style="color: var(--bs-dark);">Manager Portal</h5>
                </div>
                
                <div class="right-panel">
                    <!-- User Dropdown -->
                    <div class="dropdown">
                        <a class="d-flex align-items-center text-decoration-none dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="avatar me-2">
                                <?php echo strtoupper(substr($current_user_name, 0, 1)); ?>
                            </div>
                            <div class="d-none d-md-block">
                                <div class="fw-bold" style="font-size: 0.9rem; color: var(--bs-dark);"><?php echo htmlspecialchars($current_user_name); ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;"><?php echo ucfirst($current_user_role); ?></div>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Sign out</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
        
        <!-- Page Content -->
        <div class="container-fluid p-4">
            
            <!-- Dashboard View -->
            <div id="view-dashboard">
                
                <!-- Quick Import Section -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="card-title mb-1">Quick Import</h4>
                            <p class="text-muted mb-0" style="font-size: 0.85rem;">Paste SMS or bank statement to auto-detect transfers</p>
                        </div>
                        <div class="d-flex gap-2">
                            <?php if ($current_user_role === 'admin' || $current_user_role === 'manager'): ?>
                            <button onclick="window.openManualCreateModal()" class="btn btn-success btn-sm">
                                <i class="fas fa-plus-circle me-1"></i> Manual Create
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <textarea id="import-text" class="form-control" rows="5" placeholder="Paste bank text here..."></textarea>
                                <button onclick="window.parseBankText()" class="btn btn-primary mt-3">
                                    <i class="fas fa-magic me-2"></i> Detect
                                </button>
                            </div>
                            <div class="col-md-6">
                                <div id="parsed-placeholder" class="d-flex align-items-center justify-center border border-dashed rounded p-4 h-100 text-muted">
                                    <div class="text-center">
                                        <i class="fas fa-arrow-left mb-2" style="font-size: 2rem; opacity: 0.3;"></i>
                                        <p class="mb-0">Waiting for text input...</p>
                                    </div>
                                </div>
                                <div id="parsed-result" class="hidden">
                                    <div id="parsed-content" class="mb-3" style="max-height: 200px; overflow-y: auto;"></div>
                                    <button id="btn-save-import" onclick="window.saveParsedImport()" class="btn btn-success w-100">
                                        <i class="fas fa-save me-2"></i> Confirm & Save
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <input id="search-input" type="text" class="form-control" placeholder="Search plates, names, phones...">
                            </div>
                            <div class="col-md-4">
                                <select id="reply-filter" class="form-select">
                                    <option value="All">All Replies</option>
                                    <option value="Confirmed">✅ Confirmed</option>
                                    <option value="Reschedule Requested">📅 Reschedule</option>
                                    <option value="Pending">⏳ Not Responded</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select id="status-filter" class="form-select">
                                    <option value="All">All Active Stages</option>
                                    <option value="Processing">🟡 Processing</option>
                                    <option value="Called">🟣 Contacted</option>
                                    <option value="Parts Ordered">📦 Parts Ordered</option>
                                    <option value="Parts Arrived">🏁 Parts Arrived</option>
                                    <option value="Scheduled">🟠 Scheduled</option>
                                    <option value="Completed">🟢 Completed</option>
                                    <option value="Issue">🔴 Issue</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- New Requests -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">New Requests <span id="new-count" class="badge bg-warning ms-2">(0)</span></h4>
                    </div>
                    <div class="card-body">
                        <div id="new-cases-grid" class="row g-3"></div>
                        <div id="new-cases-empty" class="hidden text-center py-5 text-muted">
                            <i class="fas fa-inbox mb-3" style="font-size: 3rem; opacity: 0.3;"></i>
                            <p>No new requests</p>
                        </div>
                    </div>
                </div>
                
                <!-- Processing Queue -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">Processing Queue</h4>
                        <span id="record-count" class="badge bg-primary">0 active</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hope mb-0">
                                <thead>
                                    <tr>
                                        <th>Vehicle & Owner</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Contact</th>
                                        <th>Service Date</th>
                                        <th>Customer Reply</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="table-body"></tbody>
                            </table>
                            <div id="empty-state" class="hidden text-center py-5 text-muted">
                                <i class="fas fa-filter mb-3" style="font-size: 3rem; opacity: 0.3;"></i>
                                <p>No matching cases found</p>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Vehicles View -->
            <div id="view-vehicles" class="hidden">
                
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">Vehicle Registry</h4>
                        <span id="vehicles-count" class="badge bg-secondary">0 vehicles</span>
                    </div>
                    <div class="card-body">
                        <input id="vehicles-search" type="text" class="form-control mb-3" placeholder="Search by plate or phone...">
                        
                        <div class="table-responsive">
                            <table class="table table-hope mb-0">
                                <thead>
                                    <tr>
                                        <th>Plate</th>
                                        <th>Phone</th>
                                        <th>Added</th>
                                        <th>Source</th>
                                    </tr>
                                </thead>
                                <tbody id="vehicles-table-body"></tbody>
                            </table>
                            <div id="vehicles-empty" class="hidden text-center py-5 text-muted">
                                <i class="fas fa-car mb-3" style="font-size: 3rem; opacity: 0.3;"></i>
                                <p>No vehicles found</p>
                            </div>
                        </div>
                        
                        <!-- Pagination -->
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="text-muted" style="font-size: 0.9rem;">
                                Showing <span id="vehicles-showing-start">0</span>-<span id="vehicles-showing-end">0</span> of <span id="vehicles-total">0</span>
                            </div>
                            <div id="vehicles-pagination" class="d-flex gap-2"></div>
                        </div>
                    </div>
                </div>
                
            </div>
            
        </div>
        
    </div>
    
    <!-- Toast Container -->
    <div class="toast-container" id="toast-container"></div>
    
    <!-- Edit Modal -->
    <div class="modal fade" id="edit-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-title">Edit Transfer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modal-content">
                    <!-- Content injected by JavaScript -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Global variables
        const API_URL = 'api.php';
        const USER_ROLE = '<?php echo $current_user_role; ?>';
        const CAN_EDIT = USER_ROLE === 'admin' || USER_ROLE === 'manager';
        let transfers = [];
        let vehicles = [];
        window.currentEditingId = null;
        let parsedImportData = [];
        let currentView = 'dashboard';
        
        // Toggle Sidebar
        function toggleSidebar() {
            document.body.classList.toggle('sidebar-mini');
        }
        
        // Switch View
        function switchView(view) {
            currentView = view;
            document.getElementById('view-dashboard').classList.toggle('hidden', view !== 'dashboard');
            document.getElementById('view-vehicles').classList.toggle('hidden', view !== 'vehicles');
            
            // Update active nav
            document.querySelectorAll('.navbar-nav .nav-link').forEach(link => {
                link.classList.remove('active');
            });
            event.target.closest('.nav-link').classList.add('active');
            
            if (view === 'vehicles') {
                renderVehicles();
            }
        }
        
        // Toast notification function
        function showToast(title, message = '', type = 'success') {
            const container = document.getElementById('toast-container');
            const colors = {
                success: 'bg-success',
                error: 'bg-danger',
                info: 'bg-info',
            };
            const bgColor = colors[type] || colors.info;
            
            const toast = document.createElement('div');
            toast.className = `toast toast-custom show`;
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <div class="toast-header ${bgColor}">
                    <strong class="me-auto">${title}</strong>
                    <button type="button" class="btn-close" onclick="this.closest('.toast').remove()"></button>
                </div>
                ${message ? `<div class="toast-body">${message}</div>` : ''}
            `;
            container.appendChild(toast);
            
            setTimeout(() => toast.remove(), 4000);
        }
        
        // API helper
        async function fetchAPI(action, method = 'GET', body = null) {
            const opts = { method };
            if (body) {
                opts.body = JSON.stringify(body);
                opts.headers = { 'Content-Type': 'application/json' };
            }
            
            try {
                const res = await fetch(`${API_URL}?action=${action}`, opts);
                return await res.json();
            } catch (e) {
                console.error('API Error:', e);
                showToast('Connection Error', e.message, 'error');
                throw e;
            }
        }
        
        // Load data
        async function loadData() {
            try {
                const response = await fetchAPI('get_transfers');
                if (response.transfers && response.vehicles) {
                    transfers = response.transfers;
                    vehicles = response.vehicles;
                } else if (Array.isArray(response)) {
                    transfers = response;
                    const vehiclesResponse = await fetchAPI('get_vehicles');
                    vehicles = vehiclesResponse || [];
                }
                renderTable();
                document.getElementById('loading-overlay').classList.add('hidden');
            } catch (e) {
                console.error('Load data error:', e);
                document.getElementById('loading-overlay').classList.add('hidden');
            }
        }
        
        // Render table
        function renderTable() {
            const search = document.getElementById('search-input').value.toLowerCase();
            const statusFilter = document.getElementById('status-filter').value;
            const replyFilter = document.getElementById('reply-filter').value;
            
            const newGrid = document.getElementById('new-cases-grid');
            const tbody = document.getElementById('table-body');
            newGrid.innerHTML = '';
            tbody.innerHTML = '';
            
            let newCount = 0;
            let activeCount = 0;
            
            const statusBadgeMap = {
                'New': 'badge-new',
                'Processing': 'badge-processing',
                'Called': 'badge-called',
                'Parts Ordered': 'badge-parts-ordered',
                'Parts Arrived': 'badge-parts-arrived',
                'Scheduled': 'badge-scheduled',
                'Completed': 'badge-completed',
                'Issue': 'badge-issue'
            };

            transfers.forEach(t => {
                const match = (t.plate + t.name + (t.phone || '')).toLowerCase().includes(search);
                if (!match) return;
                if (statusFilter !== 'All' && t.status !== statusFilter) return;
                if (replyFilter !== 'All') {
                    if (replyFilter === 'Pending' && t.user_response && t.user_response !== 'Pending') return;
                    if (replyFilter !== 'Pending' && t.user_response !== replyFilter) return;
                }

                const dateStr = new Date(t.created_at || Date.now()).toLocaleDateString('en-GB', { 
                    month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' 
                });

                if (t.status === 'New') {
                    newCount++;
                    newGrid.innerHTML += `
                        <div class="col-md-4">
                            <div class="card cursor-pointer" onclick="window.openEditModal(${t.id})">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <span class="badge ${statusBadgeMap[t.status]}">${t.status}</span>
                                        <small class="text-muted">${dateStr}</small>
                                    </div>
                                    <h5 class="fw-bold mb-2">${t.plate}</h5>
                                    <p class="text-muted mb-2">${t.name}</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold" style="color: var(--bs-primary);">${t.amount} ₾</span>
                                        ${t.phone ? `<small class="text-muted">${t.phone}</small>` : ''}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    activeCount++;
                    tbody.innerHTML += `
                        <tr class="cursor-pointer" onclick="window.openEditModal(${t.id})">
                            <td>
                                <div class="fw-bold">${t.plate}</div>
                                <small class="text-muted">${t.name}</small>
                            </td>
                            <td>${t.amount} ₾</td>
                            <td><span class="badge ${statusBadgeMap[t.status]}">${t.status}</span></td>
                            <td>${t.phone || 'N/A'}</td>
                            <td><small>${t.service_date || 'N/A'}</small></td>
                            <td><small>${t.user_response || 'Pending'}</small></td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="event.stopPropagation(); window.openEditModal(${t.id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                }
            });

            document.getElementById('new-count').textContent = `(${newCount})`;
            document.getElementById('record-count').textContent = `${activeCount} active`;
            document.getElementById('new-cases-empty').classList.toggle('hidden', newCount > 0);
            document.getElementById('empty-state').classList.toggle('hidden', activeCount > 0);
        }
        
        // Simplified modal open
        window.openEditModal = (id) => {
            const t = transfers.find(i => i.id == id);
            if (!t) return;
            
            document.getElementById('modal-title').textContent = `${t.plate} - ${t.name}`;
            document.getElementById('modal-content').innerHTML = `
                <div class="mb-3">
                    <p><strong>Status:</strong> ${t.status}</p>
                    <p><strong>Amount:</strong> ${t.amount} ₾</p>
                    <p><strong>Phone:</strong> ${t.phone || 'N/A'}</p>
                    <p><strong>Service Date:</strong> ${t.service_date || 'N/A'}</p>
                    <p><strong>Customer Response:</strong> ${t.user_response || 'Pending'}</p>
                </div>
                ${CAN_EDIT ? '<button class="btn btn-primary">Save Changes</button>' : ''}
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('edit-modal'));
            modal.show();
        };
        
        // Parse bank text (placeholder)
        window.parseBankText = () => {
            const text = document.getElementById('import-text').value;
            if (!text) return;
            
            // Simple parsing logic
            parsedImportData = [{
                plate: 'AA123BB',
                name: 'Sample Customer',
                amount: '1234.00',
                franchise: '273.97'
            }];
            
            document.getElementById('parsed-result').classList.remove('hidden');
            document.getElementById('parsed-placeholder').classList.add('hidden');
            document.getElementById('parsed-content').innerHTML = parsedImportData.map(i => 
                `<div class="alert alert-success mb-2">
                    <strong>${i.plate}</strong> - ${i.name} - ${i.amount} ₾
                </div>`
            ).join('');
        };
        
        window.saveParsedImport = async () => {
            for (let data of parsedImportData) {
                await fetchAPI('add_transfer', 'POST', data);
            }
            document.getElementById('import-text').value = '';
            document.getElementById('parsed-result').classList.add('hidden');
            document.getElementById('parsed-placeholder').classList.remove('hidden');
            loadData();
            showToast('Import Successful', `${parsedImportData.length} orders imported`);
        };
        
        window.openManualCreateModal = () => {
            showToast('Feature Coming Soon', 'Manual create modal will be implemented', 'info');
        };
        
        // Render vehicles (placeholder)
        function renderVehicles() {
            const tbody = document.getElementById('vehicles-table-body');
            tbody.innerHTML = vehicles.map(v => `
                <tr>
                    <td class="fw-bold">${v.plate}</td>
                    <td>${v.phone || 'N/A'}</td>
                    <td><small class="text-muted">${new Date(v.created_at || Date.now()).toLocaleDateString()}</small></td>
                    <td><span class="badge bg-secondary">${v.source || 'Manual'}</span></td>
                </tr>
            `).join('');
            
            document.getElementById('vehicles-count').textContent = `${vehicles.length} vehicles`;
            document.getElementById('vehicles-empty').classList.toggle('hidden', vehicles.length > 0);
        }
        
        // Event listeners
        document.getElementById('search-input').addEventListener('input', renderTable);
        document.getElementById('status-filter').addEventListener('change', renderTable);
        document.getElementById('reply-filter').addEventListener('change', renderTable);
        document.getElementById('vehicles-search')?.addEventListener('input', renderVehicles);
        
        // Initialize
        loadData();
    </script>
</body>
</html>
