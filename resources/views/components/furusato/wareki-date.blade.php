@props([
  'name' => null,
  'value' => null,
  'required' => false,
  'readonly' => false,
  'id' => null,
  'class' => '',
  'placeholder' => '選択してください',
])

@php
  $nameStr = is_string($name) ? $name : '';
  $idBase = $id ?: ($nameStr !== '' ? $nameStr : 'wareki_date_' . uniqid());

  // 初期値（ISO: YYYY-MM-DD）… name がある場合は old() を優先
  $iso = null;
  if ($nameStr !== '') {
      $iso = old($nameStr, $value);
  } else {
      $iso = $value;
  }
  $iso = is_string($iso) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso) ? $iso : null;

  $req = (bool)$required;
  $ro  = (bool)$readonly;

  // readonly 表示用（ISO → 和暦）
  $warekiText = $iso ? \App\Support\WarekiDate::format($iso) : '';
@endphp

<div
  class="wareki-date-wrap {{ $class }}"
  data-wareki-date="1"
  data-name="{{ $nameStr }}"
  data-required="{{ $req ? '1' : '0' }}"
  data-readonly="{{ $ro ? '1' : '0' }}"
  data-initial-iso="{{ $iso ?? '' }}"
  id="wd_wrap_{{ $idBase }}"
>
  @if($ro)
    <div class="d-flex align-items-center" style="height:32px;">
      <span class="fw-semibold">{{ $warekiText !== '' ? $warekiText : '—' }}</span>
    </div>
    @if($nameStr !== '')
      <input type="hidden" name="{{ $nameStr }}" value="{{ $iso ?? '' }}" data-role="hidden">
    @endif
  @else
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <select class="form-select form-select-sm" style="height:32px; width:96px;"
              data-role="gengo" aria-label="元号">
        <option value="">{{ $placeholder }}</option>
        <option value="taisho">大正</option>
        <option value="showa">昭和</option>
        <option value="heisei">平成</option>
        <option value="reiwa">令和</option>
      </select>

      <select class="form-select form-select-sm" style="height:32px; width:80px;"
              data-role="year" aria-label="年">
        <option value="">{{ $placeholder }}</option>
      </select>
      <span>年</span>

      <select class="form-select form-select-sm" style="height:32px; width:70px;"
              data-role="month" aria-label="月">
        <option value="">{{ $placeholder }}</option>
        @for($m=1;$m<=12;$m++)
          <option value="{{ $m }}">{{ $m }}</option>
        @endfor
      </select>
      <span>月</span>

      <select class="form-select form-select-sm" style="height:32px; width:70px;"
              data-role="day" aria-label="日">
        <option value="">{{ $placeholder }}</option>
      </select>
      <span>日</span>
    </div>

    @if($nameStr !== '')
      <input type="hidden" name="{{ $nameStr }}" value="{{ $iso ?? '' }}" data-role="hidden">
    @endif
    <div class="text-danger small mt-1" data-role="error" style="display:none;"></div>
  @endif
</div>