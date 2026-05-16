<?php
return "<html>
                    <body style=\"font-family: Montserrat, Arial, sans-serif; max-width: 600px; margin: auto; background-color: #1A1A1A; color: #FFF; text-align: center;\">
                        <h1 style=\"color: #C0392B\">TD Rentals</h1>
                        <div>
                            <h1 style=\"font-size: 22px\">Hi {$first_name},</h1>
                            <p style=\"font-size: 14px\">Your booking for <strong>{$vehicle['model']}</strong> is confirmed.</p>
                            <h2 style=\"font-size: 19px\">{$vehicle['model']}</h2>
                            <hr>
                            <p style=\"font-size: 16px\">Booking Date</p>
                            <h1 style=\"font-size: 25px\">{$pickup_date} - {$dropoff_date}</h1>
                            <hr>
                            <p>Please find your invoice attached.</p>
                            <p>Thank you for choosing TD Rentals 🚀</p>
                            <p>Call us: +9779706704349</p>
                        </div>
                    </body>
                    </html>";
?>