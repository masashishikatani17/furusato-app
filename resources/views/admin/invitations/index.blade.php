@extends('layouts.min')

@section('title', '招待一覧')

@section('content')
<div class="container" style="max-width: 1100px;">
  <div class="d-flex justify-content-between align-items-center py-3">
    <hb class="mb-0">▶ 顧問先招待一覧</hb>
    <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-secondary">ユーザー管理へ</a>
  </div>

  @if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif

  <form class="row g-2 mb-3" method="GET" action="{{ route('admin.invitations.index') }}">
    <div class="col-md-3">
      <label class="form-label small text-muted mb-1">状態</label>
      <select class="form-select form-select-sm" name="status">
        @php
          $opts = [
            'pending' => '招待中（有効）',
            'accepted' => '承諾済み',
            'expired' => '期限切れ',
            'cancelled' => '取消',
            'revoked' => '失効',
          ];
        @endphp
        @foreach ($opts as $k => $v)
          <option value="{{ $k }}" @selected($status===$k)>{{ $v }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-5">
      <label class="form-label small text-muted mb-1">検索（メール/顧問先名）</label>
      <input class="form-control form-control-sm" type="text" name="q" value="{{ $kw }}" placeholder="example@ / 顧問先名">
    </div>
    <div class="col-md-2 d-flex align-items-end gap-2">
      <button class="btn btn-sm btn-primary" type="submit">絞り込み</button>
      <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.invitations.index') }}">クリア</a>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-sm table-bordered align-middle">
      <thead class="table-light">
        <tr>
          <th style="width: 160px;">作成日時</th>
          <th style="width: 260px;">メール</th>
          <th style="width: 90px;">役割</th>
          <th style="width: 220px;">顧問先</th>
          <th style="width: 160px;">部署</th>
          <th style="width: 160px;">期限</th>
          <th style="width: 220px;">操作</th>
        </tr>
      </thead>
      <tbody>
      @forelse ($invitations as $inv)
        @php
          $groupName = $inv->group_id && isset($groupMap[$inv->group_id]) ? (string)$groupMap[$inv->group_id]->name : '—';
          $guestLabel = '—';
          if ($inv->guest_id) {
            $guestLabel = isset($guestMap[$inv->guest_id]) ? (string)$guestMap[$inv->guest_id]->name : ('guest#'.$inv->guest_id);
          } elseif ($inv->guest_name) {
            $guestLabel = (string)$inv->guest_name . '（新規）';
          }
        @endphp
        <tr>
          <td>{{ optional($inv->created_at)->format('Y-m-d H:i') }}</td>
          <td>{{ $inv->email }}</td>
          <td class="text-uppercase">{{ $inv->role }}</td>
          <td>{{ $guestLabel }}</td>
          <td>{{ $groupName }}</td>
          <td>
            @if ($inv->expires_at)
              {{ $inv->expires_at->format('Y-m-d H:i') }}
            @else
              —
            @endif
          </td>
          <td>
            <div class="d-flex flex-wrap gap-2">
              @if ($status === 'pending')
                <form method="POST" action="{{ route('admin.invitations.resend', ['invitation'=>$inv->id]) }}" onsubmit="return confirm('この招待を再送しますか？（旧招待は失効になります）');">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-outline-primary">再送</button>
                </form>
                <form method="POST" action="{{ route('admin.invitations.cancel', ['invitation'=>$inv->id]) }}" onsubmit="return confirm('この招待を取消しますか？');">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-outline-secondary">取消</button>
                </form>
                <form method="POST" action="{{ route('admin.invitations.revoke', ['invitation'=>$inv->id]) }}" onsubmit="return confirm('この招待を失効しますか？');">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-outline-danger">失効</button>
                </form>
              @else
                <span class="text-muted small">—</span>
              @endif
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="text-muted py-3">データがありません。</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>

  {{ $invitations->links() }}
</div>
@endsection
