<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TD Rentals - Operations Control Dashboard</title>
    <script src="https://kit.fontawesome.com/ac1574deb1.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="/vehicle_rental_system/assets/css/style.css">
</head>

<body>
    <div class="operations-control">
        <!-- nav bar -->
        <?php require '../../includes/header.php'; ?>

        <!-- Top Header -->
        <header class="status-header">
            <h1 class="status-title">OPERATIONS CONTROL</h1>
            <div class="status-message">STATUS: ALL SYSTEMS FUNCTIONAL</div>
        </header>

        <!-- Metrics Row -->
        <div class="metrics-row">
            <div class="metric-card">
                <div class=" label">MAINTENANCE</div>
                <div class="metric-value">08</div>
                <div class="progress-bar">
                    <div class="progress-bar-fill blue" style="width:20%"></div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-label">AVAILABLE</div>
                <div class="metric-value">34</div>
                <div class="progress-bar">
                    <div class="progress-bar-fill orange" style="width:55%"></div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-label">DAILY REVENUE</div>
                <div class="metric-value">$42,890</div>
                <div class="metric-trend">+12.4%</div>
            </div>
            <!-- Verification Queue -->
            <section class="verification-queue">
                <h3 class="section-title">VERIFICATION QUEUE</h3>
                <div class="queue-list">
                    <div class="queue-item">
                        <span class="queue-initials">JD</span>
                        <span class="queue-name">Julian Draxler</span>
                        <span class="queue-membership">PRO MEMBERSHIP</span>
                        <span class="queue-action">REVIEW</span>
                    </div>
                    <div class="queue-item">
                        <span class="queue-initials">SM</span>
                        <span class="queue-name">Sonia Miller</span>
                        <span class="queue-membership">STANDARD</span>
                        <span class="queue-action">REVIEW</span>
                    </div>
                </div>
                <div class="queue-footer">
                    <a href="#" class="view-all-link">VIEW ALL (12 PENDING)</a>
                </div>
            </section>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Recent Bookings -->
            <section class="recent-bookings">
                <h3 class="section-title">RECENT GLOBAL BOOKINGS</h3>
                <div class="header-actions">
                    <button class="btn btn-filter">FILTER BY STATUS</button>
                    <button class="btn btn-export">EXPORT CSV</button>
                </div>
                <table class="bookings-table">
                    <thead>
                        <tr>
                            <th>Vehicle</th>
                            <th>Client</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="table-body">
                        <tr>
                            <td>
                                <div class="vehicle-name">Porsche 911 GT3</div>
                                <div class="vehicle-vin">VIN: …0942-RS</div>
                            </td>
                            <td>
                                <div class="client-name">Alexander Thorne</div>
                                <div class="client-tier">Verified Elite Member</div>
                            </td>
                            <td><span class="duration">72 Hours</span></td>
                            <td><span class="badge badge-active">Active</span></td>
                            <td>
                                <div class="revenue">$4,200.00</div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="vehicle-name">Lamborghini Huracán</div>
                                <div class="vehicle-vin">VIN: …8821-U</div>
                            </td>
                            <td>
                                <div class="client-name">Elena Rodriguez</div>
                                <div class="client-tier">Corporate Account</div>
                            </td>
                            <td><span class="duration">24 Hours</span></td>
                            <td><span class="badge badge-upcoming">Upcoming</span></td>
                            <td>
                                <div class="revenue">$2,850.00</div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="vehicle-name">Mercedes-AMG G63</div>
                                <div class="vehicle-vin">VIN: …1120-G</div>
                            </td>
                            <td>
                                <div class="client-name">Hiroshi Tanaka</div>
                                <div class="client-tier">Standard Member</div>
                            </td>
                            <td><span class="duration">48 Hours</span></td>
                            <td><span class="badge badge-completed">Completed</span></td>
                            <td>
                                <div class="revenue">$3,100.00</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>

        <!-- Footer -->
        <footer class="app-footer">
            <div class="footer-brand">
                <span class="company-name">TD RENTALS</span>
                <span class="copyright">© 2024 TD RENTALS, ENGINEERED FOR PERFORMANCE.</span>
            </div>
            <div class="footer-links">
                <a href="#" class="footer-link">PRIVACY POLICY</a>
                <a href="#" class="footer-link">TERMS OF SERVICE</a>
                <a href="#" class="footer-link">FLEET GUIDE</a>
            </div>
        </footer>
    </div>
</body>

</html>