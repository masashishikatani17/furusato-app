{{-- resources/views/emails/user_invitation.blade.php --}}
@php
  /** @var \App\Models\Invitation $invitation */
@endphp
ユーザー招待が届いています。

メールアドレス: {{ $invitation->email }}
権限: {{ $invitation->role }}

以下のURLから招待を承諾してください：
{{ $acceptUrl }}

@if($invitation->expires_at)
有効期限: {{ $invitation->expires_at->format('Y-m-d H:i') }}
@endif