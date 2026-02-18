@extends('layouts.min')

@section('title', '招待一覧')

@section('content')
<div class="container px-4 py-3" style="width:1000px; background-color:#E8EFF0;">
  <div class="d-flex justify-content-between gap-2">
     <div><hb>▶ 顧問先招待一覧</hb>
     </div>
     <div class="d-flex">
      <a href="{{ route('admin.users.index') }}" class="btn-base-blue">ユーザー管理へ戻る</a>
     </div>
  </div>

  @if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif
  <form class="mb-3" method="GET" action="{{ route('admin.invitations.index') }}" style="width:900px;">
    <table class="w-100" style="table-layout: fixed; border-collapse: separate; border-spacing: 12px 4px;">
      <colgroup>
        <col style="width: 240px;">
        <col style="width: auto;">
        <col style="width: 200px;">
      </colgroup>
      <tbody>
        <tr>
          <td class="align-bottom">
            <label class="form-label small mb-1"><h12>状態</h12></label>
          </td>
          <td class="align-bottom">
            <label class="form-label small mb-1"><h12>検索（メール/顧問先名）</h12></label>
          </td>
          <td class="align-bottom"></td>
        </tr>
        <tr>
          <td>
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
          </td>
          <td>
            <input class="form-control kana20" type="text" name="q" value="{{ $kw }}" placeholder="example@ / 顧問先名">
          </td>
          <td class="text-end">
            <div class="d-inline-flex gap-2 align-items-center">
              <button class="btn-base-green" type="submit" style="height:22px; white-space:nowrap;">絞り込み</button>
              <a class="btn-base-red" href="{{ route('admin.invitations.index') }}"
                 style="height:22px; white-space:nowrap;">クリア</a>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </form>

  <div class="table-responsive">
    <table class="table table-base table-bordered align-middle" style="width: 920px;">
      <thead class="table-light">
        <tr style="height: 32px;">
          <th style="width: 60px;">作成日時</th>
          <th style="width: 260px;">メール</th>
          <th style="width: 80px;">役 割</th>
          <th style="width: 300px;">顧問先</th>
          <th style="width: 160px;">部 署</th>
          <th style="width: 60px;">期 限</th>
          <th style="width: 100px;">操 作</th>
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
                  <button type="submit" class="btn btn-base-blue" style="height: 22px; line-height:22px; padding-top:0; padding-bottom:0;">再 送</button>
                </form>
                <form method="POST" action="{{ route('admin.invitations.cancel', ['invitation'=>$inv->id]) }}" onsubmit="return confirm('この招待を取消しますか？');">
                  @csrf
                  <button type="submit" class="btn btn-base-red" style="height: 22px; line-height:22px; padding-top:0; padding-bottom:0;">取 消</button>
                </form>
                <form method="POST" action="{{ route('admin.invitations.revoke', ['invitation'=>$inv->id]) }}" onsubmit="return confirm('この招待を失効しますか？');">
                  @csrf
                  <button type="submit" class="btn btn-base-red" style="height: 22px; line-height:22px; padding-top:0; padding-bottom:0;">失 効</button>
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
