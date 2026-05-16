<?php
return <<<HTML
<html>
<head>
  <style>@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap');</style>
</head>
<body style="margin:0;padding:20px;background-color:#111111;font-family:'Montserrat',Arial,sans-serif;">

<table width="600" cellpadding="0" cellspacing="0" style="background-color:#1a1a1a;color:#ffffff;font-family:'Montserrat',Arial,sans-serif;margin:auto;border-radius:12px;overflow:hidden;">

  <tr>
    <td style="padding:28px 40px 20px;border-bottom:1px solid #2e2e2e;text-align:center;background:#141414;">
      <span style="font-size:22px;font-weight:800;color:#e53935;letter-spacing:3px;">TD RENTALS</span>
    </td>
  </tr>

  <tr>
    <td style="padding:36px 40px 24px;text-align:center;">
      <h1 style="margin:0 0 10px;font-size:28px;font-weight:700;color:#ffffff;">Hi {$first_name},</h1>
      <p style="margin:0;font-size:15px;color:#888888;">Your booking for <strong style="color:#ffffff;">{$vehicle["model"]}</strong> is confirmed.</p>
    </td>
  </tr>

  <tr>
    <td style="padding:0 24px 24px;">
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#222222;border-radius:12px;overflow:hidden;">
        <tr>
          <td style="padding:24px;vertical-align:top;">
            <p style="margin:0 0 6px;font-size:10px;font-weight:700;color:#e53935;letter-spacing:2px;text-transform:uppercase;">Vehicle Details</p>
            <h2 style="margin:0 0 18px;font-size:20px;font-weight:700;color:#ffffff;">{$vehicle["model"]}</h2>
            <p style="margin:0 0 8px;font-size:13px;color:#888888;">&#9881;&#65039; {$vehicle["transmission"]}</p>
            <p style="margin:0;font-size:13px;color:#888888;">&#9889; {$vehicle["fuel_type"]}</p>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <tr>
    <td style="padding:0 24px 24px;">
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#222222;border-radius:12px;">
        <tr>
          <td style="padding:24px;">
            <p style="margin:0 0 14px;font-size:10px;font-weight:700;color:#e53935;letter-spacing:2px;text-transform:uppercase;">Booking Period</p>
            <p style="margin:0 0 12px;font-size:15px;font-weight:600;color:#ffffff;">Booking Date</p>
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td style="background:#2e2e2e;border-radius:8px;padding:14px 20px;text-align:center;">
                  <span style="font-size:16px;font-weight:600;color:#ffffff;letter-spacing:1px;">{$pickup_date} &nbsp;&rarr;&nbsp; {$dropoff_date}</span>
                </td>
              </tr>
            </table>
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:16px;">
              <tr>
                <td style="padding:10px 0;border-top:1px solid #2e2e2e;">
                  <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                      <td style="font-size:14px;color:#888888;">Duration</td>
                      <td style="font-size:14px;color:#ffffff;text-align:right;font-weight:600;">{$days} Days</td>
                    </tr>
                  </table>
                </td>
              </tr>
              <tr>
                <td style="padding:10px 0;border-top:1px solid #2e2e2e;">
                  <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                      <td style="font-size:14px;color:#888888;">Status</td>
                      <td style="text-align:right;">
                        <span style="background:#1a2e1a;color:#4caf50;font-size:12px;font-weight:700;padding:4px 12px;border-radius:20px;letter-spacing:1px;">&#9679; CONFIRMED</span>
                      </td>
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

  <tr>
    <td style="padding:0 24px 24px;">
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#2a1414;border:1px solid rgba(229,57,53,0.3);border-radius:12px;">
        <tr>
          <td style="padding:24px;">
            <p style="margin:0 0 6px;font-size:10px;font-weight:700;color:#e53935;letter-spacing:2px;text-transform:uppercase;">Invoice</p>
            <p style="margin:0;font-size:14px;color:#b88a8a;">Your invoice is attached to this email. All-inclusive insurance and taxes applied to this booking.</p>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <tr>
    <td style="padding:0 40px 32px;text-align:center;">
      <p style="margin:0 0 6px;font-size:14px;color:#888888;">Thank you for choosing TD Rentals &#128640;</p>
      <p style="margin:0;font-size:13px;color:#555555;">Call us: +977 9706704349</p>
    </td>
  </tr>

  <tr>
    <td style="padding:20px 40px;border-top:1px solid #2e2e2e;text-align:center;background:#141414;">
      <p style="margin:0 0 10px;font-size:13px;font-weight:700;color:#ffffff;letter-spacing:2px;">TD RENTALS</p>
      <p style="margin:0;font-size:11px;color:#444444;">&copy; 2026 TD Rentals. All rights reserved.</p>
    </td>
  </tr>

</table>
</body>
</html>
HTML;
?>