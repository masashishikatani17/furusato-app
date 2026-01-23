@extends('layouts.min')

@section('title', '操作履歴')

@section('content')
<div class="container" style="max-width: 1100px;">
  <div class="d-flex justify-content-between align-items-center py-3">
    <hb class="mb-0">▶ 操作履歴</hb>
    <a href="{{ route('admin.settings') }}" class="btn btn-sm btn-secondary">設定TOPへ</a>
  </div>

  <form class="row g-2 mb-3" method="GET" action="{{ route('admin.audit_logs.index') }}">
    {{-- 操作種別 --}}
    <div class="col-md-2">
      <label class="form-label small text-muted mb-1">操作種別</label>
      <select class="form-select form-select-sm" name="action">
        @foreach($actionOptions as $val => $label)
          <option value="{{ $val }}" @selected(request('action','')===(string)$val)>{{ $label }}</option>
        @endforeach
      </select>
    </div>

    {{-- 操作ユーザー --}}
    <div class="col-md-3">
      <label class="form-label small text-muted mb-1">操作ユーザー</label>
      <select class="form-select form-select-sm" name="actor_user_id">
        <option value="">（すべて）</option>
        @foreach($usersForFilter as $u)
          <option value="{{ $u->id }}" @selected((string)request('actor_user_id','')===(string)$u->id)>
            {{ $u->name }} ({{ $u->email }})
          </option>
        @endforeach
      </select>
    </div>

    {{-- 対象：顧客 --}}
    <div class="col-md-3">
      <label class="form-label small text-muted mb-1">対象（顧客）</label>
      <select class="form-select form-select-sm" name="guest_id">
        <option value="">（すべて）</option>
        @foreach($guestsForFilter as $g)
          <option value="{{ $g->id }}" @selected((string)request('guest_id','')===(string)$g->id)>
            {{ $g->name }}
          </option>
        @endforeach
      </select>
    </div>

    {{-- 対象：年度 --}}
    <div class="col-md-2">
      <label class="form-label small text-muted mb-1">対象（年度）</label>
      <select class="form-select form-select-sm" name="year">
        <option value="">（すべて）</option>
        @foreach($yearsForFilter as $y)
          <option value="{{ $y }}" @selected((string)request('year','')===(string)$y)>{{ $y }}年</option>
        @endforeach
      </select>
    </div>

    {{-- 期間 --}}
    <div class="col-md-2">
      <label class="form-label small text-muted mb-1">期間（開始日）</label>
      <input class="form-control form-control-sm" type="date" name="date_from" value="{{ request('date_from') }}">
    </div>
    <div class="col-md-2">
      <label class="form-label small text-muted mb-1">期間（終了日）</label>
      <input class="form-control form-control-sm" type="date" name="date_to" value="{{ request('date_to') }}">
    </div>

    <div class="col-md-2 d-flex gap-2 align-items-end">
      <button class="btn btn-sm btn-primary" type="submit">絞り込み</button>
      <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.audit_logs.index') }}">クリア</a>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-sm table-bordered align-middle">
      <thead class="table-light">
        <tr>
          <th style="width: 160px;">日時</th>
          <th style="width: 260px;">操作ユーザー</th>
          <th style="width: 220px;">内容</th>
          <th style="width: 240px;">対象</th>
          <th>メモ</th>
        </tr>
      </thead>
      <tbody>
      @forelse($logs as $log)
        @php
          $a = $log->actor_user_id ? ($actorMap[$log->actor_user_id] ?? null) : null;
          $meta = is_array($log->meta) ? $log->meta : [];

          // --- action → 日本語ラベル ---
          $actionLabelMap = [
            'data.created' => '新規作成',
            'data.copied' => 'コピー作成',
            'data.updated' => '情報更新',
            'data.year_moved' => '年度変更',
            'data.overwritten' => '年度変更（上書き）',
            'data.deleted' => '削除',
            'data.year_select.existing' => '年度選択（既存へ移動）',
          ];
          $actionLabel = $actionLabelMap[$log->action] ?? $log->action;

          // --- 対象（お客様名＋年度） ---
          $guestId = null;
          foreach (['guest_id','to_guest_id','from_guest_id'] as $k) {
            if (isset($meta[$k]) && is_numeric($meta[$k])) { $guestId = (int)$meta[$k]; break; }
          }
          $guestName = $guestId && isset($guestMap[$guestId]) ? (string)$guestMap[$guestId]->name : null;
          $targetGuestText = $guestName ? $guestName : ($guestId ? ('guest#'.$guestId) : '—');

          $yearText = '—';
          if (isset($meta['kihu_year']) && is_numeric($meta['kihu_year'])) {
            $yearText = ((int)$meta['kihu_year']).'年';
          } elseif (isset($meta['to_year']) && is_numeric($meta['to_year'])) {
            $yearText = ((int)$meta['to_year']).'年';
          }

          // --- メモ（分かりやすい1行） ---
          $note = '';
          if ($log->action === 'data.overwritten') {
            $fy = isset($meta['from_year']) ? (int)$meta['from_year'] : null;
            $ty = isset($meta['to_year']) ? (int)$meta['to_year'] : null;
            $note = ($fy && $ty) ? "{$fy}年→{$ty}年に上書き" : '年度データを上書き';
          } elseif ($log->action === 'data.year_moved') {
            $fy = isset($meta['from_year']) ? (int)$meta['from_year'] : null;
            $ty = isset($meta['to_year']) ? (int)$meta['to_year'] : null;
            $note = ($fy && $ty) ? "{$fy}年→{$ty}年へ変更" : '年度を変更';
          } elseif ($log->action === 'data.copied') {
            $ys = isset($meta['years']) && is_array($meta['years']) ? $meta['years'] : [];
            $ys = array_values(array_filter(array_map('intval', $ys), fn($v)=>$v>0));
            if (!empty($ys)) $note = '年度：'.implode('年, ', $ys).'年 を作成';
          } elseif ($log->action === 'data.created') {
            if (isset($meta['kihu_year']) && is_numeric($meta['kihu_year'])) $note = ((int)$meta['kihu_year']).'年を作成';
          } elseif ($log->action === 'data.deleted') {
            if (isset($meta['kihu_year']) && is_numeric($meta['kihu_year'])) $note = ((int)$meta['kihu_year']).'年を削除';
          } elseif ($log->action === 'data.updated') {
            $note = '提案書日/共有設定などを更新';
          }
        @endphp
        <tr>
          <td>{{ optional($log->created_at)->format('Y-m-d H:i:s') }}</td>
          <td>
            @if($a)
              {{ $a->name }} ({{ $a->email }})
            @else
              (unknown)
            @endif
          </td>
          <td>
            <a href="{{ route('admin.audit_logs.show', ['id'=>$log->id]) }}">{{ $actionLabel }}</a>
          </td>
          <td>
            {{ $targetGuestText }}
            <span class="text-muted">/</span>
            {{ $yearText }}
          </td>
          <td class="text-muted">{{ $note ?: '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="4" class="text-muted py-3">ログがありません。</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>

  {{ $logs->links() }}
</div>
@endsection
