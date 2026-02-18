{{-- resources/views/tax/furusato/partials/report_preview_modal.blade.php --}}
{{-- 帳票プレビュー（サムネ一覧→拡大→並び替え） --}}

{{-- 3択：current/max/both（PDF出力と同じ） --}}
<div class="modal fade" id="furusato-preview-mode-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
    <div class="modal-content">
      <div class="modal-header">
        <h15 class="modal-title">どの条件で帳票をプレビューしますか？</h15>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex flex-column align-items-center gap-2">
          <button type="button" class="btn btn-base-blue" data-preview-variant="current" style="height:40px; width:260px;">
            今までに寄付した額でプレビューする
          </button>
          <button type="button" class="btn btn-base-blue" data-preview-variant="max" style="height:40px; width:260px;">
            上限額まで寄付した場合でプレビューする
          </button>
          <button type="button" class="btn btn-base-blue" data-preview-variant="both" style="height:40px; width:260px;">
            両方ともプレビューする
          </button>
        </div>
        <h12 class="text-muted mt-2">
          ※ プレビュー開始時に最新の値で再計算し、上限探索も実行します。
        </h12>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-base-blue" data-bs-dismiss="modal">キャンセル</button>
      </div>
    </div>
  </div>
</div>

{{-- 帳票プレビューモーダル（サムネ一覧） --}}
<div class="modal fade" id="furusato-report-preview-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h15 class="modal-title">帳票プレビュー</h15>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
          <div class="small text-muted">
            サムネをクリック：選択 / ダブルクリック：拡大表示 / ドラッグ：並び替え（保存なし）
          </div>
          <div class="d-flex gap-2">
            <span class="small text-muted d-inline-flex align-items-center me-1" id="furusato-preview-selected-count">選択中：0件</span>
            <button type="button" class="btn btn-base-blue" id="furusato-preview-clear">選択解除</button>
            <button type="button" class="btn btn-base-blue" id="furusato-preview-open-selected">選択を拡大</button>
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
        <button type="button" class="btn btn-base-blue" data-bs-dismiss="modal">閉じる</button>
      </div>
    </div>
  </div>
</div>

{{-- 拡大表示モーダル（選択枚数ぶん並べて表示） --}}
<div class="modal fade" id="furusato-report-viewer-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content">
      <div class="modal-header">
        <div class="d-flex align-items-center gap-3 flex-wrap">
          <span class="small text-muted">
            選択した帳票を「レイヤー」として重ねて表示します（ドラッグ：移動 / ＋−：ズーム / 端で停止）
          </span>
          <button type="button" class="btn btn-outline-secondary btn-sm" id="furusato-layer-reset-all">全リセット</button>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
      </div>
      <div class="modal-body p-2">
        {{-- レイヤーキャンバス --}}
        <div id="furusato-layer-canvas"
             class="border rounded bg-white position-relative"
             style="width:100%; height: calc(100vh - 120px); overflow:hidden; touch-action:none;">
        </div>
      </div>
    </div>
  </div>
</div>

@push('styles')
<style>
      /* プレビュー生成中アラートの見た目（幅/背景/文字） */
      #furusato-preview-loading {
        max-width: 400px;
        margin-left: auto;
        margin-right: auto;
        background-color: #4193d0;
        border-color: #4193d0;
        color: #ffffff;
      }
    
      /* “h14相当” の見た目に寄せる（必要に応じて調整） */
      #furusato-preview-loading > .d-flex > div:last-child {
        font-size: 14px;
        font-weight: 600;
        line-height: 1.4;
      }
    
      /* 3択モーダル内の btn-base-blue：hover/focus/active の色を統一 */
      #furusato-preview-mode-modal .btn.btn-base-blue:hover,
      #furusato-preview-mode-modal .btn.btn-base-blue:focus,
      #furusato-preview-mode-modal .btn.btn-base-blue:active,
      #furusato-preview-mode-modal .btn.btn-base-blue.active {
        background-color: #4193d0;
        border-color: #4193d0 !important;
      }
</style>
@endpush