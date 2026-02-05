@extends('layouts.min')

@section('title', '招待一覧')

@section('content')
<div class="container px-4 py-4" style="width:1150px; background-color:#E8EFF0;">
  <div class="d-flex justify-content-between gap-2">
     <div><hb>▶ 顧問先招待一覧</hb>
     </div>
     <div class="d-flex">
      <a href="{{ route('admin.users.index') }}" class="btn-base-blue">ユーザー管理へ</a>
     </div>
  </div>

  @if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif

  <form class="row mb-3" method="GET" action="{{ route('admin.invitations.index') }}">
    <div class="col-md-3">
      <label class="form-label small mb-1"><h12>状態</h12></label>
      <select class="form-select form-select-sm mt-2" name="status">
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
      <label class="form-label small"><h12>検索（メール/顧問先名）</h12></label>
      <input class="form-control form-control-sm text-start" type="text" name="q" value="{{ $kw }}" placeholder="example@ / 顧問先名">
    </div>
    <div class="col-md-4 d-flex text-end mt-5">
      <button class="btn-base-green me-2" type="submit" style="height: 22px;">絞り込み</button>
      <a class="btn-base-red" style="height: 22px;" href="{{ route('admin.invitations.index') }}">クリア</a>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-base table-bordered align-middle" style="width: 1090px;">
      <thead class="table-light">
        <tr style="height: 32px;">
          <th style="width: 130px;">作成日時</th>
          <th style="width: 260px;">メール</th>
          <th style="width: 80px;">役 割</th>
          <th style="width: 130px;">顧問先</th>
          <th style="width: 180px;">部 署</th>
          <th style="width: 130px;">期 限</th>
          <th style="width: 180px;">操 作</th>
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
                  <button type="submit" class="btn btn-sm btn-outline-primary" style="height: 22px; line-height:22px; padding-top:0; padding-bottom:0;">再 送</button>
                </form>
                <form method="POST" action="{{ route('admin.invitations.cancel', ['invitation'=>$inv->id]) }}" onsubmit="return confirm('この招待を取消しますか？');">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-outline-secondary" style="height: 22px; line-height:22px; padding-top:0; padding-bottom:0;">取 消</button>
                </form>
                <form method="POST" action="{{ route('admin.invitations.revoke', ['invitation'=>$inv->id]) }}" onsubmit="return confirm('この招待を失効しますか？');">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-outline-danger" style="height: 22px; line-height:22px; padding-top:0; padding-bottom:0;">失 効</button>
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
