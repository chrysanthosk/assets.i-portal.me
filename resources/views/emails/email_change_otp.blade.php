<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Email Change OTP</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.5;">
  <p>Hello {{ $fullName }},</p>

  <p>You requested to change your email address. Use the OTP below to confirm:</p>

  <p style="font-size: 22px; font-weight: bold; letter-spacing: 2px;">
    {{ $otp }}
  </p>

  <p>This OTP expires in 10 minutes.</p>

  <p>If you did not request this change, you can ignore this email.</p>
</body>
</html>
