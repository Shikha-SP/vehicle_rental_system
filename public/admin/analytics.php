<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/vehicle_rental_system/assets/css/style.css">
    <title>Document</title>
</head>

<body>
    <div class="operations-control">
        <nav class="dashboard-nav">
            <ul class="nav-list">
                <li class="nav-item">DASHBOARD</li>
                <li class="nav-item">RESERVATIONS</li>
                <li class="nav-item">VEHICLES</li>
                <li class="nav-item">CUSTOMERS</li>
                <li class="nav-item">ANALYTICS</li>
                <li class="nav-item">SETTINGS</li>
            </ul>
            <button class="add-vehicle-btn">+ ADD VEHICLE</button>
        </nav>

        <!-- Top Header -->
        <header class="status-header">
            <h1 class="status-title">CUSTOMER INSIGHTS</h1>
            <h2>REGISTERED DRIVERS & BEHAVIORAL DATA</h2>
        </header>

        <div class="avg-info">
            <div class="avg-info-item">
                <p>Avg ltv</p>
                <p>$14,280</p>
                <p>+12.4% from last quarter</p>
            </div>
            <div class="avg-info-item">
                <p>Rental Freq</p>
                <p>3.8x</p>
                <p>Annual avg per user</p>
            </div>
            <div class="avg-info-item">
                <p>Approval Rate</p>
                <p>92%</p>
                <p>Verified high-net users</p>
            </div>
            <div class="avg-info-item">
                <p>Total Active</p>
                <p>1,402</p>
                <p>Elite membership tier</p>
            </div>
        </div>


        <div class="Customer-direcotry">
            <h2>CUSTOMER DIRECTORY</h2>
            <table>
                <thead>
                    <tr>
                        <th>CUSTOMER DETAILS</th>
                        <th>STATUS</th>
                        <th>FREQUENCY</th>
                        <th>LIFETIME VALUE</th>
                        <th>ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            Alessandro Rossi<br>
                            <small>alessandro.r@milan.it</small>
                        </td>
                        <td class="status-verified">VERIFIED</td>
                        <td>12 Rentals</td>
                        <td>$42,500.00</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>
                            Elena Vancamp<br>
                            <small>evancamp@tech.co</small>
                        </td>
                        <td class="status-pending">PENDING</td>
                        <td>1 Rental</td>
                        <td>$2,100.00</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>
                            Markus Thron<br>
                            <small>m.thron@estate.de</small>
                        </td>
                        <td class="status-verified">VERIFIED</td>
                        <td>8 Rentals</td>
                        <td>$18,420.00</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>
                            Jordan S.<br>
                            <small>j.s@creative.com</small>
                        </td>
                        <td class="status-rejected">REJECTED</td>
                        <td>0 Rentals</td>
                        <td>$0.00</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>