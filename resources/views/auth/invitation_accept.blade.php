<!-- views/auth/invitation_accept.blade.php -->
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>招待の承諾</title>
  <style>
    body { font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans JP","Hiragino Kaku Gothic ProN","Yu Gothic",sans-serif; background:#f6f7f9; }
    .card { max-width: 720px; margin: 40px auto; background:#fff; border:1px solid #ddd; border-radius: 10px; padding: 18px; }
    .row { margin: 10px 0; }
    label { display:block; font-size: 13px; margin-bottom: 6px; }
    input { width:100%; padding:10px; border:1px solid #ccc; border-radius: 8px; }
    button { padding:10px 14px; border:0; border-radius: 8px; cursor:pointer; }
    .info { margin: 12px 0; padding: 12px; background:#fafafa; border:1px solid #e5e5e5; border-radius: 10px; }
    .err { margin: 12px 0; padding: 12px; background:#fff2f2; border:1px solid #f0b4b4; border-radius: 10px; }
    .muted { color:#666; font-size: 12px; }
  </style>
</head>
<body>
  <div class="card">
    <h2 style="margin:0 0 12px 0;">招待の承諾</h2>

    <div class="info">
      <div><strong>メール</strong>: {{ $invitation->email }}</div>
      <div><strong>権限</strong>: {{ $invitation->role }}</div>
      @if($invitation->expires_at)
        <div class="muted" style="margin-top:6px;">有効期限: {{ $invitation->expires_at->format('Y-m-d H:i') }}</div>
      @endif
    </div>

    @if ($errors->any())
      <div class="err">
        <ul style="margin:0; padding-left: 18px;">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('invitations.accept.store', ['token' => $invitation->token]) }}">
      @csrf

      <div class="row">
        <label>表示名（任意）</label>
        <input type="text" name="name" value="{{ old('name') }}">
      </div>

      <div class="row">
        <label>パスワード（8文字以上）</label>
        <input type="password" name="password" autocomplete="new-password">
      </div>

      <div class="row">
        <label>パスワード（確認）</label>
        <input type="password" name="password_confirmation" autocomplete="new-password">
      </div>

      <button type="submit">承諾してログイン</button>
    </form>
  </div>
</body>
</html>