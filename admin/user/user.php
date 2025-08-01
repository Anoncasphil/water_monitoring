
<?php
session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login/index.php');
    exit;
}

require_once '../../config/database.php';



// Check for success message from URL parameters
$showSuccessToast = false;
$successMessage = '';
if (isset($_GET['success']) && $_GET['success'] == '1' && isset($_GET['message'])) {
    $showSuccessToast = true;
    $successMessage = urldecode($_GET['message']);
}

// Check for status update success message
$showStatusSuccessToast = false;
$statusSuccessMessage = '';
if (isset($_GET['status_success']) && $_GET['status_success'] == '1' && isset($_GET['message'])) {
    $showStatusSuccessToast = true;
    $statusSuccessMessage = urldecode($_GET['message']);
}

// Fetch users from database
$users = [];
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $query = "SELECT id, username, first_name, last_name, email, role, status, created_at FROM users ORDER BY created_at DESC";
    $result = $conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
} catch (Exception $e) {
    // If database error, use sample data as fallback
    $users = [
        [
            'id' => 1,
            'username' => 'admin',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'role' => 'admin',
            'email' => 'admin@example.com',
            'created_at' => '2024-01-15'
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Water Quality Monitor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        dark: {
                            bg: '#111827',
                            card: '#1F2937',
                            text: '#F3F4F6',
                            muted: '#9CA3AF'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .gradient-bg {
            background: linear-gradient(135deg, #f6f8fc 0%, #ffffff 100%);
        }
        .dark .gradient-bg {
            background: linear-gradient(135deg, #111827 0%, #1F2937 100%);
        }
    </style>
</head>
<body class="gradient-bg min-h-screen transition-colors duration-200">
    <!-- Include Sidebar -->
    <?php include '../sidebar/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="lg:ml-64">
        <div class="container mx-auto px-4 py-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-white">
                        <i class="fas fa-users text-blue-500 mr-2"></i>
                        User Management
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400 text-sm mt-1">Manage system users and permissions</p>
                </div>
                <div class="flex items-center space-x-4">
                    <button id="themeToggle" class="p-2 rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                        <i class="fas fa-sun text-yellow-500 dark:hidden"></i>
                        <i class="fas fa-moon text-blue-300 hidden dark:block"></i>
                    </button>
                    <button id="addUserBtn" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center transition-colors">
                        <i class="fas fa-plus mr-2"></i>
                        Add User
                    </button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                            <i class="fas fa-users text-blue-500 dark:text-blue-400 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white"><?php echo count($users); ?></h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total Users</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center">
                            <i class="fas fa-user-shield text-green-500 dark:text-green-400 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white"><?php echo count(array_filter($users, fn($u) => $u['role'] === 'admin')); ?></h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Administrators</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 card-hover">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-purple-100 dark:bg-purple-900 flex items-center justify-center">
                            <i class="fas fa-user text-purple-500 dark:text-purple-400 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white"><?php echo count(array_filter($users, fn($u) => $u['role'] === 'staff')); ?></h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Staff Members</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white">User List</h3>
                        
                        <!-- Search and Filter Controls -->
                        <div class="flex flex-col sm:flex-row gap-4">
                            <!-- Search Input -->
                            <div class="relative">
                                <input type="text" id="searchInput" placeholder="Search users..." 
                                       class="w-full sm:w-64 pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            </div>
                            
                            <!-- Role Filter -->
                            <select id="roleFilter" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                                <option value="">All Roles</option>
                                <option value="admin">Administrator</option>
                                <option value="staff">Staff</option>
                            </select>
                            
                            <!-- Status Filter -->
                            <select id="statusFilter" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="userTableBody" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700" 
                                data-username="<?php echo htmlspecialchars(strtolower($user['username'])); ?>"
                                data-name="<?php echo htmlspecialchars(strtolower($user['first_name'] . ' ' . $user['last_name'])); ?>"
                                data-email="<?php echo htmlspecialchars(strtolower($user['email'])); ?>"
                                data-role="<?php echo htmlspecialchars($user['role']); ?>"
                                data-status="<?php echo htmlspecialchars($user['status'] ?? 'active'); ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                                            <i class="fas fa-user text-blue-500 dark:text-blue-400"></i>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                @<?php echo htmlspecialchars($user['username']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $user['role'] === 'admin' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'; ?>">
                                        <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $user['status'] === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'; ?>">
                                        <?php echo ucfirst(htmlspecialchars($user['status'] ?? 'active')); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <button class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 edit-user-btn" 
                                                data-user='<?php echo json_encode($user); ?>'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($user['status'] === 'active'): ?>
                                        <button class="text-orange-600 hover:text-orange-900 dark:text-orange-400 dark:hover:text-orange-300 archive-user-btn" 
                                                data-user-id="<?php echo $user['id']; ?>" 
                                                data-user-name="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>">
                                            <i class="fas fa-archive"></i>
                                        </button>
                                        <?php else: ?>
                                        <button class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300 activate-user-btn" 
                                                data-user-id="<?php echo $user['id']; ?>" 
                                                data-user-name="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>">
                                            <i class="fas fa-user-check"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Toast Notification -->
    <div id="successToast" class="fixed bottom-4 right-4 z-60 hidden">
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg shadow-lg p-4 max-w-sm">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-500 text-xl"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800 dark:text-green-200" id="successMessage">User created successfully!</p>
                </div>
                <div class="ml-auto pl-3">
                    <button id="closeToast" class="text-green-400 hover:text-green-600 dark:hover:text-green-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black bg-opacity-50" id="confirmationOverlay"></div>
        <div class="flex items-center justify-center min-h-screen p-4 relative z-10">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-lg w-full relative z-20">
                <div class="p-8">
                    <!-- Header -->
                    <div class="text-center mb-6">
                        <div class="w-16 h-16 rounded-full bg-orange-100 dark:bg-orange-900 flex items-center justify-center mx-auto mb-4" id="confirmationIcon">
                            <i class="fas fa-user-slash text-orange-500 text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2" id="confirmationTitle">Deactivate Account</h3>
                        <p class="text-gray-600 dark:text-gray-400" id="confirmationMessage">Are you sure you want to deactivate this user's account?</p>
                    </div>
                    
                    <!-- Info Box -->
                    <div class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg p-4 mb-6" id="infoBox">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle text-orange-500 mt-0.5 mr-3"></i>
                            <div class="text-sm text-orange-800 dark:text-orange-200">
                                <p class="font-medium mb-1" id="infoTitle">What happens when you deactivate an account?</p>
                                <ul class="space-y-1 text-xs" id="infoList">
                                    <li>• User will not be able to log in</li>
                                    <li>• Account data is preserved</li>
                                    <li>• Can be reactivated anytime</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex space-x-4">
                        <button id="cancelAction" class="flex-1 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 py-3 px-4 rounded-lg transition-colors font-medium">
                            Cancel
                        </button>
                        <button id="confirmAction" class="flex-1 bg-orange-500 hover:bg-orange-600 text-white py-3 px-4 rounded-lg transition-colors font-medium">
                            Deactivate Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black bg-opacity-50" id="modalOverlay"></div>
        <div class="flex items-center justify-center min-h-screen p-4 relative z-10">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full relative z-20">
                <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white" id="modalTitle">Add New User</h3>
                    <button id="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form id="addUserForm" class="p-6 space-y-4">
                    <!-- Hidden input for user ID when editing -->
                    <input type="hidden" id="userId" name="user_id" value="">
                    
                    <!-- Validation Alert -->
                    <div id="validationAlert" class="hidden p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                            <span class="text-red-700 dark:text-red-300 text-sm" id="alertMessage"></span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Username <span class="text-red-500">*</span></label>
                        <input type="text" name="username" id="username" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">First Name <span class="text-red-500">*</span></label>
                            <input type="text" name="first_name" id="first_name" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Last Name <span class="text-red-500">*</span></label>
                            <input type="text" name="last_name" id="last_name" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email <span class="text-red-500">*</span></label>
                        <input type="email" name="email" id="email" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                        <div id="email-valid" class="mt-1 text-xs hidden">
                            <i class="fas fa-check-circle text-green-500 mr-1"></i>
                            <span class="text-green-600 dark:text-green-400">Valid email address format</span>
                        </div>
                        <div id="email-invalid" class="mt-1 text-xs hidden">
                            <i class="fas fa-times-circle text-red-500 mr-1"></i>
                            <span class="text-red-600 dark:text-red-400">Please enter a valid email address</span>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Role <span class="text-red-500">*</span></label>
                        <select name="role" id="role" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                            <option value="">Select Role</option>
                            <option value="admin">Administrator</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Password <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="password" name="password" id="password" class="w-full px-3 py-2 pr-10 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                            <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <i class="fas fa-eye" id="passwordIcon"></i>
                            </button>
                        </div>
                        
                        <!-- Password Requirements -->
                        <div class="mt-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                            <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">Password Requirements:</p>
                            <div class="space-y-1">
                                <div class="flex items-center text-xs" id="req-length">
                                    <i class="fas fa-circle text-gray-300 dark:text-gray-600 mr-2"></i>
                                    <span class="text-gray-600 dark:text-gray-400">Minimum 8 characters</span>
                                </div>
                                <div class="flex items-center text-xs" id="req-uppercase">
                                    <i class="fas fa-circle text-gray-300 dark:text-gray-600 mr-2"></i>
                                    <span class="text-gray-600 dark:text-gray-400">One uppercase letter (A-Z)</span>
                                </div>
                                <div class="flex items-center text-xs" id="req-number">
                                    <i class="fas fa-circle text-gray-300 dark:text-gray-600 mr-2"></i>
                                    <span class="text-gray-600 dark:text-gray-400">One number (0-9)</span>
                                </div>
                                <div class="flex items-center text-xs" id="req-special">
                                    <i class="fas fa-circle text-gray-300 dark:text-gray-600 mr-2"></i>
                                    <span class="text-gray-600 dark:text-gray-400">One special character (!@#$%^&*)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Confirm Password <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="password" name="confirm_password" id="confirm_password" class="w-full px-3 py-2 pr-10 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                            <button type="button" id="toggleConfirmPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <i class="fas fa-eye" id="confirmPasswordIcon"></i>
                            </button>
                        </div>
                        <div id="password-match" class="mt-1 text-xs hidden">
                            <i class="fas fa-check-circle text-green-500 mr-1"></i>
                            <span class="text-green-600 dark:text-green-400">Passwords match</span>
                        </div>
                        <div id="password-mismatch" class="mt-1 text-xs hidden">
                            <i class="fas fa-times-circle text-red-500 mr-1"></i>
                            <span class="text-red-600 dark:text-red-400">Passwords do not match</span>
                        </div>
                    </div>
                    
                    <div class="flex space-x-3 pt-4">
                        <button type="submit" class="flex-1 bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-lg transition-colors" id="submitBtn">
                            Add User
                        </button>
                        <button type="button" id="cancelAdd" class="flex-1 bg-gray-300 hover:bg-gray-400 dark:bg-gray-600 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 py-2 px-4 rounded-lg transition-colors">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal functionality
        const modal = document.getElementById('addUserModal');
        const addUserBtn = document.getElementById('addUserBtn');
        const closeModal = document.getElementById('closeModal');
        const cancelAdd = document.getElementById('cancelAdd');
        const addUserForm = document.getElementById('addUserForm');

        // Toast notification functionality
        const successToast = document.getElementById('successToast');
        const successMessage = document.getElementById('successMessage');
        const closeToast = document.getElementById('closeToast');

        // Confirmation modal functionality
        const confirmationModal = document.getElementById('confirmationModal');
        const confirmationTitle = document.getElementById('confirmationTitle');
        const confirmationMessage = document.getElementById('confirmationMessage');
        const confirmAction = document.getElementById('confirmAction');
        const cancelAction = document.getElementById('cancelAction');
        const confirmationOverlay = document.getElementById('confirmationOverlay');
        let currentAction = null;
        let currentUserId = null;

        // Search and filter functionality
        const searchInput = document.getElementById('searchInput');
        const roleFilter = document.getElementById('roleFilter');
        const statusFilter = document.getElementById('statusFilter');
        const userTableBody = document.getElementById('userTableBody');

        function openModal(userData = null) {
            // Reset form
            addUserForm.reset();
            hideAlert();
            
            // Clear email validation messages
            emailValid.classList.add('hidden');
            emailInvalid.classList.add('hidden');
            
            // Reset password fields to hidden
            passwordInput.type = 'password';
            confirmPasswordInput.type = 'password';
            passwordIcon.classList.remove('fa-eye-slash');
            passwordIcon.classList.add('fa-eye');
            confirmPasswordIcon.classList.remove('fa-eye-slash');
            confirmPasswordIcon.classList.add('fa-eye');
            
            // Reset password requirements display
            document.getElementById('req-length').innerHTML = `
                <i class="fas fa-circle text-gray-300 dark:text-gray-600 mr-2"></i>
                <span class="text-gray-600 dark:text-gray-400">Minimum 8 characters</span>
            `;
            document.getElementById('req-uppercase').innerHTML = `
                <i class="fas fa-circle text-gray-300 dark:text-gray-600 mr-2"></i>
                <span class="text-gray-600 dark:text-gray-400">One uppercase letter (A-Z)</span>
            `;
            document.getElementById('req-number').innerHTML = `
                <i class="fas fa-circle text-gray-300 dark:text-gray-600 mr-2"></i>
                <span class="text-gray-600 dark:text-gray-400">One number (0-9)</span>
            `;
            document.getElementById('req-special').innerHTML = `
                <i class="fas fa-circle text-gray-300 dark:text-gray-600 mr-2"></i>
                <span class="text-gray-600 dark:text-gray-400">One special character (!@#$%^&*)</span>
            `;
            
            // Clear password match/mismatch messages
            passwordMatch.classList.add('hidden');
            passwordMismatch.classList.add('hidden');
            
            // Clear user ID
            document.getElementById('userId').value = '';
            
            // Set default modal title and button text
            document.getElementById('modalTitle').textContent = 'Add New User';
            document.getElementById('submitBtn').textContent = 'Add User';
            
            // If editing user, populate form
            if (userData) {
                document.getElementById('modalTitle').textContent = 'Edit User';
                document.getElementById('submitBtn').textContent = 'Update User';
                document.getElementById('userId').value = userData.id;
                document.getElementById('username').value = userData.username;
                document.getElementById('first_name').value = userData.first_name;
                document.getElementById('last_name').value = userData.last_name;
                document.getElementById('email').value = userData.email;
                document.getElementById('role').value = userData.role;
                
                // Clear password fields for editing
                document.getElementById('password').value = '';
                document.getElementById('confirm_password').value = '';
                
                // Update password field labels to show they're optional
                const passwordLabel = document.querySelector('label[for="password"]');
                const confirmPasswordLabel = document.querySelector('label[for="confirm_password"]');
                
                if (passwordLabel) {
                    passwordLabel.innerHTML = 'Password <span class="text-gray-500">(leave blank to keep current)</span>';
                }
                if (confirmPasswordLabel) {
                    confirmPasswordLabel.innerHTML = 'Confirm Password <span class="text-gray-500">(leave blank to keep current)</span>';
                }
                
                // Trigger validation to update UI
                checkEmailValidity();
            } else {
                // Reset password field labels for new user
                const passwordLabel = document.querySelector('label[for="password"]');
                const confirmPasswordLabel = document.querySelector('label[for="confirm_password"]');
                
                if (passwordLabel) {
                    passwordLabel.innerHTML = 'Password <span class="text-red-500">*</span>';
                }
                if (confirmPasswordLabel) {
                    confirmPasswordLabel.innerHTML = 'Confirm Password <span class="text-red-500">*</span>';
                }
            }
            
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeModalFunc() {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
            addUserForm.reset();
        }

        function showSuccessToast(message) {
            successMessage.textContent = message;
            successToast.classList.remove('hidden');
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                hideSuccessToast();
            }, 5000);
        }

        function hideSuccessToast() {
            successToast.classList.add('hidden');
        }

        function showConfirmationModal(action, userId, userName) {
            currentAction = action;
            currentUserId = userId;
            
            const confirmationIcon = document.getElementById('confirmationIcon');
            const infoBox = document.getElementById('infoBox');
            const infoTitle = document.getElementById('infoTitle');
            const infoList = document.getElementById('infoList');
            
            if (action === 'archive') {
                // Deactivate Account
                confirmationTitle.textContent = 'Deactivate Account';
                confirmationMessage.textContent = `Are you sure you want to deactivate ${userName}'s account?`;
                confirmAction.textContent = 'Deactivate Account';
                confirmAction.className = 'flex-1 bg-orange-500 hover:bg-orange-600 text-white py-3 px-4 rounded-lg transition-colors font-medium';
                
                // Update icon
                confirmationIcon.className = 'w-16 h-16 rounded-full bg-orange-100 dark:bg-orange-900 flex items-center justify-center mx-auto mb-4';
                confirmationIcon.innerHTML = '<i class="fas fa-user-slash text-orange-500 text-2xl"></i>';
                
                // Update info box
                infoBox.className = 'bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg p-4 mb-6';
                infoTitle.textContent = 'What happens when you deactivate an account?';
                infoList.innerHTML = `
                    <li>• User will not be able to log in</li>
                    <li>• Account data is preserved</li>
                    <li>• Can be reactivated anytime</li>
                `;
                
            } else if (action === 'activate') {
                // Activate Account
                confirmationTitle.textContent = 'Activate Account';
                confirmationMessage.textContent = `Are you sure you want to activate ${userName}'s account?`;
                confirmAction.textContent = 'Activate Account';
                confirmAction.className = 'flex-1 bg-green-500 hover:bg-green-600 text-white py-3 px-4 rounded-lg transition-colors font-medium';
                
                // Update icon
                confirmationIcon.className = 'w-16 h-16 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center mx-auto mb-4';
                confirmationIcon.innerHTML = '<i class="fas fa-user-check text-green-500 text-2xl"></i>';
                
                // Update info box
                infoBox.className = 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-6';
                infoTitle.textContent = 'What happens when you activate an account?';
                infoList.innerHTML = `
                    <li>• User will be able to log in again</li>
                    <li>• All account data remains intact</li>
                    <li>• User regains full access to the system</li>
                `;
            }
            
            confirmationModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function hideConfirmationModal() {
            confirmationModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
            currentAction = null;
            currentUserId = null;
        }

        function updateUserStatus(action, userId) {
            const formData = new FormData();
            formData.append('user_id', userId);
            formData.append('action', action);
            
            fetch('../../api/update_user_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Store success message and reload page
                    const actionText = action === 'archive' ? 'deactivated' : 'activated';
                    const successMessage = data.message;
                    location.href = window.location.pathname + '?status_success=1&message=' + encodeURIComponent(successMessage) + '&action=' + actionText;
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating user status.');
            });
        }

        function filterUsers() {
            const searchTerm = searchInput.value.toLowerCase();
            const selectedRole = roleFilter.value;
            const selectedStatus = statusFilter.value;
            
            const rows = userTableBody.querySelectorAll('tr');
            
            rows.forEach(row => {
                const username = row.getAttribute('data-username') || '';
                const name = row.getAttribute('data-name') || '';
                const email = row.getAttribute('data-email') || '';
                const role = row.getAttribute('data-role') || '';
                const status = row.getAttribute('data-status') || '';
                
                // Check search term
                const matchesSearch = !searchTerm || 
                    username.includes(searchTerm) || 
                    name.includes(searchTerm) || 
                    email.includes(searchTerm);
                
                // Check role filter
                const matchesRole = !selectedRole || role === selectedRole;
                
                // Check status filter
                const matchesStatus = !selectedStatus || status === selectedStatus;
                
                // Show/hide row based on all filters
                if (matchesSearch && matchesRole && matchesStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Update results count
            updateResultsCount();
        }

        function updateResultsCount() {
            const visibleRows = userTableBody.querySelectorAll('tr:not([style*="display: none"])').length;
            const totalRows = userTableBody.querySelectorAll('tr').length;
            
            // You can add a results counter here if needed
            // For example: document.getElementById('resultsCount').textContent = `Showing ${visibleRows} of ${totalRows} users`;
        }

        addUserBtn.addEventListener('click', () => openModal());
        closeModal.addEventListener('click', closeModalFunc);
        cancelAdd.addEventListener('click', closeModalFunc);
        closeToast.addEventListener('click', hideSuccessToast);
        confirmAction.addEventListener('click', function() {
            if (currentAction && currentUserId) {
                updateUserStatus(currentAction, currentUserId);
            }
            hideConfirmationModal();
        });
        cancelAction.addEventListener('click', hideConfirmationModal);
        confirmationOverlay.addEventListener('click', hideConfirmationModal);

        // Add event listeners for edit buttons
        document.addEventListener('click', function(e) {
            if (e.target.closest('.edit-user-btn')) {
                const button = e.target.closest('.edit-user-btn');
                const userData = JSON.parse(button.getAttribute('data-user'));
                openModal(userData);
            }
        });

        // Add event listeners for archive/activate buttons
        document.addEventListener('click', function(e) {
            if (e.target.closest('.archive-user-btn')) {
                const button = e.target.closest('.archive-user-btn');
                const userId = button.getAttribute('data-user-id');
                const userName = button.getAttribute('data-user-name');
                showConfirmationModal('archive', userId, userName);
            }
            
            if (e.target.closest('.activate-user-btn')) {
                const button = e.target.closest('.activate-user-btn');
                const userId = button.getAttribute('data-user-id');
                const userName = button.getAttribute('data-user-name');
                showConfirmationModal('activate', userId, userName);
            }
        });

        // Add event listeners for search and filter
        searchInput.addEventListener('input', filterUsers);
        roleFilter.addEventListener('change', filterUsers);
        statusFilter.addEventListener('change', filterUsers);

        // Close modal when clicking outside
        const modalOverlay = document.getElementById('modalOverlay');
        modalOverlay.addEventListener('click', closeModalFunc);

        // Password validation functionality
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const passwordMatch = document.getElementById('password-match');
        const passwordMismatch = document.getElementById('password-mismatch');

        // Email validation functionality
        const emailInput = document.getElementById('email');
        const emailValid = document.getElementById('email-valid');
        const emailInvalid = document.getElementById('email-invalid');

        // Password toggle functionality
        const togglePassword = document.getElementById('togglePassword');
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        const passwordIcon = document.getElementById('passwordIcon');
        const confirmPasswordIcon = document.getElementById('confirmPasswordIcon');

        function validatePassword(password) {
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)
            };

            // Update requirement indicators
            document.getElementById('req-length').innerHTML = `
                <i class="fas fa-${requirements.length ? 'check-circle text-green-500' : 'circle text-gray-300 dark:text-gray-600'} mr-2"></i>
                <span class="text-${requirements.length ? 'green-600 dark:text-green-400' : 'gray-600 dark:text-gray-400'}">Minimum 8 characters</span>
            `;
            
            document.getElementById('req-uppercase').innerHTML = `
                <i class="fas fa-${requirements.uppercase ? 'check-circle text-green-500' : 'circle text-gray-300 dark:text-gray-600'} mr-2"></i>
                <span class="text-${requirements.uppercase ? 'green-600 dark:text-green-400' : 'gray-600 dark:text-gray-400'}">One uppercase letter (A-Z)</span>
            `;
            
            document.getElementById('req-number').innerHTML = `
                <i class="fas fa-${requirements.number ? 'check-circle text-green-500' : 'circle text-gray-300 dark:text-gray-600'} mr-2"></i>
                <span class="text-${requirements.number ? 'green-600 dark:text-green-400' : 'gray-600 dark:text-gray-400'}">One number (0-9)</span>
            `;
            
            document.getElementById('req-special').innerHTML = `
                <i class="fas fa-${requirements.special ? 'check-circle text-green-500' : 'circle text-gray-300 dark:text-gray-600'} mr-2"></i>
                <span class="text-${requirements.special ? 'green-600 dark:text-green-400' : 'gray-600 dark:text-gray-400'}">One special character (!@#$%^&*)</span>
            `;

            return Object.values(requirements).every(req => req);
        }

        function checkPasswordMatch() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (confirmPassword === '') {
                passwordMatch.classList.add('hidden');
                passwordMismatch.classList.add('hidden');
                return;
            }
            
            if (password === confirmPassword) {
                passwordMatch.classList.remove('hidden');
                passwordMismatch.classList.add('hidden');
            } else {
                passwordMatch.classList.add('hidden');
                passwordMismatch.classList.remove('hidden');
            }
        }

        function validateEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        function checkEmailValidity() {
            const email = emailInput.value;
            
            // Clear email validation messages
            emailValid.classList.add('hidden');
            emailInvalid.classList.add('hidden');
            
            if (email === '') {
                return;
            }
            
            if (validateEmail(email)) {
                emailValid.classList.remove('hidden');
            } else {
                emailInvalid.classList.remove('hidden');
            }
        }

        function togglePasswordVisibility(inputField, iconElement) {
            if (inputField.type === 'password') {
                inputField.type = 'text';
                iconElement.classList.remove('fa-eye');
                iconElement.classList.add('fa-eye-slash');
            } else {
                inputField.type = 'password';
                iconElement.classList.remove('fa-eye-slash');
                iconElement.classList.add('fa-eye');
            }
        }

        // Add event listeners for password validation
        passwordInput.addEventListener('input', function() {
            validatePassword(this.value);
            checkPasswordMatch();
        });

        confirmPasswordInput.addEventListener('input', checkPasswordMatch);

        // Add event listener for email validation
        emailInput.addEventListener('input', checkEmailValidity);

        // Add event listeners for password toggle
        togglePassword.addEventListener('click', () => {
            togglePasswordVisibility(passwordInput, passwordIcon);
        });

        toggleConfirmPassword.addEventListener('click', () => {
            togglePasswordVisibility(confirmPasswordInput, confirmPasswordIcon);
        });

        // Form validation and alert functionality
        const validationAlert = document.getElementById('validationAlert');
        const alertMessage = document.getElementById('alertMessage');
        const formInputs = ['username', 'first_name', 'last_name', 'email', 'role', 'password', 'confirm_password'];

        function showAlert(message) {
            alertMessage.textContent = message;
            validationAlert.classList.remove('hidden');
            validationAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        function hideAlert() {
            validationAlert.classList.add('hidden');
        }

        function validateForm() {
            const formData = new FormData(addUserForm);
            const errors = [];
            const isEditing = formData.get('user_id') !== '';

            // Check required fields (password is optional when editing)
            const requiredFields = isEditing ? ['username', 'first_name', 'last_name', 'email', 'role'] : formInputs;
            requiredFields.forEach(field => {
                const value = formData.get(field);
                if (!value || value.trim() === '') {
                    const fieldName = field.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
                    errors.push(`${fieldName} is required`);
                }
            });

            // Check email format
            const email = formData.get('email');
            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                errors.push('Please enter a valid email address');
            }

            // Check password requirements (only if password is provided)
            const password = formData.get('password');
            const confirmPassword = formData.get('confirm_password');
            
            if (password && password.trim() !== '') {
                if (!validatePassword(password)) {
                    errors.push('Password does not meet all requirements');
                }
                
                if (!confirmPassword || confirmPassword.trim() === '') {
                    errors.push('Please confirm your password');
                } else if (password !== confirmPassword) {
                    errors.push('Passwords do not match');
                }
            } else if (confirmPassword && confirmPassword.trim() !== '') {
                errors.push('Please enter a password');
            }

            return errors;
        }

        // Form submission
        addUserForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const errors = validateForm();
            
            if (errors.length > 0) {
                showAlert(errors.join(', '));
                return;
            }
            
            hideAlert();
            
            // Show loading state
            const submitBtn = addUserForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating User...';
            submitBtn.disabled = true;
            
            // Send data to backend
            const formData = new FormData(addUserForm);
            
            // If editing and password fields are empty, remove them from form data
            const isEditing = formData.get('user_id') !== '';
            if (isEditing) {
                const password = formData.get('password');
                const confirmPassword = formData.get('confirm_password');
                
                if (!password && !confirmPassword) {
                    formData.delete('password');
                    formData.delete('confirm_password');
                }
            }
            
            // Choose the appropriate API endpoint
            const apiEndpoint = isEditing ? '../../api/update_user.php' : '../../api/create_user.php';
            
            fetch(apiEndpoint, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close modal and reload page with success parameter
                    closeModalFunc();
                    location.href = window.location.pathname + '?success=1&message=' + encodeURIComponent(data.message);
                } else {
                    // Show error message
                    if (data.errors && data.errors.length > 0) {
                        showAlert(data.errors.join(', '));
                    } else {
                        showAlert(data.message);
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred while creating the user. Please try again.');
            })
            .finally(() => {
                // Reset button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });

        // Hide alert when user starts typing
        formInputs.forEach(field => {
            const input = document.getElementById(field);
            if (input) {
                input.addEventListener('input', hideAlert);
            }
        });

        // Dark mode toggle
        const themeToggle = document.getElementById('themeToggle');
        const html = document.documentElement;

        themeToggle.addEventListener('click', () => {
            html.classList.toggle('dark');
            localStorage.theme = html.classList.contains('dark') ? 'dark' : 'light';
        });

        // Initialize theme
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            html.classList.add('dark');
        } else {
            html.classList.remove('dark');
        }

        // Check for success message on page load
        <?php if ($showSuccessToast): ?>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                showSuccessToast('<?php echo addslashes($successMessage); ?>');
            }, 500); // Small delay to ensure page is fully loaded
        });
        <?php endif; ?>

        // Check for status update success message on page load
        <?php if ($showStatusSuccessToast): ?>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                showSuccessToast('<?php echo addslashes($statusSuccessMessage); ?>');
            }, 500); // Small delay to ensure page is fully loaded
        });
        <?php endif; ?>
    </script>
</body>
</html>
