<?php
// API endpoint to calculate distance using Google Maps Distance Matrix API
header('Content-Type: application/json');

// ============================================
// GOOGLE MAPS API CONFIGURATION
// ============================================
// Get your API key from: https://console.cloud.google.com/
define('GOOGLE_MAPS_API_KEY', 'YOUR_API_KEY_HERE'); // ⚠️ REPLACE WITH YOUR API KEY

// Origin: ISU Ilagan Campus
define('ORIGIN_ADDRESS', 'Isabela State University, Ilagan City, Isabela, Philippines');
define('ORIGIN_LAT', '17.1453'); // ISU Ilagan coordinates
define('ORIGIN_LNG', '121.8840');

// Get destination from request
$destination = $_GET['destination'] ?? '';

if (empty($destination)) {
    echo json_encode([
        'success' => false,
        'error' => 'Destination is required'
    ]);
    exit;
}

// Check if API key is configured
if (GOOGLE_MAPS_API_KEY === 'YOUR_API_KEY_HERE') {
    echo json_encode([
        'success' => false,
        'error' => 'Google Maps API key not configured. Please update calculate_distance.php',
        'setup_required' => true
    ]);
    exit;
}

// Build Google Maps Distance Matrix API URL
$origin = urlencode(ORIGIN_ADDRESS);
$dest = urlencode($destination . ', Philippines'); // Add Philippines for better accuracy
$url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins={$origin}&destinations={$dest}&key=" . GOOGLE_MAPS_API_KEY;

// Make API request
$response = file_get_contents($url);
$data = json_decode($response, true);

// Check if request was successful
if ($data['status'] !== 'OK') {
    echo json_encode([
        'success' => false,
        'error' => 'Unable to calculate distance. Status: ' . $data['status'],
        'raw_response' => $data
    ]);
    exit;
}

// Extract distance information
$element = $data['rows'][0]['elements'][0];

if ($element['status'] !== 'OK') {
    echo json_encode([
        'success' => false,
        'error' => 'Destination not found or unreachable',
        'status' => $element['status']
    ]);
    exit;
}

// Get distance in kilometers (one way)
$distance_meters = $element['distance']['value'];
$distance_km = round($distance_meters / 1000, 1);
$distance_text = $element['distance']['text'];

// Get duration
$duration_seconds = $element['duration']['value'];
$duration_text = $element['duration']['text'];

// Calculate round trip
$round_trip_km = $distance_km * 2;

// Estimate fuel consumption (average: 10 km per liter)
$estimated_fuel = round($round_trip_km / 10, 2);

// Estimate oil (0.5 liters per 100 km)
$estimated_oil = round(($round_trip_km / 100) * 0.5, 2);

// Estimate gear oil (0.3 liters per 100 km)
$estimated_gear_oil = round(($round_trip_km / 100) * 0.3, 2);

// Estimate grease (75 grams per 100 km)
$estimated_grease = round(($round_trip_km / 100) * 75, 0);

// Return success response
echo json_encode([
    'success' => true,
    'origin' => ORIGIN_ADDRESS,
    'destination' => $destination,
    'one_way_distance_km' => $distance_km,
    'one_way_distance_text' => $distance_text,
    'round_trip_km' => $round_trip_km,
    'duration_text' => $duration_text,
    'duration_seconds' => $duration_seconds,
    'estimated_fuel_liters' => $estimated_fuel,
    'estimated_oil_liters' => $estimated_oil,
    'estimated_gear_oil_liters' => $estimated_gear_oil,
    'estimated_grease_grams' => $estimated_grease,
    'calculation_method' => 'Google Maps Distance Matrix API'
]);
?>
