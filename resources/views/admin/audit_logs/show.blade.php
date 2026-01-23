@extends('layouts.min')

@section('title', '監査ログ詳細')

@section('content')
<div class="container" style="max-width: 900px;">
  <div class="d-flex justify-content-between align-items-center py-3">
    <hb class="mb-0">▶ 操作履歴 詳細</hb>
    <a href="{{ route('admin.audit_logs.index') }}" class="btn btn-sm btn-secondary">一覧へ戻る</a>
  </div>

  @php
    $meta = is_array($log->meta) ? $log->meta : [];
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

    $guestName = $guest?->name ?? null;
    $yearText = null;
    if (isset($meta['kihu_year']) && is_numeric($meta['kihu_year'])) $yearText = ((int)$meta['kihu_year']).'年';
    if (!$yearText && isset($meta['to_year']) && is_numeric($meta['to_year'])) $yearText = ((int)$meta['to_year']).'年';

    $desc = '';
    if ($log->action === 'data.overwritten') {
      $fy = isset($meta['from_year']) ? (int)$meta['from_year'] : null;
      $ty = isset($meta['to_year']) ? (int)$meta['to_year'] : null;
      $desc = ($fy && $ty)
        ? "年度データを上書きしました（{$fy}年 → {$ty}年）。"
        : "年度データを上書きしました。";
    } elseif ($log->action === 'data.year_moved') {
      $fy = isset($meta['from_year']) ? (int)$meta['from_year'] : null;
      $ty = isset($meta['to_year']) ? (int)$meta['to_year'] : null;
      $desc = ($fy && $ty)
        ? "年度を変更しました（{$fy}年 → {$ty}年）。"
        : "年度を変更しました。";
    } elseif ($log->action === 'data.copied') {
      $ys = isset($meta['years']) && is_array($meta['years']) ? $meta['years'] : [];
      $ys = array_values(array_filter(array_map('intval', $ys), fn($v)=>$v>0));
      $desc = !empty($ys)
        ? "コピーで年度データを作成しました（".implode('年, ', $ys)."年）。"
        : "コピーで年度データを作成しました。";
    } elseif ($log->action === 'data.created') {
      $yy = isset($meta['kihu_year']) && is_numeric($meta['kihu_year']) ? (int)$meta['kihu_year'] : null;
      $desc = $yy ? "{$yy}年のデータを新規作成しました。" : "データを新規作成しました。";
    } elseif ($log->action === 'data.deleted') {
      $yy = isset($meta['kihu_year']) && is_numeric($meta['kihu_year']) ? (int)$meta['kihu_year'] : null;
      $desc = $yy ? "{$yy}年のデータを削除しました。" : "データを削除しました。";
    } elseif ($log->action === 'data.updated') {
      $desc = "提案書日や共有設定などのデータ情報を更新しました。";
    } elseif ($log->action === 'data.year_select.existing') {
      $yy = isset($meta['to_year']) && is_numeric($meta['to_year']) ? (int)$meta['to_year'] : null;
      $desc = $yy ? "{$yy}年の既存データへ移動しました。" : "既存データへ移動しました。";
    } else {
      $desc = "操作（{$log->action}）を実行しました。";
    }
  @endphp

  <div class="p-3 border rounded bg-white">
    <div class="mb-2">
      <div class="text-muted small">日時</div>
      <div>{{ optional($log->created_at)->format('Y-m-d H:i:s') }}</div>
    </div>
    <div class="mb-2">
      <div class="text-muted small">操作ユーザー</div>
      <div>
        @if($actor)
          {{ $actor->name }} ({{ $actor->email }})
        @else
          (unknown)
        @endif
      </div>
    </div>
    <div class="mb-2">
      <div class="text-muted small">内容</div>
      <div><strong>{{ $actionLabel }}</strong></div>
    </div>
    <div class="mb-2">
      <div class="text-muted small">対象</div>
      <div>
        {{ $guestName ?: '—' }}
        @if($yearText)
          <span class="text-muted">/</span> {{ $yearText }}
        @endif
      </div>
    </div>
    <hr>
    <div>
      <div class="text-muted small">説明</div>
      <div>{{ $desc }}</div>
    </div>
  </div>
</div>
@endsection
