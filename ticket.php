<?php
/**
 * user/ticket.php
 * Printable / saveable trip ticket for the user.
 *
 * Ownership check: the trip's requester_name must match a completed
 * trip_request belonging to this user, OR the user is an admin.
 */
session_start();
require_once('../config/connection.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

/** @var mysqli $conn */

$trip_id = intval($_GET['trip_id'] ?? 0);
if (!$trip_id) {
    header("Location: index.php");
    exit();
}

// Fetch the trip
$stmt = $conn->prepare("SELECT * FROM trips WHERE id = ?");
$stmt->bind_param("i", $trip_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Trip ticket not found.");
}

$trip = $result->fetch_assoc();

// ── Ownership check ──────────────────────────────────────────
// Admins can always view. Users must own a completed request
// whose destination matches this trip's place_visited.
if ($_SESSION['role'] != 1) {
    $own = $conn->prepare("
        SELECT id FROM trip_requests
        WHERE user_id   = ?
          AND status    = 'completed'
          AND destination = ?
        LIMIT 1
    ");
    $own->bind_param("is", $_SESSION['user_id'], $trip['place_visited']);
    $own->execute();
    if ($own->get_result()->num_rows === 0) {
        // Also try matching by requester_name stored on the trip
        $own2 = $conn->prepare("
            SELECT tr.id FROM trip_requests tr
            JOIN user_info ui ON ui.id = tr.user_id
            WHERE tr.user_id = ?
              AND tr.status  = 'completed'
              AND (tr.requester_name = ? OR ui.username = ?)
            LIMIT 1
        ");
        $rname = $trip['requester_name'] ?: $trip['passenger_name'];
        $own2->bind_param("iss", $_SESSION['user_id'], $rname, $rname);
        $own2->execute();
        if ($own2->get_result()->num_rows === 0) {
            die("You do not have permission to view this ticket.");
        }
    }
}

$current_date = date('F j, Y');

// Parse passengers
$passenger_list = [];
if (!empty($trip['passenger_names'])) {
    $passenger_list = array_values(array_filter(array_map('trim', explode(',', $trip['passenger_names']))));
} elseif (!empty($trip['passenger_name'])) {
    $passenger_list = array_values(array_filter(array_map('trim', explode(',', $trip['passenger_name']))));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trip Ticket #<?php echo $trip_id; ?> — VehiQuest</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #1e7e34 0%, #f39c12 100%);
            min-height: 100vh;
            padding: 30px 20px;
        }

        /* ── Toolbar ── */
        .toolbar {
            max-width: 820px;
            margin: 0 auto 20px;
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn-toolbar {
            padding: 11px 24px;
            border: none;
            border-radius: 25px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .btn-save {
            background: white;
            color: #1e7e34;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }

        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.2); }

        .btn-back {
            background: rgba(255,255,255,0.25);
            color: white;
        }

        .btn-back:hover { background: rgba(255,255,255,0.35); }

        .toolbar-hint {
            color: rgba(255,255,255,0.85);
            font-size: 13px;
            margin-left: auto;
        }

        /* ── Ticket card ── */
        .ticket {
            max-width: 820px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.25);
        }

        .ticket-header {
            background: linear-gradient(135deg, #1e7e34 0%, #f39c12 100%);
            padding: 24px 32px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .ticket-header img {
            width: 52px; height: 52px;
            object-fit: contain;
            background: white;
            border-radius: 8px;
            padding: 4px;
        }

        .ticket-header-text h1 { color: white; font-size: 22px; font-weight: 800; }
        .ticket-header-text p  { color: rgba(255,255,255,0.85); font-size: 13px; margin-top: 2px; }

        .ticket-id {
            margin-left: auto;
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }

        .ticket-body { padding: 32px; }

        .ticket-title    { font-size: 20px; font-weight: 800; color: #1e7e34; margin-bottom: 4px; }
        .ticket-subtitle { font-size: 13px; color: #718096; margin-bottom: 28px; }

        /* ── Sections ── */
        .section { margin-bottom: 26px; }

        .section-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #1e7e34;
            margin-bottom: 10px;
            padding-bottom: 6px;
            border-bottom: 2px solid #e8f5e9;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }

        .info-grid.single { grid-template-columns: 1fr; }

        .info-item {
            padding: 11px 16px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .info-item:nth-child(odd)  { background: #f9fafb; }
        .info-item:nth-child(even) { background: white; }

        /* Remove bottom border from last row (handles both 1 and 2 col grids) */
        .info-grid:not(.single) .info-item:nth-last-child(-n+2) { border-bottom: none; }
        .info-grid.single .info-item:last-child { border-bottom: none; }

        .info-key {
            font-size: 11px;
            font-weight: 600;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .info-val { font-size: 14px; font-weight: 600; color: #1a202c; }
        .info-val.upper { text-transform: uppercase; }

        /* ── Passenger table ── */
        .passenger-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            font-size: 13px;
        }

        .passenger-table th {
            background: linear-gradient(135deg, #1e7e34, #f39c12);
            color: white;
            padding: 10px 14px;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .passenger-table td {
            padding: 10px 14px;
            border-bottom: 1px solid #e2e8f0;
            color: #1a202c;
        }

        .passenger-table tr:last-child td { border-bottom: none; }
        .passenger-table tr:nth-child(even) td { background: #f9fafb; }

        /* ── Signatures ── */
        .sig-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 2px dashed #e2e8f0;
        }

        .sig-box { text-align: center; }

        .sig-line {
            border-top: 1px solid #333;
            margin: 44px 20px 8px;
        }

        .sig-name { font-weight: 700; font-size: 13px; text-transform: uppercase; }
        .sig-role  { font-size: 12px; color: #718096; }

        /* ── Footer ── */
        .ticket-footer {
            background: #f8f9fa;
            padding: 14px 32px;
            text-align: center;
            font-size: 12px;
            color: #718096;
            border-top: 1px solid #e2e8f0;
        }

        /* ── Print ── */
        @media print {
            @page { size: A4; margin: 12mm 10mm; }

            body { background: white !important; padding: 0 !important; }
            .toolbar { display: none !important; }

            .ticket {
                box-shadow: none !important;
                border-radius: 0 !important;
                max-width: 100% !important;
            }

            .ticket-header,
            .passenger-table th {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .info-item:nth-child(odd) {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

<!-- Toolbar -->
<div class="toolbar">
    <a href="<?php echo $_SESSION['role'] == 1 ? '../admin/requests.php' : 'index.php'; ?>"
       class="btn-toolbar btn-back">← Back</a>
    <button class="btn-toolbar btn-save" onclick="window.print()">
        💾 Save as PDF / Print
    </button>
    <span class="toolbar-hint">In the print dialog, choose "Save as PDF" to download</span>
</div>

<!-- Ticket -->
<div class="ticket">

    <!-- Header -->
    <div class="ticket-header">
        <img src="../assets/images/isu-logo.png" alt="ISU Logo">
        <div class="ticket-header-text">
            <h1>VehiQuest</h1>
            <p>Isabela State University — Ilagan Campus</p>
        </div>
        <div class="ticket-id">Ticket #<?php echo $trip_id; ?></div>
    </div>

    <!-- Body -->
    <div class="ticket-body">
        <div class="ticket-title">Driver's Trip Ticket</div>
        <div class="ticket-subtitle">
            Issued <?php echo $current_date; ?> &nbsp;·&nbsp; Official Travel Document
        </div>

        <!-- Trip Information -->
        <div class="section">
            <div class="section-label">Trip Information</div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-key">Destination</span>
                    <span class="info-val upper"><?php echo htmlspecialchars($trip['place_visited']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-key">Purpose</span>
                    <span class="info-val"><?php echo htmlspecialchars($trip['purpose']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-key">Departure</span>
                    <span class="info-val">
                        <?php echo $trip['departure_date']
                            ? date('F j, Y  g:i A', strtotime($trip['departure_date']))
                            : '—'; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-key">Arrival</span>
                    <span class="info-val">
                        <?php echo $trip['arrival_date']
                            ? date('F j, Y  g:i A', strtotime($trip['arrival_date']))
                            : '—'; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-key">Requester</span>
                    <span class="info-val upper">
                        <?php echo htmlspecialchars($trip['requester_name'] ?: $trip['passenger_name']); ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-key">Authorized By</span>
                    <span class="info-val upper"><?php echo htmlspecialchars($trip['authorized_by']); ?></span>
                </div>
            </div>
        </div>

        <!-- Vehicle & Driver -->
        <div class="section">
            <div class="section-label">Vehicle &amp; Driver</div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-key">Driver</span>
                    <span class="info-val upper"><?php echo htmlspecialchars($trip['driver_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-key">Vehicle</span>
                    <span class="info-val upper"><?php echo htmlspecialchars($trip['vehicle_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-key">Plate Number</span>
                    <span class="info-val upper"><?php echo htmlspecialchars($trip['plate_number']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-key">Distance Traveled</span>
                    <span class="info-val">
                        <?php echo $trip['distance_traveled'] > 0
                            ? number_format($trip['distance_traveled'], 1) . ' km'
                            : '—'; ?>
                    </span>
                </div>
                <?php if ($trip['speedometer_start'] > 0): ?>
                <div class="info-item">
                    <span class="info-key">Speedometer Start</span>
                    <span class="info-val"><?php echo number_format($trip['speedometer_start'], 1); ?> km</span>
                </div>
                <div class="info-item">
                    <span class="info-key">Speedometer End</span>
                    <span class="info-val"><?php echo number_format($trip['speedometer_end'], 1); ?> km</span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Fuel & Supplies -->
        <div class="section">
            <div class="section-label">Fuel &amp; Supplies</div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-key">Gasoline Issued</span>
                    <span class="info-val">
                        <?php echo $trip['gasoline_issued'] > 0
                            ? number_format($trip['gasoline_issued'], 2) . ' L'
                            : '—'; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-key">Additional Purchase</span>
                    <span class="info-val">
                        <?php echo $trip['gasoline_purchased'] > 0
                            ? number_format($trip['gasoline_purchased'], 2) . ' L'
                            : '—'; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-key">Lubricating Oil</span>
                    <span class="info-val">
                        <?php echo $trip['oil_issued'] > 0
                            ? number_format($trip['oil_issued'], 2) . ' L'
                            : '—'; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-key">Gear Oil</span>
                    <span class="info-val">
                        <?php echo $trip['gear_oil'] > 0
                            ? number_format($trip['gear_oil'], 2) . ' L'
                            : '—'; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-key">Grease</span>
                    <span class="info-val">
                        <?php echo !empty($trip['grease_issued']) ? htmlspecialchars($trip['grease_issued']) : '—'; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-key">Items Purchased</span>
                    <span class="info-val">
                        <?php echo !empty($trip['items_purchased']) ? htmlspecialchars($trip['items_purchased']) : '—'; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Passengers -->
        <div class="section">
            <div class="section-label">Passengers</div>
            <table class="passenger-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Name</th>
                        <th>Designation</th>
                        <th style="width:140px;">Signature</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $rows = max(count($passenger_list), 3);
                    for ($i = 0; $i < $rows; $i++):
                        $p = $passenger_list[$i] ?? '';
                        // Parse [Designation] prefix: "[Student] Juan Dela Cruz"
                        $designation_label = '';
                        $clean_name = $p;
                        if ($p && preg_match('/^\[([^\]]+)\]\s*(.+)$/', $p, $m)) {
                            $designation_label = $m[1];
                            $clean_name        = $m[2];
                        }
                    ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td style="text-transform:uppercase;font-weight:600;">
                            <?php echo $clean_name ? htmlspecialchars($clean_name) : '&nbsp;'; ?>
                        </td>
                        <td>
                            <?php if ($designation_label): ?>
                                <span style="display:inline-block;padding:3px 10px;border-radius:10px;
                                             font-size:11px;font-weight:700;text-transform:uppercase;
                                             background:#e8f5e9;color:#1e7e34;">
                                    <?php echo htmlspecialchars($designation_label); ?>
                                </span>
                            <?php elseif ($p): ?>
                                Campus Official
                            <?php else: ?>
                                &nbsp;
                            <?php endif; ?>
                        </td>
                        <td>&nbsp;</td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($trip['remarks'])): ?>
        <div class="section">
            <div class="section-label">Remarks</div>
            <div class="info-grid single">
                <div class="info-item">
                    <span class="info-val"><?php echo nl2br(htmlspecialchars($trip['remarks'])); ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Signatures -->
        <div class="sig-row">
            <div class="sig-box">
                <div class="sig-line"></div>
                <div class="sig-name"><?php echo htmlspecialchars($trip['driver_name']); ?></div>
                <div class="sig-role">Driver</div>
            </div>
            <div class="sig-box">
                <div class="sig-line"></div>
                <div class="sig-name"><?php echo htmlspecialchars($trip['authorized_by']); ?></div>
                <div class="sig-role">Director, AFS / Authorized Representative</div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="ticket-footer">
        Official trip ticket issued by VehiQuest — Isabela State University, Ilagan Campus
        &nbsp;·&nbsp; Generated <?php echo $current_date; ?>
    </div>

</div>
</body>
</html>
