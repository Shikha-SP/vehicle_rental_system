<?php
return "
        <html>
        <body style=\"font-family: Montserrat, Arial, sans-serif; max-width: 600px; margin: auto; text-align: center; background-color: #1A1A1A; color: #FFF;\">
            <h1 style=\"color: #C0392B\">TD Rentals</h1>
            <div>
            <h2 style=\"font-size: 22px;\">Your Payment has been confirmed. </h2>
            <hr>
            <p style=\"font-size: 14px;\">Booking ID: <strong>{$booking_id}</strong></p>
            <p style=\"font-size: 14px;\">Vehicle: <strong>{$vehicle['model']}</strong></p>
            <hr>
            <p style=\"font-size: 16px;\">Booking Date</p>
            <h2 style=\"font-size: 25px;\">{$pickup_date} - {$dropoff_date}</h2>
            <hr>
            <p style=\"font-size: 16px;\">Total Paid: <strong>NPR {$totalprice}</strong></p>
            <hr>
            <p>Please find your invoice attached.</p>
            <p>Thank you for choosing TD Rentals 🚀</p>
            <br>
            <p>Best Regards,</p>
            <p><strong>TD Rentals Team</strong></p>
            </div>
        </body>
        </html>    
";
?>