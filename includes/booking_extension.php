<?php
return "
<html>
<head>
  <style>@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap');</style>
</head>
<body style=\"margin:0;padding:20px;background-color:#0a0a0a;font-family:'Montserrat',Arial,sans-serif;\">

<table width=\"600\" cellpadding=\"0\" cellspacing=\"0\" style=\"background-color:#111111;color:#ffffff;font-family:'Montserrat',Arial,sans-serif;margin:auto;overflow:hidden;\">

  <!-- HEADER -->
  <tr>
    <td style=\"padding:20px 40px;text-align:center;background:#111111;border-bottom:1px solid #2a2a2a;\">
      <span style=\"font-size:16px;font-weight:800;color:#e53935;letter-spacing:3px;\">TD RENTALS</span>
    </td>
  </tr>

  <!-- HERO -->
  <tr>
    <td style=\"padding:48px 40px 36px;text-align:center;background:#0d0d0d;\">
      <h1 style=\"margin:0 0 16px;font-size:26px;font-weight:800;color:#ffffff;line-height:1.3;\">Hi {$first_name}, your booking has been<br>extended!</h1>
      <p style=\"margin:0;font-size:14px;color:#888888;line-height:1.6;\">We've updated your reservation details. Safe travels!</p>
    </td>
  </tr>

  <!-- DETAILS CARD -->
  <tr>
    <td style=\"padding:24px 24px 16px;\">
      <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"background:#1a1a1a;border-radius:10px;border:1px solid #2a2a2a;border-top:2px solid #e53935;overflow:hidden;\">
        <tr>
          <td style=\"padding:20px 24px 16px;\">
            <p style=\"margin:0 0 10px;font-size:10px;font-weight:700;color:#888888;letter-spacing:1.5px;text-transform:uppercase;\">Vehicle</p>
            <table cellpadding=\"0\" cellspacing=\"0\">
              <tr>
                <td style=\"font-size:16px;font-weight:700;color:#ffffff;\">{$vehicle['model']}</td>
              </tr>
            </table>
          </td>
        </tr>
	        <tr><td style=\"padding:0 24px;\"><div style=\"height:1px;background:#2a2a2a;\"></div></td></tr>
	        <tr>
	          <td style=\"padding:20px 24px 0;\">
	            <p style=\"margin:0 0 10px;font-size:10px;font-weight:700;color:#888888;letter-spacing:1.5px;text-transform:uppercase;\">Payment Method</p>
	            <table cellpadding=\"0\" cellspacing=\"0\">
	              <tr>
	                <td style=\"padding-right:8px;font-size:16px;\">&#128179;</td>
	                <td style=\"font-size:15px;font-weight:600;color:#ffffff;\">" . htmlspecialchars($payment_method ?? 'Card', ENT_QUOTES, 'UTF-8') . "</td>
	              </tr>
	            </table>
	          </td>
	        </tr>
	        <tr>
	          <td style=\"padding:20px 24px;\">
            <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\">
              <tr>
                <td width=\"50%\" style=\"vertical-align:top;\">
                  <p style=\"margin:0 0 10px;font-size:10px;font-weight:700;color:#888888;letter-spacing:1.5px;text-transform:uppercase;\">New Drop-off Date</p>
                  <table cellpadding=\"0\" cellspacing=\"0\">
                    <tr>
                      <td style=\"padding-right:8px;font-size:16px;\">&#128197;</td>
                      <td style=\"font-size:15px;font-weight:600;color:#ffffff;\">{$dropoff_date}</td>
                    </tr>
                  </table>
                </td>
                <td width=\"50%\" style=\"vertical-align:top;\">
                  <p style=\"margin:0 0 10px;font-size:10px;font-weight:700;color:#888888;letter-spacing:1.5px;text-transform:uppercase;\">Additional Charge</p>
                  <table cellpadding=\"0\" cellspacing=\"0\">
                    <tr>
                      <td style=\"padding-right:8px;font-size:16px;\">&#128179;</td>
                      <td style=\"font-size:15px;font-weight:600;color:#ffffff;\">NPR " . number_format($extra_cost, 2) . "</td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- SUPPORT NOTE -->
  <tr>
    <td style=\"padding:0 24px 32px;\">
      <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:1px dashed #2a2a2a;border-radius:8px;\">
        <tr>
          <td style=\"padding:20px 24px;text-align:center;\">
            <p style=\"margin:0;font-size:13px;color:#888888;line-height:1.6;\">Need to modify again? Visit your dashboard or contact our 24/7 executive support.</p>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- FOOTER -->
  <tr>
    <td style=\"padding:32px 40px;border-top:1px solid #2a2a2a;text-align:center;background:#0d0d0d;\">
      <p style=\"margin:0 0 6px;font-size:11px;font-weight:700;color:#888888;letter-spacing:2px;text-transform:uppercase;\">TD RENTALS EXECUTIVE</p>
      <p style=\"margin:12px 0 4px;font-size:16px;font-weight:700;color:#ffffff;\">Thank you for choosing TD Rentals</p>
      <p style=\"margin:0 0 16px;font-size:20px;\">&#128640;</p>
      <p style=\"margin:0;font-size:11px;color:#444444;\">&copy; 2026 TD Rentals. All rights reserved.</p>
    </td>
  </tr>

</table>
</body>
</html>
";
?>
