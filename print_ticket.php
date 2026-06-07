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

$trip_id = $_GET['trip_id'] ?? 0;

// Fetch trip data
$query = "SELECT * FROM trips WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $trip_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Trip ticket not found");
}

$trip = $result->fetch_assoc();
$current_date = date('F j, Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver's Trip Ticket - Print</title>
    <style>
        @page {
            size: letter;
            margin: 0.5in;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #000;
            background: white;
            padding: 20px;
        }

        .ticket-container {
            max-width: 8.5in;
            margin: 0 auto;
            background: white;
        }

        .header {
            text-align: right;
            margin-bottom: 20px;
        }

        .header .date {
            font-weight: bold;
        }

        .title {
            text-align: center;
            font-weight: bold;
            font-size: 14pt;
            margin-bottom: 20px;
            text-decoration: underline;
        }

        .section {
            margin-bottom: 15px;
        }

        .section-header {
            font-weight: bold;
            margin-bottom: 8px;
        }

        .field-row {
            margin-bottom: 5px;
            display: flex;
            align-items: baseline;
        }

        .field-label {
            min-width: 40px;
            font-weight: bold;
        }

        .field-value {
            flex: 1;
            border-bottom: 1px solid #000;
            padding: 0 5px;
            min-height: 20px;
        }

        .indent {
            margin-left: 40px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        table td {
            border: 1px solid #000;
            padding: 5px;
            min-height: 25px;
        }

        table td.label {
            width: 60%;
            padding-left: 10px;
        }

        table td.value {
            width: 40%;
            text-align: center;
        }

        .signature-block {
            text-align: center;
            margin-top: 50px;
        }

        .signature-line {
            border-top: 1px solid #000;
            width: 250px;
            margin: 0 auto 5px;
        }

        .certification {
            margin-top: 30px;
            font-style: italic;
        }

        .passenger-table {
            margin-top: 20px;
        }

        .passenger-table table td {
            text-align: center;
        }

        @media print {
            body {
                padding: 0;
            }
            
            .no-print {
                display: none !important;
            }
        }

        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
        }

        .print-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(102, 126, 234, 0.6);
        }

        .back-button {
            position: fixed;
            top: 20px;
            left: 20px;
            padding: 12px 24px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }

        .back-button:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>

<a href="../admin/index.php" class="back-button no-print">← Back</a>
<button class="print-button no-print" onclick="window.print()">🖨️ Print Ticket</button>

