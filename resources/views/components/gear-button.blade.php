<!-- resources/views/components/gear-button.blade.php-->
@props([
  'url' => null,
  'label' => '設定',
  'title' => '設定',
  'visible' => true,
  // 配置：fixed=画面右上 / abs=親要素右上 / inline=インラインで横並び
  'position' => 'fixed', // 'fixed' | 'abs' | 'inline'
  // オフセット（abs/fixed 用）
  'top' => 14,
  'right' => 14,
  // アイコンの外形サイズ（px 数値 or CSS長さ）。基準30pxをスケール
  'size' => 30,
  // ギア色（CSSカラー値）。例 '#2563eb', 'rgb(99,102,241)'
  'color' => '#6b7280',
])

@php
  $topVal = is_numeric($top) ? $top.'px' : $top;
  $rightVal = is_numeric($right) ? $right.'px' : $right;
  $sizeVal  = is_numeric($size)  ? $size.'px'  : $size;
  // 指定CSSの基準サイズ(30px)に対する拡大率
  $scaleVal = is_numeric($size) ? round($size / 30, 4) : 1;
  $posClass = match($position){
      'abs'    => 'c-gear-btn--abs',
      'inline' => 'c-gear-btn--inline',
      default  => 'c-gear-btn--fixed',
  };
  $colorVal = is_string($color) ? $color : '#6b7280';
  // 設定ページURL（routeが未定義でも壊れないようフォールバック）
  $settingsUrl = $url
      ?: (\Illuminate\Support\Facades\Route::has('admin.settings') ? route('admin.settings') : '#');
@endphp

@auth
@if($visible)
  <a href="{{ $settingsUrl }}"
     {{ $attributes->merge(['class' => 'c-gear-btn '.$posClass]) }}
     style="--gear-top: {{ $topVal }}; --gear-right: {{ $rightVal }}; --gear-size: {{ $sizeVal }}; --gear-scale: {{ $scaleVal }}; --gear-rotate: 0deg; --gear-color: {{ $colorVal }};"
     aria-label="{{ $label }}"
     title="{{ $title }}"
     onclick="this.classList.add('is-rotating')"
     onkeydown="if(event.key==='Enter'||event.key===' '){ this.classList.add('is-rotating') }">
    <span class="c-gear-outer" aria-hidden="true">
      <span class="c-icon-gear">
        <span class="c-icon-gear__inner"></span>
      </span>
    </span>
  </a>

  @once
    @push('styles')
      <style>
        /* ===== 位置クラス（アンカー自身に付与） ===== */
        .c-gear-btn--fixed { position: fixed;  z-index: 1060; top: var(--gear-top, 14px); right: var(--gear-right, 14px); }
        .c-gear-btn--abs   { position: absolute; z-index: 1060; top: var(--gear-top, 14px); right: var(--gear-right, 14px); }
        .c-gear-btn--inline{ position: static !important; vertical-align: middle; }

        /* ===== ギア（クリック領域＝アンカー） ===== */
        .c-gear-btn{
          display: inline-flex; align-items: center; justify-content: center;
          width: var(--gear-size, 30px); height: var(--gear-size, 30px);
          text-decoration: none; line-height: 0; border: none; background: transparent;
          padding: 0; margin: 0; box-shadow: none; cursor: pointer;
          transition: transform .2s ease;
          touch-action: manipulation;
        }  
        /* ホバー時の周囲暗転・強調は行わない */
        .c-gear-btn:hover{ }
        .c-gear-btn:active{ }
        .c-gear-btn:focus-visible{ outline: 2px solid var(--gear-color); outline-offset: 2px; border-radius: 8px; }

        /* ===== 指定の見た目（基準30pxをCSSで構成） ===== */
        .c-gear-outer{
          position: relative;
          width: 30px; height: 30px;
          display: inline-block;
          transform: rotate(var(--gear-rotate, 0deg)) scale(var(--gear-scale, 1));
          transform-origin: center center;
          transition: transform .2s cubic-bezier(.22,.61,.36,1);
          will-change: transform;
         }
        /* クリック後はclass付与で30°回転を保持（遷移まで維持） */
        .c-gear-btn.is-rotating .c-gear-outer{ --gear-rotate: 30deg; }
        /* JS無効環境向けフォールバック：押下中のみ回転 */
        .c-gear-btn:active .c-gear-outer{ --gear-rotate: 30deg; }
        
        .c-icon-gear,
        .c-icon-gear::before,
        .c-icon-gear::after,
        .c-icon-gear__inner::before{
          position: absolute;
          width: 4px; height: 20px;
          content: '';
          background-color: var(--gear-color, #aaa);
          border-radius: 1px;
        }
        .c-icon-gear{ top: 5px; left: 13px; }
        .c-icon-gear::before{ top: 0; left: 0; transform: rotate(45deg); }
        .c-icon-gear::after { top: 0; left: 0; transform: rotate(-45deg); }
        .c-icon-gear__inner{ position:absolute; top: 2px; left: -6px; width:16px; height:16px; background-color: var(--gear-color, #aaa); border-radius:50%; z-index:5; }
        .c-icon-gear__inner::before{ top: -2px; left: 6px; transform: rotate(90deg); }
        .c-icon-gear__inner::after{
          position:absolute; top:4px; left:4px; content:''; width:8px; height:8px; background-color:#fff; border-radius:50%;
        }

        /* 低モーション設定を尊重 */
        @media (prefers-reduced-motion: reduce) {
          .c-gear-outer{ transition: none; }
          .c-gear-btn{ transition: none; }
        }
      </style>
    @endpush
  @endonce
@endif
@endauth