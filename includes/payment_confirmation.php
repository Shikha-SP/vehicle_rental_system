<?php
$payment_vehicle_image_src = $payment_vehicle_image_src ?? 'cid:vehicle_image';
$payment_confirmation_method = $payment_confirmation_method ?? ($payment_label ?? ($payment_method ?? 'Card'));
return "
<html>
<head>
  <style>@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap');</style>
</head>
<body style=\"margin:0;padding:20px;background-color:#0a0a0a;font-family:'Montserrat',Arial,sans-serif;\">

<table width=\"600\" cellpadding=\"0\" cellspacing=\"0\" style=\"background-color:#111111;color:#ffffff;font-family:'Montserrat',Arial,sans-serif;margin:auto;border-radius:4px;overflow:hidden;\">

  <!-- HEADER -->
  <tr>
    <td style=\"padding:24px 40px 16px;background:#111111;\">
      <span style=\"font-size:18px;font-weight:700;color:#e53935;\">TD Rentals</span>
      <div style=\"height:2px;background:linear-gradient(to right,#e53935,#111111);margin-top:14px;\"></div>
    </td>
  </tr>

  <!-- HERO -->
  <tr>
    <td style=\"padding:40px 40px 32px;text-align:center;background:#111111;\">
      <table cellpadding=\"0\" cellspacing=\"0\" style=\"margin:0 auto 24px;\">
        <tr><td style=\"width:52px;height:52px;background:#e53935;border-radius:10px;text-align:center;vertical-align:middle;\">
          <span style=\"font-size:24px;color:#ffffff;\">&#10003;</span>
        </td></tr>
      </table>
      <h1 style=\"margin:0 0 16px;font-size:26px;font-weight:700;color:#ffffff;\">Payment Confirmed</h1>
      <p style=\"margin:0;font-size:14px;color:#aaaaaa;line-height:1.6;\">Hi {$first_name}, your payment for the <strong style=\"color:#ffffff;\">{$vehicle['model']}</strong> has been processed successfully.</p>
    </td>
  </tr>

  <!-- BOOKING CARD -->
  <tr>
    <td style=\"padding:0 24px 16px;\">
      <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"background:#1e1e1e;border-radius:10px;overflow:hidden;\">
        <tr>
          <td width=\"50%\" style=\"padding:20px 24px 12px;vertical-align:top;\">
            <p style=\"margin:0 0 6px;font-size:10px;font-weight:700;color:#888888;letter-spacing:1.5px;text-transform:uppercase;\">Booking ID</p>
            <p style=\"margin:0;font-size:18px;font-weight:700;color:#ffffff;\">TD-#{$booking_id}</p>
          </td>
          <td width=\"50%\" style=\"padding:20px 24px 12px;vertical-align:top;text-align:right;\">
            <p style=\"margin:0 0 6px;font-size:10px;font-weight:700;color:#888888;letter-spacing:1.5px;text-transform:uppercase;\">Duration</p>
            <p style=\"margin:0;font-size:18px;font-weight:700;color:#ffffff;\">{$days} Days</p>
          </td>
        </tr>
        <tr>
          <td colspan=\"2\" style=\"padding:0 16px 16px;\">
	            <img src=\"{$payment_vehicle_image_src}\" alt=\"{$vehicle['model']}\" width=\"100%\" style=\"display:block;height:200px;object-fit:cover;border-radius:8px;\" />
          </td>
        </tr>
        <tr>
          <td colspan=\"2\" style=\"padding:0 24px 20px;\">
	            <p style=\"margin:0 0 6px;font-size:10px;font-weight:700;color:#888888;letter-spacing:1.5px;text-transform:uppercase;\">Booking Date</p>
	            <p style=\"margin:0;font-size:15px;font-weight:600;color:#ffffff;\">{$pickup_date} &mdash; {$dropoff_date}</p>
	          </td>
	        </tr>
	        <tr>
	          <td colspan=\"2\" style=\"padding:0 24px 20px;\">
	            <p style=\"margin:0 0 6px;font-size:10px;font-weight:700;color:#888888;letter-spacing:1.5px;text-transform:uppercase;\">Payment Method</p>
	            <p style=\"margin:0;font-size:15px;font-weight:600;color:#ffffff;\">{$payment_confirmation_method}</p>
	          </td>
	        </tr>
	      </table>
    </td>
  </tr>

  <!-- TOTAL PAID -->
  <tr>
    <td style=\"padding:0 24px 32px;\">
      <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"background:#1e1e1e;border-radius:10px;\">
        <tr>
          <td style=\"padding:20px 24px;\">
            <p style=\"margin:0 0 8px;font-size:10px;font-weight:700;color:#888888;letter-spacing:1.5px;text-transform:uppercase;\">Total Paid</p>
            <p style=\"margin:0;font-size:22px;font-weight:700;color:#e53935;\">NPR {$totalprice}</p>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- NOTE -->
  <tr>
    <td style=\"padding:0 24px 32px;text-align:center;\">
      <p style=\"margin:0 0 6px;font-size:13px;color:#888888;\">Please find your invoice attached.</p>
      <p style=\"margin:0 0 6px;font-size:13px;color:#888888;\">Thank you for choosing TD Rentals &#128640;</p>
      <p style=\"margin:16px 0 4px;font-size:13px;color:#888888;\">Best Regards,</p>
      <p style=\"margin:0;font-size:13px;font-weight:700;color:#ffffff;\">TD Rentals Team</p>
    </td>
  </tr>

  <!-- FOOTER -->
  <tr>
    <td style=\"padding:20px 40px;border-top:1px solid #2a2a2a;text-align:center;background:#111111;\">
      <p style=\"margin:0 0 10px;font-size:15px;font-weight:700;color:#e53935;\">TD Rentals</p>
      <p style=\"margin:0 0 10px;font-size:11px;color:#555555;\">Support &nbsp;&middot;&nbsp; Privacy Policy &nbsp;&middot;&nbsp; Terms of Service</p>
      <p style=\"margin:0;font-size:11px;color:#444444;\">&copy; 2026 TD Rentals. All rights reserved.</p>
    </td>
  </tr>

</table>
</body>
</html>
";
?>
