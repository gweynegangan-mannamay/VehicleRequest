<?php
session_start();
require_once('../config/connection.php');

// Check if logged in AND if the role is Admin (1)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    header("Location: ../auth/login.php");
    exit();
}

// Declare $conn for static analysis
/** @var mysqli $conn */

$request_id = $_GET['request_id'] ?? 0;

// Fetch request data
$query = "SELECT * FROM trip_requests WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Request not found");
}

$request = $result->fetch_assoc();

// Fetch all drivers from database
$drivers_query = "SELECT * FROM drivers ORDER BY driver_name ASC";
$drivers_result = $conn->query($drivers_query);

// Fetch all destinations from database
$destinations_query = "SELECT * FROM destinations ORDER BY destination_name ASC";
$destinations_result = $conn->query($destinations_query);

// Store destinations in array for JavaScript
$destinations_data = [];
if ($destinations_result) {
    while ($dest = $destinations_result->fetch_assoc()) {
        $destinations_data[] = $dest;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Trip Ticket from Request</title>
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
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: white;
            font-size: 28px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .back-btn {
            padding: 12px 25px;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: bold;
            border: 2px solid rgba(255,255,255,0.3);
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        .container {
            background: white;
            padding: 35px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 1200px;
            margin: 0 auto;
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .request-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 4px solid #1e7e34;
        }

        .request-info h3 {
            color: #1e7e34;
            margin-bottom: 15px;
        }

        .request-info p {
            margin-bottom: 8px;
            color: #555;
        }

        .request-info strong {
            color: #333;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section h2 {
            color: #1e7e34;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid;
            border-image: linear-gradient(90deg, #1e7e34, #f39c12) 1;
            font-size: 22px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 600;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #1e7e34;
            background: white;
            box-shadow: 0 0 0 3px rgba(30, 126, 52, 0.1);
            transform: translateY(-2px);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1e7e34 0%, #f39c12 100%);
            color: white;
            padding: 14px 35px;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(102, 126, 234, 0.6);
        }
    </style>
</head>
<body>

<div class="header">
    <div class="header-top">
        <h1>Create Trip Ticket from Request</h1>
        <a href="../admin/requests.php" class="back-btn">← Back to Requests</a>
    </div>
</div>

<div class="container">
    <div class="request-info">
        <h3>Request Information</h3>
        <p><strong>Requester:</strong> <?php echo htmlspecialchars($request['requester_name']); ?> (<?php echo htmlspecialchars($request['requester_position']); ?>)</p>
        <p><strong>Department:</strong> <?php echo htmlspecialchars($request['department']); ?></p>
        <p><strong>Passengers:</strong> <?php echo htmlspecialchars($request['passenger_names']); ?></p>
        <p><strong>Destination:</strong> <?php echo htmlspecialchars($request['destination']); ?></p>
        <p><strong>Purpose:</strong> <?php echo htmlspecialchars($request['purpose']); ?></p>
        <p><strong>Trip Date:</strong> <?php echo date('F j, Y', strtotime($request['trip_date'])); ?></p>
    </div>

    <form id="tripTicketForm" method="POST" action="save_and_print_ticket.php">
        <!-- Hidden field to pass request ID -->
        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
        
        <!-- Section A: Administrative Officials -->
        <div class="form-section">
            <h2>A. Administrative Officials Authorization</h2>
            
            <div class="form-group">
                <label for="driver_select">Select Driver:</label>
                <select name="driver_id" id="driver_select" required onchange="fillDriverInfo()">
                    <option value="">-- Select Driver --</option>
                    <?php while($driver = $drivers_result->fetch_assoc()): ?>
                        <option value="<?php echo $driver['id']; ?>" 
                                data-name="<?php echo htmlspecialchars($driver['driver_name']); ?>"
                                data-vehicle="<?php echo htmlspecialchars($driver['vehicle_name']); ?>"
                                data-plate="<?php echo htmlspecialchars($driver['plate_number']); ?>">
                            <?php echo htmlspecialchars($driver['driver_name']); ?> - <?php echo htmlspecialchars($driver['vehicle_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="driver_name">Name of Driver:</label>
                    <input type="text" id="driver_name" name="driver_name" readonly>
                </div>
                <div class="form-group">
                    <label for="vehicle_name">Vehicle Name:</label>
                    <input type="text" id="vehicle_name" name="vehicle_name" readonly>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="requester_name">Name of Requester (Authorized Passenger):</label>
                    <input type="text" id="requester_name" name="requester_name" value="<?php echo htmlspecialchars($request['requester_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="plate_number">Plate Number:</label>
                    <input type="text" id="plate_number" name="plate_number" readonly>
                </div>
            </div>

            <div class="form-group">
                <label for="passenger_names">All Passenger Names (comma-separated):</label>
                <textarea id="passenger_names" name="passenger_names" rows="2"><?php echo htmlspecialchars($request['passenger_names']); ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="place_visited">Place to be Visited/Inspected:</label>
                    <div style="position: relative;">
                        <input type="text" id="place_visited" name="place_visited" value="<?php echo htmlspecialchars($request['destination']); ?>" required list="destinations-list" onchange="autoCalculateTrip()" onblur="calculateGPSDistance()">
                        <datalist id="destinations-list">
                            <?php foreach($destinations_data as $dest): ?>
                                <option value="<?php echo htmlspecialchars($dest['destination_name']); ?>" 
                                        data-distance="<?php echo $dest['distance_km']; ?>"
                                        data-fuel="<?php echo $dest['estimated_fuel_liters']; ?>"
                                        data-oil="<?php echo $dest['estimated_oil_liters']; ?>"
                                        data-gear-oil="<?php echo $dest['estimated_gear_oil_liters']; ?>"
                                        data-grease="<?php echo $dest['estimated_grease_grams']; ?>">
                            <?php endforeach; ?>
                        </datalist>
                        <div id="gps-loading" style="display: none; position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #1e7e34; font-size: 12px;">
                            🌍 Calculating distance...
                        </div>
                    </div>
                    <small style="color: #666; font-size: 12px;">💡 Type any destination - GPS will calculate distance automatically</small>
                </div>
                <div class="form-group">
                    <label for="purpose">Purpose:</label>
                    <input type="text" id="purpose" name="purpose" value="<?php echo htmlspecialchars($request['purpose']); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="authorized_by">Authorized By (Name & Position):</label>
                <input type="text" id="authorized_by" name="authorized_by" value="Alfonso R. Simon" required>
            </div>
        </div>

        <!-- Section B: Driver Information -->
        <div class="form-section">
            <h2>B. To be Filled Up by the Driver</h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="departure_date">Date and Time of Departure:</label>
                    <input type="datetime-local" id="departure_date" name="departure_date" value="<?php echo date('Y-m-d\TH:i', strtotime($request['trip_date'] . ' ' . $request['departure_time'])); ?>" required>
                </div>
                <div class="form-group">
                    <label for="arrival_date">Date and Time of Arrival:</label>
                    <input type="datetime-local" id="arrival_date" name="arrival_date" value="<?php echo $request['return_time'] ? date('Y-m-d\TH:i', strtotime($request['trip_date'] . ' ' . $request['return_time'])) : ''; ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="items_purchased">Appropriate Items Purchased and Consumed:</label>
                <textarea id="items_purchased" name="items_purchased"></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="gasoline_issued">Gasoline Issued:</label>
                    <input type="number" step="0.01" id="gasoline_issued" name="gasoline_issued" placeholder="Liters">
                </div>
                <div class="form-group">
                    <label for="gasoline_purchased">Additional Purchase During Trip:</label>
                    <input type="number" step="0.01" id="gasoline_purchased" name="gasoline_purchased" placeholder="Liters">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="oil_issued">Lubricating Oil Issued:</label>
                    <input type="number" step="0.01" id="oil_issued" name="oil_issued" placeholder="Liters">
                </div>
                <div class="form-group">
                    <label for="gear_oil">Gear Oil Issued:</label>
                    <input type="number" step="0.01" id="gear_oil" name="gear_oil" placeholder="Liters">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="grease_issued">Grease Issued:</label>
                    <input type="text" id="grease_issued" name="grease_issued" placeholder="Amount">
                </div>
                <div class="form-group">
                    <label for="speedometer_start">Speedometer at Start:</label>
                    <input type="number" step="0.1" id="speedometer_start" name="speedometer_start" placeholder="miles/kms">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="speedometer_end">Speedometer at End:</label>
                    <input type="number" step="0.1" id="speedometer_end" name="speedometer_end" placeholder="miles/kms">
                </div>
                <div class="form-group">
                    <label for="distance_traveled">Distance Traveled:</label>
                    <input type="number" step="0.1" id="distance_traveled" name="distance_traveled" placeholder="miles/kms" readonly>
                </div>
            </div>

            <div class="form-group">
                <label for="remarks">Remarks:</label>
                <textarea id="remarks" name="remarks"><?php echo htmlspecialchars($request['special_requirements'] ?? ''); ?></textarea>
            </div>
        </div>

        <button type="submit" class="btn-primary">Generate & Print Trip Ticket</button>
    </form>
</div>

<script>
// Destinations data from PHP
const destinationsData = <?php echo json_encode($destinations_data); ?>;

// Auto-calculate on page load
window.addEventListener('load', function() {
    autoCalculateTrip();
});

function fillDriverInfo() {
    const select = document.getElementById('driver_select');
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption.value) {
        document.getElementById('driver_name').value = selectedOption.getAttribute('data-name');
        document.getElementById('vehicle_name').value = selectedOption.getAttribute('data-vehicle');
        document.getElementById('plate_number').value = selectedOption.getAttribute('data-plate');
    } else {
        document.getElementById('driver_name').value = '';
        document.getElementById('vehicle_name').value = '';
        document.getElementById('plate_number').value = '';
    }
}

// Auto-calculate trip requirements based on destination
function autoCalculateTrip() {
    const placeVisited = document.getElementById('place_visited').value;
    
    // Find matching destination
    const destination = destinationsData.find(dest => dest.destination_name === placeVisited);
    
    if (destination) {
        // Auto-fill gasoline
        document.getElementById('gasoline_issued').value = parseFloat(destination.estimated_fuel_liters).toFixed(2);
        
        // Auto-fill lubricating oil
        document.getElementById('oil_issued').value = parseFloat(destination.estimated_oil_liters).toFixed(2);
        
        // Auto-fill gear oil
        document.getElementById('gear_oil').value = parseFloat(destination.estimated_gear_oil_liters).toFixed(2);
        
        // Auto-fill grease
        document.getElementById('grease_issued').value = parseFloat(destination.estimated_grease_grams).toFixed(0) + ' grams';
        
        // Get current speedometer reading (you can set a default or leave empty)
        const currentSpeedometer = parseFloat(document.getElementById('speedometer_start').value) || 0;
        
        // Calculate round trip distance (distance * 2)
        const roundTripDistance = parseFloat(destination.distance_km) * 2;
        
        // Auto-fill speedometer end (current + round trip distance)
        if (currentSpeedometer > 0) {
            document.getElementById('speedometer_end').value = (currentSpeedometer + roundTripDistance).toFixed(1);
            document.getElementById('distance_traveled').value = roundTripDistance.toFixed(1);
        }
        
        // Show notification
        showNotification(`Auto-calculated for ${placeVisited}: ${roundTripDistance} km round trip`);
    }
}

// GPS-based distance calculation using Google Maps API
let gpsCalculationTimeout;
function calculateGPSDistance() {
    const destination = document.getElementById('place_visited').value.trim();
    
    if (!destination || destination.length < 3) {
        return; // Don't calculate for very short inputs
    }
    
    // Clear any existing timeout
    clearTimeout(gpsCalculationTimeout);
    
    // Debounce: wait 1 second after user stops typing
    gpsCalculationTimeout = setTimeout(() => {
        // Show loading indicator
        document.getElementById('gps-loading').style.display = 'block';
        
        // Make API request
        fetch('../api/calculate_distance.php?destination=' + encodeURIComponent(destination))
            .then(response => response.json())
            .then(data => {
                // Hide loading indicator
                document.getElementById('gps-loading').style.display = 'none';
                
                if (data.success) {
                    // Auto-fill based on GPS calculation
                    document.getElementById('gasoline_issued').value = data.estimated_fuel_liters.toFixed(2);
                    document.getElementById('oil_issued').value = data.estimated_oil_liters.toFixed(2);
                    document.getElementById('gear_oil').value = data.estimated_gear_oil_liters.toFixed(2);
                    document.getElementById('grease_issued').value = data.estimated_grease_grams + ' grams';
                    
                    // Calculate speedometer end if start is entered
                    const currentSpeedometer = parseFloat(document.getElementById('speedometer_start').value) || 0;
                    if (currentSpeedometer > 0) {
                        document.getElementById('speedometer_end').value = (currentSpeedometer + data.round_trip_km).toFixed(1);
                        document.getElementById('distance_traveled').value = data.round_trip_km.toFixed(1);
                    }
                    
                    // Show success notification with details
                    showNotification(`🌍 GPS Calculated: ${data.one_way_distance_text} one-way, ${data.round_trip_km} km round trip (${data.duration_text})`);
                } else if (data.setup_required) {
                    // API key not configured - fall back to database calculation
                    console.log('GPS calculation not available, using database values');
                    autoCalculateTrip();
                } else {
                    // Show error but don't block user
                    console.error('GPS calculation failed:', data.error);
                    // Try database calculation as fallback
                    autoCalculateTrip();
                }
            })
            .catch(error => {
                // Hide loading indicator
                document.getElementById('gps-loading').style.display = 'none';
                console.error('Error calculating distance:', error);
                // Fall back to database calculation
                autoCalculateTrip();
            });
    }, 1000); // Wait 1 second after user stops typing
}

// Update distance when speedometer values change
document.getElementById('speedometer_end').addEventListener('input', function() {
    const start = parseFloat(document.getElementById('speedometer_start').value) || 0;
    const end = parseFloat(this.value) || 0;
    const distance = end - start;
    document.getElementById('distance_traveled').value = distance > 0 ? distance.toFixed(1) : '';
});

document.getElementById('speedometer_start').addEventListener('input', function() {
    const start = parseFloat(this.value) || 0;
    const end = parseFloat(document.getElementById('speedometer_end').value) || 0;
    const distance = end - start;
    document.getElementById('distance_traveled').value = distance > 0 ? distance.toFixed(1) : '';
    
    // Re-calculate if destination is selected
    autoCalculateTrip();
});

// Show notification function
function showNotification(message) {
    // Create notification element
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: linear-gradient(135deg, #1e7e34 0%, #f39c12 100%);
        color: white;
        padding: 15px 25px;
        border-radius: 10px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.3);
        z-index: 10000;
        animation: slideIn 0.3s ease-out;
    `;
    notification.textContent = '✓ ' + message;
    
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(400px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(400px); opacity: 0; }
    }
`;
document.head.appendChild(style);
</script>

</body>
</html>
