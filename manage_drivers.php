<?php
session_start();
require_once('../config/connection.php');

// Verify database connection
if (!isset($conn)) {
    die("Database connection failed. Please check config/connection.php");
}

// Check if logged in AND if the role is Admin (1)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    header("Location: ../auth/login.php");
    exit();
}

// Initialize variables
$success_message = '';
$error_message = '';

// Handle Add Driver
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_driver'])) {
    $driver_name = trim($_POST['driver_name']);
    $vehicle_name = trim($_POST['vehicle_name']);
    $plate_number = trim($_POST['plate_number']);
    
    if (!empty($driver_name) && !empty($vehicle_name) && !empty($plate_number)) {
        $insert_query = "INSERT INTO drivers (driver_name, vehicle_name, plate_number) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("sss", $driver_name, $vehicle_name, $plate_number);
        
        if ($stmt->execute()) {
            $success_message = "Driver added successfully!";
        } else {
            $error_message = "Error adding driver. Please try again.";
        }
    } else {
        $error_message = "All fields are required!";
    }
}

// Handle Delete Driver
if (isset($_GET['delete'])) {
    $driver_id = intval($_GET['delete']);
    $delete_query = "DELETE FROM drivers WHERE id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $driver_id);
    
    if ($stmt->execute()) {
        $success_message = "Driver deleted successfully!";
    } else {
        $error_message = "Error deleting driver. Please try again.";
    }
}

// Handle Update Driver
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_driver'])) {
    $driver_id = intval($_POST['driver_id']);
    $driver_name = trim($_POST['driver_name']);
    $vehicle_name = trim($_POST['vehicle_name']);
    $plate_number = trim($_POST['plate_number']);
    
    if (!empty($driver_name) && !empty($vehicle_name) && !empty($plate_number)) {
        $update_query = "UPDATE drivers SET driver_name = ?, vehicle_name = ?, plate_number = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("sssi", $driver_name, $vehicle_name, $plate_number, $driver_id);
        
        if ($stmt->execute()) {
            $success_message = "Driver updated successfully!";
        } else {
            $error_message = "Error updating driver. Please try again.";
        }
    } else {
        $error_message = "All fields are required!";
    }
}

