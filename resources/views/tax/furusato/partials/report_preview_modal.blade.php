{{-- resources/views/tax/furusato/partials/report_preview_modal.blade.php --}}
{{-- 帳票プレビュー（サムネ一覧→拡大→並び替え） --}}

{{-- 3択：current/max/both（PDF出力と同じ） --}}
<div class="modal fade" id="furusato-preview-mode-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">帳票プレビューの内容を選択</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">どの条件で帳票をプレビューしますか？</div>
        <div class="d-grid gap-2">
          <button type="button" class="btn btn-outline-primary" data-preview-variant="current">
            今までに寄付した額でプレビューする
          </button>
          <button type="button" class="btn btn-outline-primary" data-preview-variant="max">
            上限額まで寄付した場合でプレビューする
          </button>
          <button type="button" class="btn btn-primary" data-preview-variant="both">
            両方ともプレビューする
          </button>
        </div>
        <div class="small text-muted mt-2">
          ※ プレビュー開始時に最新の値で再計算し、上限探索も実行します。
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
      </div>
    </div>
  </div>
</div>

{{-- 帳票プレビューモーダル（サムネ一覧） --}}
<div class="modal fade" id="furusato-report-preview-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">帳票プレビュー</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
          <div class="small text-muted">
            サムネをクリック：選択 / ダブルクリック：拡大表示 / ドラッグ：並び替え（保存なし）
          </div>
          <div class="d-flex gap-2">
            <span class="small text-muted d-inline-flex align-items-center me-1" id="furusato-preview-selected-count">選択中：0件</span>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="furusato-preview-clear">選択解除</button>
            <button type="button" class="btn btn-primary btn-sm" id="furusato-preview-open-selected">選択を拡大</button>
          </div>
        </div>

        <div id="furusato-preview-loading" class="alert alert-info py-2 px-3 d-none">
          <div class="d-flex align-items-center gap-2">
            <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
            <div>再計算＆プレビュー生成中です…（しばらくお待ちください）</div>
          </div>
        </div>
        <div id="furusato-preview-error" class="alert alert-danger py-2 px-3 d-none"></div>

        <div id="furusato-preview-grid" class="row g-3"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
      </div>
    </div>
  </div>
</div>

{{-- 拡大表示モーダル（選択枚数ぶん並べて表示） --}}
<div class="modal fade" id="furusato-report-viewer-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content">
      <div class="modal-header">
        <div class="d-flex align-items-center gap-2">
          <span class="small text-muted">
            選択した帳票を並べて表示します（各枠：＋/−で拡大縮小、ドラッグで表示位置移動）
          </span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
      </div>
      <div class="modal-body p-2">
        <div id="furusato-viewer-grid" class="row g-2"></div>
      </div>
    </div>
  </div>
</div>