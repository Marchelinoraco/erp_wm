<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>{{ $subject }}</title>
<style>
  body { margin: 0; padding: 0; background: #f5f5f5; font-family: Arial, sans-serif; color: #333; }
  .wrapper { max-width: 600px; margin: 32px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
  .header { background: #cc1c1c; padding: 28px 32px; text-align: center; }
  .header img { height: 52px; }
  .header h1 { color: #fff; margin: 12px 0 0; font-size: 18px; font-weight: 700; letter-spacing: .5px; }
  .body { padding: 32px; }
  .body p { line-height: 1.7; margin: 0 0 16px; }
  .body pre { white-space: pre-wrap; font-family: Arial, sans-serif; margin: 0; }
  .footer { background: #f9f9f9; border-top: 1px solid #eee; padding: 20px 32px; text-align: center; font-size: 12px; color: #999; }
  .footer a { color: #cc1c1c; text-decoration: none; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <img src="{{ asset('logo.png') }}" alt="Welcome Manado" />
    <h1>Welcome Manado Tour &amp; Travel</h1>
  </div>
  <div class="body">
    <pre>{{ $body }}</pre>
  </div>
  <div class="footer">
    <p>
      <strong>Welcome Manado Tour &amp; Travel</strong><br>
      Manado, Sulawesi Utara, Indonesia<br>
      <a href="https://welcomemanado.com">welcomemanado.com</a>
    </p>
    <p style="margin-top:8px;font-size:11px;">Email ini dikirim oleh sistem ERP Welcome Manado.</p>
  </div>
</div>
</body>
</html>
