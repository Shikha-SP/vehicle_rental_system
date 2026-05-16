<?php
return "<html>
            <body style=\"font-family: Montserrat, Arial, sans-serif; max-width: 600px; margin: auto; background-color: #1A1A1A; color: #FFF; text-align: center;\">
                <h1 style=\"color: #C0392B\">TD Rentals</h1>
                <h2>Hi {$first_name}, your booking has been extended!</h2>
                <hr>
                <p>Vehicle: <strong>{$vehicle['model']}</strong></p>
                <p>New drop-off date: <strong>{$dropoff_date}</strong></p>
                <p>Additional charge: <strong>NPR " . number_format($extra_cost, 2) . "</strong></p>
                <hr>
                <p>Thank you for choosing TD Rentals 🚀</p>
            </body>
            </html>";
?>