<div class="ticket-container">
    <div class="header">
        <div class="date"><?php echo $current_date; ?></div>
        <div>Date</div>
    </div>

    <div class="title">DRIVER'S TRIP TICKET</div>

    <!-- Section A -->
    <div class="section">
        <div class="section-header">A. To be filled up by the Administrative Officials Authorizing Official Travel</div>
        
        <div class="field-row indent">
            <span class="field-label">1.</span>
            <span>Name of the driver to the vehicle: </span>
            <span class="field-value" style="text-transform: uppercase; font-weight: bold;"><?php echo htmlspecialchars($trip['driver_name']); ?></span>
        </div>

        <div class="field-row indent">
            <span class="field-label">2.</span>
            <span>Name of authorized passenger: </span>
            <span class="field-value" style="text-transform: uppercase; font-weight: bold;"><?php echo htmlspecialchars($trip['requester_name'] ?? $trip['passenger_name']); ?></span>
        </div>

        <div class="field-row indent">
            <span class="field-label">3.</span>
            <span>Government car to be used Plate: </span>
            <span class="field-value" style="text-transform: uppercase; font-weight: bold;"><?php echo htmlspecialchars($trip['plate_number']); ?></span>
        </div>

        <div class="field-row indent">
            <span class="field-label">4.</span>
            <span>Place to be visited inspected: </span>
            <span class="field-value" style="text-transform: uppercase; font-weight: bold;"><?php echo htmlspecialchars($trip['place_visited']); ?></span>
        </div>

        <div class="field-row indent">
            <span class="field-label">5.</span>
            <span>Purpose: </span>
            <span class="field-value"><?php echo htmlspecialchars($trip['purpose']); ?></span>
        </div>

        <div class="signature-block" style="margin-top: 30px;">
            <div class="signature-line"></div>
            <div style="text-transform: uppercase; font-weight: bold;"><?php echo htmlspecialchars($trip['authorized_by']); ?></div>
            <div style="font-size: 10pt;">Director, AFS/Authorized Representative</div>
        </div>
    </div>

    <!-- Section B -->
    <div class="section" style="margin-top: 30px;">
        <div class="section-header">B. To be filled up by the driver:</div>
        
        <div class="field-row indent">
            <span class="field-label">6.</span>
            <span>Date and Time of departure from office/garage: </span>
            <span class="field-value"><?php echo $trip['departure_date'] ? date('F j, Y g:i A', strtotime($trip['departure_date'])) : ''; ?></span>
        </div>

        <div class="field-row indent">
            <span class="field-label">7.</span>
            <span>Date and Time of arrival: </span>
            <span class="field-value"><?php echo $trip['arrival_date'] ? date('F j, Y g:i A', strtotime($trip['arrival_date'])) : ''; ?></span>
        </div>

        <div class="field-row indent">
            <span class="field-label">8.</span>
            <span>Appropriate issued purchased and consumed: </span>
            <span class="field-value"><?php echo htmlspecialchars($trip['items_purchased']); ?></span>
        </div>

        <div class="field-row indent">
            <span class="field-label">9.</span>
            <span>Official Time of departure back to office/garage: </span>
            <span class="field-value"></span>
        </div>

        <div class="field-row indent">
            <span class="field-label">10.</span>
            <span>Date and Time of arrival back to office/garage: </span>
            <span class="field-value"></span>
        </div>

        <div class="field-row indent">
            <span class="field-label">11.</span>
            <span>Gasoline issued purchased and consumed: </span>
        </div>

        <table style="margin-left: 40px; width: calc(100% - 40px);">
            <tr>
                <td class="label">a. Balance in tank:</td>
                <td class="value">(estimate in ½, ⅓ and ¼ full)</td>
            </tr>
            <tr>
                <td class="label">b. Issued by the from Stock:</td>
                <td class="value"><?php echo htmlspecialchars($trip['gasoline_issued']); ?> Liters</td>
            </tr>
            <tr>
                <td class="label">c. Additional purchase during trip:</td>
                <td class="value"><?php echo htmlspecialchars($trip['gasoline_purchased']); ?> Liters</td>
            </tr>
            <tr>
                <td class="label">d. Used during the trip (to and from):</td>
                <td class="value">_____ Liters</td>
            </tr>
            <tr>
                <td class="label">e. Balance in tank at the end of the trip:</td>
                <td class="value">(estimate in ½, ⅓ and ¼ full)</td>
            </tr>
        </table>

        <div class="field-row indent" style="margin-top: 10px;">
            <span class="field-label">12.</span>
            <span>Gear oil issued: </span>
            <span class="field-value"><?php echo htmlspecialchars($trip['gear_oil']); ?> Liters</span>
        </div>

        <div class="field-row indent">
            <span class="field-label">13.</span>
            <span>Lubricating oil issued: </span>
            <span class="field-value"><?php echo htmlspecialchars($trip['oil_issued']); ?> Liters</span>
        </div>

        <div class="field-row indent">
            <span class="field-label">14.</span>
            <span>Greased issued: </span>
            <span class="field-value"><?php echo htmlspecialchars($trip['grease_issued']); ?></span>
        </div>

        <div class="field-row indent">
            <span class="field-label">15.</span>
            <span>Speed meter reader if any: </span>
        </div>

        <div class="field-row indent" style="margin-left: 60px;">
            <span>At the beginning of the trip: </span>
            <span class="field-value"><?php echo htmlspecialchars($trip['speedometer_start']); ?> miles/kms</span>
        </div>

        <div class="field-row indent" style="margin-left: 60px;">
            <span>At the end of the trip: </span>
            <span class="field-value"><?php echo htmlspecialchars($trip['speedometer_end']); ?> miles/kms</span>
        </div>

        <div class="field-row indent" style="margin-left: 60px;">
            <span>Distance traveled per no 5 above: </span>
            <span class="field-value"><?php echo htmlspecialchars($trip['distance_traveled']); ?> miles/kms</span>
        </div>

        <div class="field-row indent">
            <span class="field-label">16.</span>
            <span>Remarks: </span>
            <span class="field-value"><?php echo htmlspecialchars($trip['remarks']); ?></span>
        </div>
    </div>

    <!-- Certification -->
    <div class="certification">
        <p>I hereby certify to the correctness of the above statement of record of travel</p>
    </div>

    <!-- Driver Signature -->
    <div class="signature-block">
        <div class="signature-line"></div>
        <div style="text-transform: uppercase; font-weight: bold;"><?php echo htmlspecialchars($trip['driver_name']); ?></div>
        <div style="font-size: 10pt;">Driver</div>
    </div>

    <!-- Certification 2 -->
    <div class="certification" style="margin-top: 30px;">
        <p>I hereby certify that this area on Official Business as stated above</p>
        <p style="margin-top: 5px;">Name/s of Passenger/s</p>
    </div>

    <!-- Passenger Table -->
    <div class="passenger-table">
        <table>
            <tr>
                <td style="font-weight: bold;">NAME</td>
                <td style="font-weight: bold;">DESIGNATION</td>
                <td style="font-weight: bold;">SIGNATURE</td>
            </tr>
            <?php
            // Parse passenger names (comma-separated)
            $passenger_list = [];
            if (!empty($trip['passenger_names'])) {
                $passenger_list = array_map('trim', explode(',', $trip['passenger_names']));
            } elseif (!empty($trip['passenger_name'])) {
                // Fallback to old passenger_name field
                $passenger_list = array_map('trim', explode(',', $trip['passenger_name']));
            }
            
            // Display each passenger in a row
            if (!empty($passenger_list)) {
                foreach ($passenger_list as $passenger) {
                    if (!empty($passenger)) {
                        // Parse [Designation] prefix — e.g. "[Student] Juan Dela Cruz"
                        $designation = 'CAMPUS OFFICIALS';
                        $clean_name  = $passenger;
                        if (preg_match('/^\[([^\]]+)\]\s*(.+)$/', $passenger, $m)) {
                            $designation = strtoupper($m[1]);
                            $clean_name  = $m[2];
                        }
                        echo '<tr>';
                        echo '<td style="text-transform: uppercase; font-weight: bold;">' . htmlspecialchars($clean_name) . '</td>';
                        echo '<td style="text-transform: uppercase; font-weight: bold;">' . htmlspecialchars($designation) . '</td>';
                        echo '<td>&nbsp;</td>';
                        echo '</tr>';
                    }
                }
            }
            
            // Add empty rows to fill the table (minimum 3 rows total)
            $rows_to_add = max(0, 3 - count($passenger_list));
            for ($i = 0; $i < $rows_to_add; $i++) {
                echo '<tr>';
                echo '<td>&nbsp;</td>';
                echo '<td>&nbsp;</td>';
                echo '<td>&nbsp;</td>';
                echo '</tr>';
            }
            ?>
        </table>
    </div>
</div>

</body>
</html>
