<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Follow-up hari ini</title>
<style>
  body { margin: 0; padding: 0; background: #f5f5f5; font-family: Arial, sans-serif; color: #333; }
  .wrapper { max-width: 600px; margin: 32px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
  .header { background: #cc1c1c; padding: 24px 32px; }
  .header h1 { color: #fff; margin: 0; font-size: 18px; font-weight: 700; }
  .header p { color: #ffd9d9; margin: 6px 0 0; font-size: 13px; }
  .body { padding: 24px 32px; }
  .body > p { line-height: 1.6; margin: 0 0 16px; }
  .item { border: 1px solid #eee; border-radius: 6px; padding: 14px 16px; margin-bottom: 12px; }
  .item .title { font-weight: 700; margin: 0 0 4px; }
  .item .meta { font-size: 13px; color: #666; margin: 0 0 8px; }
  .item .overdue { color: #cc1c1c; font-weight: 700; }
  .item a { display: inline-block; font-size: 13px; color: #cc1c1c; text-decoration: none; font-weight: 700; }
  .footer { background: #f9f9f9; border-top: 1px solid #eee; padding: 18px 32px; text-align: center; font-size: 12px; color: #999; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>Follow-up untuk {{ $sales->name }}</h1>
    <p>{{ $reminders->count() }} tindak lanjut menunggu per {{ now()->timezone('Asia/Makassar')->format('d M Y') }}</p>
  </div>
  <div class="body">
    <p>Halo {{ $sales->name }}, berikut daftar follow-up yang jatuh tempo atau terlewat. Klik untuk membuka tour terkait.</p>

    @foreach ($reminders as $reminder)
      <div class="item">
        <p class="title">{{ $reminder->title }}</p>
        <p class="meta">
          @if ($reminder->remind_at->isPast() && ! $reminder->remind_at->isToday())
            <span class="overdue">Terlewat {{ $reminder->remind_at->format('d M Y') }}</span>
          @else
            Jatuh tempo hari ini
          @endif
          @if ($reminder->notes) &middot; {{ $reminder->notes }} @endif
        </p>
        @if ($reminder->tour_id)
          <a href="{{ url('/tours/' . $reminder->tour_id . '/edit') }}">Buka tour &rarr;</a>
        @endif
      </div>
    @endforeach
  </div>
  <div class="footer">
    <p>Email otomatis dari sistem ERP Welcome Manado. Reminder yang belum ditandai selesai akan tetap muncul esok hari.</p>
  </div>
</div>
</body>
</html>
