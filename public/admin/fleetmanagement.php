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
            <h1 class="status-title">FLEET MANAGEMENT</h1>
            <div class="totals">
                <p>42 Total Vehicles</p>
                <p>12 In Maintenance</p>
                <p>30 Active</p>
            </div>

        </header>

        <div>
            <div class="vehicle-card">
                <h3 class="vehicle-status">AVAILABLE</h3>
                <img src="/vehicle_rental_system/assets/images/toyotacar.jpg" alt="Porsche 911 GT3" width="300">
                <p class="short-detail">Luxury Performance</p>
                <h2 class="Model-detail">Porsche 911 GT3</h2>

                <p><strong>License Plate:</strong> TD-911-GT</p>
                <p><strong>Location:</strong> Miami South Central</p>

                <p class="engine,status">0-60: 3.2s</p>
                <p class="engine,status">Engine: 4.0L</p>
                <p class="engine,status">Status: Pristine</p>

                <button class="edit-specs">Edit Specs</button>
                <button class="service-req">Service Required</button>
            </div>
        </div>
    </div>
</body>

</html>