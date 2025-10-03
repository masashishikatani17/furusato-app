<!-- resources/views/components/group-switcher.blade.php-->
@props(['inline' => false])
@php
    use Illuminate\Support\Facades\Auth;
    use App\Models\Group;
    $user = Auth::user();
    if (!$user) { return; }
    $isOwner   = method_exists($user, 'isOwner') ? $user->isOwner() : false;
    $role      = strtolower((string)($user->role ?? 'member'));
    $canSwitch = $isOwner || in_array($role, ['registrar'], true);
    $key = 'current_group_id';
    if (class_exists(\App\Support\SessionKey::class) && defined(\App\Support\SessionKey::class.'::CURRENT_GROUP_ID')) {
        $key = \App\Support\SessionKey::CURRENT_GROUP_ID;
    }
    $current = session($key); // null=横断
    $groups  = Group::where('company_id', $user->company_id)->orderBy('id')->limit(500)->get();
    $currentLabel = $current ? optional($groups->firstWhere('id',(int)$current))->name : '（すべて）';
@endphp
@if ($inline)
  {{-- インライン表示（枠線なし・余白最小） --}}
  <div class="d-inline-flex align-items-center group-switcher-inline"
       style="gap:8px; padding:0; margin:0; border:0;">
      <div class="text ms-3" style="font-size: 13px;">部署:</div>
      @if ($canSwitch)
          <form method="POST" action="{{ route('common.group.switch') }}"
                id="group-switcher-form"
                class="d-inline-flex align-items-center"
                style="gap:2px; margin:0; padding:0; border:0;">
              @csrf
              <select name="group_id"
                      class="form-select form-select-sm"
                      onchange="this.form.submit()"
                      style="min-width: 220px;">
                  <option value="all" @selected(!$current)>（すべて）</option>
                  @foreach ($groups as $g)
                      <option value="{{ $g->id }}" @selected((int)$current === (int)$g->id)>
                          {{ $g->id }} : {{ $g->name }}
                      </option>
                  @endforeach
              </select>
              <noscript>
                  <button type="submit" class="btn btn-sm btn-outline-secondary">切替</button>
              </noscript>
          </form>
      @else
          <div class="d-inline-flex align-items-center" style="margin:0; padding:0;">
              <input type="text"
                     class="form-control form-control-sm"
                     value="{{ $user->group_id ? optional($groups->firstWhere('id',(int)$user->group_id))->name : '（未所属）' }}"
                     disabled
                     style="min-width: 220px;">
          </div>
      @endif
  </div>
@else
  {{-- 既存のブロック表示（他画面で流用する場合） --}}
  <div class="container" style="max-width: 1200px; border:0; box-shadow:none; background:transparent;">
      <div class="d-flex align-items-center justify-content-end" style="gap: 8px; padding: 6px 0;">
          <div class="text-muted" style="font-size: 12px;">部署</div>
          @if ($canSwitch)
              <form method="POST" action="{{ route('common.group.switch') }}" id="group-switcher-form" class="d-flex" style="gap:6px; margin:0;">
                  @csrf
                  <select name="group_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width: 220px;">
                      <option value="all" @selected(!$current)>（すべて）</option>
                      @foreach ($groups as $g)
                          <option value="{{ $g->id }}" @selected((int)$current === (int)$g->id)>
                              {{ $g->id }} : {{ $g->name }}
                          </option>
                      @endforeach
                  </select>
                  <noscript>
                      <button type="submit" class="btn btn-sm btn-outline-secondary">切替</button>
                  </noscript>
              </form>
          @else
              <div class="d-inline-flex align-items-center">
                  <input type="text" class="form-control form-control-sm" value="{{ $user->group_id ? optional($groups->firstWhere('id',(int)$user->group_id))->name : '（未所属）' }}" disabled style="min-width: 220px;">
              </div>
          @endif
      </div>
  </div>
@endif