// Get all drivers
$drivers_query = "SELECT * FROM drivers ORDER BY driver_name ASC";
$drivers_result = $conn->query($drivers_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Drivers - VehiQuest</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e7e34 0%, #f39c12 100%);
            min-height: 100vh;
            padding: 20px;
        }

        /* ── Shared header (matches all admin pages) ── */
        .header {
            background: linear-gradient(135deg, #1e7e34 0%, #f39c12 100%);
            padding: 25px 30px;
            border-radius: 0 0 15px 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            margin-bottom: 30px;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 45px;
            height: 45px;
            background: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .logo-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .logo-text h1 {
            color: white;
            font-size: 24px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
            margin: 0;
            font-weight: 700;
        }

        .logo-text p {
            color: rgba(255,255,255,0.9);
            font-size: 12px;
            margin: 0;
        }

        .nav-menu {
            display: flex;
            gap: 15px;
            padding-top: 20px;
            border-top: 2px solid rgba(255,255,255,0.2);
        }

        .nav-btn {
            padding: 12px 25px;
            background: rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .nav-btn:hover { background: rgba(255,255,255,0.3); }
        .nav-btn.active { background: white; color: #1e7e34; }

        .logout-btn {
            padding: 12px 25px;
            background: rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: bold;
        }

        /* ── Page content ── */
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .card h2 {
            color: #1e7e34;
            margin-bottom: 20px;
            font-size: 24px;
        }

        .alert {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 500;
            font-size: 14px;
            display: none;
        }

        .alert.show {
            display: block;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .form-group { margin-bottom: 20px; }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #1e7e34;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1e7e34 0%, #f39c12 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30, 126, 52, 0.3);
        }

        .table-container { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table th {
            background: linear-gradient(135deg, #1e7e34 0%, #f39c12 100%);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }

        table td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        table tr:hover { background: #f8f9fa; }

        .action-buttons { display: flex; gap: 10px; }

        .btn-edit {
            background: #007bff;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-edit:hover  { background: #0056b3; }
        .btn-delete:hover { background: #c82333; }

        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active { display: flex; }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h3 { color: #1e7e34; font-size: 20px; }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
    </style>
</head>
<body>

<!-- ── Header (matches all admin pages) ── -->
<div class="header">
    <div class="header-top">
        <div class="logo">
            <div class="logo-icon">
                <img src="../assets/images/isu-logo.png" alt="ISU Logo">
            </div>
            <div class="logo-text">
                <h1>VehiQuest</h1>
                <p>Admin Dashboard</p>
            </div>
        </div>
        <a href="../auth/logout.php" class="logout-btn">Logout</a>
    </div>
    <div class="nav-menu">
        <a href="requests.php" class="nav-btn">Manage Requests</a>
        <a href="manage_drivers.php" class="nav-btn active">Manage Drivers</a>
    </div>
</div>

<div class="container">

        <!-- Messages -->
        <?php if (isset($success_message) && !empty($success_message)): ?>
            <div class="alert alert-success show"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message) && !empty($error_message)): ?>
            <div class="alert alert-error show"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Add Driver Form -->
        <div class="card">
            <h2>Add New Driver</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Driver Name</label>
                    <input type="text" name="driver_name" placeholder="Enter driver's full name" required>
                </div>
                <div class="form-group">
                    <label>Vehicle Name</label>
                    <input type="text" name="vehicle_name" placeholder="Enter vehicle name/model" required>
                </div>
                <div class="form-group">
                    <label>Plate Number</label>
                    <input type="text" name="plate_number" placeholder="Enter plate number (e.g., ABC-1234)" required>
                </div>
                <button type="submit" name="add_driver" class="btn btn-primary">Add Driver</button>
            </form>
        </div>

        <!-- Drivers List -->
        <div class="card">
            <h2>All Drivers</h2>
            <div class="table-container">
                <?php if ($drivers_result->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Driver Name</th>
                                <th>Vehicle Name</th>
                                <th>Plate Number</th>
                                <th>Added On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($driver = $drivers_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $driver['id']; ?></td>
                                    <td><?php echo htmlspecialchars($driver['driver_name']); ?></td>
                                    <td><?php echo htmlspecialchars($driver['vehicle_name']); ?></td>
                                    <td><?php echo htmlspecialchars($driver['plate_number']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($driver['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-edit" onclick="editDriver(<?php echo htmlspecialchars(json_encode($driver)); ?>)">Edit</button>
                                            <button class="btn-delete" onclick="confirmDelete(<?php echo $driver['id']; ?>, '<?php echo htmlspecialchars($driver['driver_name']); ?>')">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No drivers found. Add your first driver above!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <!-- Edit Driver Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Driver</h3>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="driver_id" id="edit_driver_id">
                <div class="form-group">
                    <label>Driver Name</label>
                    <input type="text" name="driver_name" id="edit_driver_name" required>
                </div>
                <div class="form-group">
                    <label>Vehicle Name</label>
                    <input type="text" name="vehicle_name" id="edit_vehicle_name" required>
                </div>
                <div class="form-group">
                    <label>Plate Number</label>
                    <input type="text" name="plate_number" id="edit_plate_number" required>
                </div>
                <button type="submit" name="update_driver" class="btn btn-primary">Update Driver</button>
            </form>
        </div>
    </div>

    <script>
        function editDriver(driver) {
            document.getElementById('edit_driver_id').value = driver.id;
            document.getElementById('edit_driver_name').value = driver.driver_name;
            document.getElementById('edit_vehicle_name').value = driver.vehicle_name;
            document.getElementById('edit_plate_number').value = driver.plate_number;
            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        function confirmDelete(id, name) {
            if (confirm('Are you sure you want to delete driver "' + name + '"?\n\nThis action cannot be undone.')) {
                window.location.href = 'manage_drivers.php?delete=' + id;
            }
        }

        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>