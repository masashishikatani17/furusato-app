<!-- resources/views/admin/groups/index.blade.php -->
@extends('layouts.min')

@section('title', '部署一覧')

@section('content')
<div class="container" style="max-width: 980px;">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">部署一覧</h4>
    @if($canManage)
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-create-group">
        部署を作成
      </button>
    @endif
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('info'))
    <div class="alert alert-info">{{ session('info') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger">
      <div class="fw-bold mb-1">入力内容を確認してください。</div>
      <ul class="mb-0">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card shadow-sm">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th style="width: 240px;">部署名</th>
              <th style="width: 90px;">状態</th>
              <th>所属</th>
              <th style="width: 260px;">操作</th>
            </tr>
          </thead>
          <tbody>
          @forelse($groups as $g)
            @php
              $gid = (int)$g->id;
              $cGA = (int)($counts['users_group_admin'][$gid] ?? 0);
              $cMB = (int)($counts['users_member'][$gid] ?? 0);
              $cCL = (int)($counts['users_client'][$gid] ?? 0);
              $cGS = (int)($counts['guests'][$gid] ?? 0);
              $cDS = (int)($counts['datas'][$gid] ?? 0);
              $cIV = (int)($counts['invitations_pending'][$gid] ?? 0);
              $isActive = (bool)$g->is_active;
              $statusLabel = $isActive ? '稼働' : '停止';
              $statusClass = $isActive ? 'bg-success' : 'bg-secondary';
              $needTransfer = ($cGA + $cMB + $cCL + $cGS + $cDS + $cIV) > 0;
            @endphp
            <tr>
              <td class="fw-semibold">{{ $g->name }}</td>
              <td>
                <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
              </td>
              <td class="small text-muted">
                users: GA {{ $cGA }} / member {{ $cMB }} / client {{ $cCL }}
                <span class="mx-2">|</span>
                guests: {{ $cGS }}
                <span class="mx-2">|</span>
                datas: {{ $cDS }}
                <span class="mx-2">|</span>
                invitations: {{ $cIV }}
              </td>
              <td class="text-end">
                @if($canManage)
                  <button type="button"
                          class="btn btn-outline-secondary btn-sm"
                          data-bs-toggle="modal"
                          data-bs-target="#modal-rename-group"
                          data-group-id="{{ $gid }}"
                          data-group-name="{{ e($g->name) }}">
                    名称変更
                  </button>

                  @if($isActive)
                    <button type="button"
                            class="btn btn-outline-warning btn-sm ms-1"
                            data-bs-toggle="modal"
                            data-bs-target="#modal-transfer"
                            data-action="deactivate"
                            data-from-group-id="{{ $gid }}"
                            data-from-group-name="{{ e($g->name) }}"
                            data-count-ga="{{ $cGA }}"
                            data-count-member="{{ $cMB }}"
                            data-count-client="{{ $cCL }}"
                            data-count-guests="{{ $cGS }}"
                            data-count-datas="{{ $cDS }}"
                            data-count-invitations="{{ $cIV }}">
                      停止
                    </button>
                  @else
                    <form method="POST" action="{{ route('admin.groups.activate', ['group' => $gid]) }}" class="d-inline">
                      @csrf
                      @method('PATCH')
                      <button type="submit" class="btn btn-outline-success btn-sm ms-1">復活</button>
                    </form>
                  @endif

                  <button type="button"
                          class="btn btn-outline-danger btn-sm ms-1"
                          data-bs-toggle="modal"
                          data-bs-target="#modal-transfer"
                          data-action="destroy"
                          data-from-group-id="{{ $gid }}"
                          data-from-group-name="{{ e($g->name) }}"
                          data-count-ga="{{ $cGA }}"
                          data-count-member="{{ $cMB }}"
                          data-count-client="{{ $cCL }}"
                          data-count-guests="{{ $cGS }}"
                          data-count-datas="{{ $cDS }}"
                          data-count-invitations="{{ $cIV }}">
                    削除
                  </button>
                @else
                  <span class="text-muted small">閲覧のみ</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center text-muted py-4">部署がありません。</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      <div class="text-muted small">
        ※停止/削除は「異動」とセットで実行されます（対象が残らないことが必須）。
      </div>
    </div>
  </div>
</div>

@if($canManage)
  {{-- 作成モーダル --}}
  <div class="modal fade" id="modal-create-group" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form method="POST" action="{{ route('admin.groups.store') }}">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title">部署を作成</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <label class="form-label">部署名</label>
            <input type="text" name="name" class="form-control" maxlength="255" required value="{{ old('name') }}">
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
            <button type="submit" class="btn btn-primary">作成</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  {{-- 名称変更モーダル --}}
  <div class="modal fade" id="modal-rename-group" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form method="POST" id="form-rename-group" action="">
          @csrf
          @method('PUT')
          <div class="modal-header">
            <h5 class="modal-title">部署名を変更</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <label class="form-label">部署名</label>
            <input type="text" name="name" id="rename-group-name" class="form-control" maxlength="255" required>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
            <button type="submit" class="btn btn-primary">更新</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  {{-- 異動＋停止/削除モーダル --}}
  <div class="modal fade" id="modal-transfer" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form method="POST" id="form-transfer" action="">
          @csrf
          <input type="hidden" name="action" id="transfer-action" value="">
          <div class="modal-header">
            <h5 class="modal-title" id="transfer-title">異動</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-2">
              <div class="fw-semibold">対象部署</div>
              <div class="text-muted small" id="transfer-from-name"></div>
            </div>

            <label class="form-label">移動先部署（稼働中のみ）</label>
            <select name="to_group_id" id="transfer-to-group" class="form-select" required>
              <option value="">（選択してください）</option>
              @foreach($activeGroups as $ag)
                <option value="{{ (int)$ag->id }}">{{ $ag->name }}</option>
              @endforeach
            </select>
            <div class="text-muted small mt-2">
              停止・削除する場合は、対象部署の users/guests/datas/invitations を別部署へ異動させることが必須です。
            </div>

            <hr class="my-3">
            <div class="small">
              <div class="fw-semibold mb-1">異動対象件数（予定）</div>
              <ul class="mb-0">
                <li>users: GA <span id="cnt-ga">0</span> / member <span id="cnt-member">0</span> / client <span id="cnt-client">0</span></li>
                <li>guests: <span id="cnt-guests">0</span></li>
                <li>datas: <span id="cnt-datas">0</span>（guest追随で移動）</li>
                <li>invitations: <span id="cnt-invitations">0</span>（未完了のみ）</li>
              </ul>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
            <button type="submit" class="btn btn-danger" id="transfer-submit">実行</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endif
@endsection

@push('scripts')
<script>
(() => {
  const renameModal = document.getElementById('modal-rename-group');
  if (renameModal) {
    renameModal.addEventListener('show.bs.modal', (ev) => {
      const btn = ev.relatedTarget;
      const groupId = btn?.getAttribute('data-group-id');
      const name = btn?.getAttribute('data-group-name') || '';
      const form = document.getElementById('form-rename-group');
      const input = document.getElementById('rename-group-name');
      if (form && groupId) {
        form.action = `{{ url('/admin/groups') }}/${groupId}`;
      }
      if (input) input.value = name;
    });
  }

  const transferModal = document.getElementById('modal-transfer');
  if (transferModal) {
    transferModal.addEventListener('show.bs.modal', (ev) => {
      const btn = ev.relatedTarget;
      const action = btn?.getAttribute('data-action') || '';
      const fromId = btn?.getAttribute('data-from-group-id') || '';
      const fromName = btn?.getAttribute('data-from-group-name') || '';

      const form = document.getElementById('form-transfer');
      const act = document.getElementById('transfer-action');
      const title = document.getElementById('transfer-title');
      const fromNameEl = document.getElementById('transfer-from-name');
      const submit = document.getElementById('transfer-submit');
      const toSelect = document.getElementById('transfer-to-group');

      if (form && fromId) {
        form.action = `{{ url('/admin/groups') }}/${fromId}/transfer`;
      }
      if (act) act.value = action;
      if (fromNameEl) fromNameEl.textContent = fromName;

      if (title && submit) {
        if (action === 'deactivate') {
          title.textContent = '異動して停止';
          submit.textContent = '異動して停止する';
          submit.className = 'btn btn-warning';
        } else {
          title.textContent = '異動して削除';
          submit.textContent = '異動して削除する';
          submit.className = 'btn btn-danger';
        }
      }

      // 移動先候補から自分自身を除外（表示上）
      if (toSelect && fromId) {
        [...toSelect.options].forEach(opt => {
          if (!opt.value) return;
          opt.disabled = (String(opt.value) === String(fromId));
        });
        toSelect.value = '';
      }

      const setText = (id, v) => {
        const el = document.getElementById(id);
        if (el) el.textContent = String(v ?? 0);
      };

      setText('cnt-ga', btn?.getAttribute('data-count-ga'));
      setText('cnt-member', btn?.getAttribute('data-count-member'));
      setText('cnt-client', btn?.getAttribute('data-count-client'));
      setText('cnt-guests', btn?.getAttribute('data-count-guests'));
      setText('cnt-datas', btn?.getAttribute('data-count-datas'));
      setText('cnt-invitations', btn?.getAttribute('data-count-invitations'));
    });
  }
})();
</script>
@endpush