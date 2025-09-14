<?php
/**
 * System Test Script
 * This script tests the basic functionality of the church appointment system
 */

echo "<h1>Church Appointment System - System Test</h1>";

// Test 1: Database Connection
echo "<h2>Test 1: Database Connection</h2>";
try {
    require_once 'config/database.php';
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    echo "✅ Database connection successful<br>";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
    exit;
}

// Test 2: Check if tables exist
echo "<h2>Test 2: Database Tables</h2>";
$tables = ['users', 'services', 'resources', 'appointments', 'appointment_resources', 'announcements', 'audit_logs', 'notifications', 'settings'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "✅ Table '$table' exists with $count records<br>";
    } catch (Exception $e) {
        echo "❌ Table '$table' error: " . $e->getMessage() . "<br>";
    }
}

// Test 3: Check if admin user exists
echo "<h2>Test 3: Admin User</h2>";
try {
    $stmt = $pdo->query("SELECT username, role FROM users WHERE role = 'admin'");
    $admin = $stmt->fetch();
    if ($admin) {
        echo "✅ Admin user found: " . $admin['username'] . "<br>";
    } else {
        echo "❌ No admin user found<br>";
    }
} catch (Exception $e) {
    echo "❌ Error checking admin user: " . $e->getMessage() . "<br>";
}

// Test 4: Check if default services exist
echo "<h2>Test 4: Default Services</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM services");
    $count = $stmt->fetchColumn();
    if ($count > 0) {
        echo "✅ Default services loaded: $count services<br>";
    } else {
        echo "❌ No services found<br>";
    }
} catch (Exception $e) {
    echo "❌ Error checking services: " . $e->getMessage() . "<br>";
}

// Test 5: Check if default resources exist
echo "<h2>Test 5: Default Resources</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM resources");
    $count = $stmt->fetchColumn();
    if ($count > 0) {
        echo "✅ Default resources loaded: $count resources<br>";
    } else {
        echo "❌ No resources found<br>";
    }
} catch (Exception $e) {
    echo "❌ Error checking resources: " . $e->getMessage() . "<br>";
}

// Test 6: Test file permissions
echo "<h2>Test 6: File Permissions</h2>";
$files_to_check = [
    'index.php',
    'login.php',
    'dashboard.php',
    'appointments.php',
    'new-appointment.php',
    'appointment.php',
    'appointment-status.php',
    'appointment-delete.php',
    'logout.php',
    'resources.php',
    'services.php',
    'users.php',
    'priests.php',
    'announcements.php',
    'includes/header.php',
    'includes/footer.php',
    'includes/AppointmentManager.php',
    'includes/ResourceManager.php',
    'assets/css/style.css',
    'assets/js/main.js'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        if (is_readable($file)) {
            echo "✅ $file exists and is readable<br>";
        } else {
            echo "❌ $file exists but is not readable<br>";
        }
    } else {
        echo "❌ $file does not exist<br>";
    }
}

// Test 7: Test database schema
echo "<h2>Test 7: Database Schema</h2>";
try {
    // Check appointments table structure
    $stmt = $pdo->query("PRAGMA table_info(appointments)");
    $columns = $stmt->fetchAll();
    $required_columns = ['id', 'reference_number', 'user_id', 'service_id', 'priest_id', 'title', 'description', 'start_time', 'end_time', 'contact_name', 'contact_phone', 'contact_email', 'status'];
    
    $found_columns = array_column($columns, 'name');
    $missing_columns = array_diff($required_columns, $found_columns);
    
    if (empty($missing_columns)) {
        echo "✅ Appointments table has all required columns<br>";
    } else {
        echo "❌ Appointments table missing columns: " . implode(', ', $missing_columns) . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Error checking database schema: " . $e->getMessage() . "<br>";
}

echo "<h2>Test Complete</h2>";
echo "<p>If all tests show ✅, the system should be working correctly.</p>";
echo "<p><a href='login.php'>Go to Login Page</a></p>";
?>
