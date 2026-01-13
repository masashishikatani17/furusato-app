
{{-- resources/views/pdf/0_hyoshi.blade.php --}}
@extends('pdf.layouts.print')

@section('title','表紙')

@section('head')
<style>
  
  /* A4横 + 余白（上 右 下 左） */
        @page { size: A4 landscape; margin: 17mm 6mm 17mm 6mm; }
        
        /* 中央寄せしたい場合だけ（任意） */
        .page-frame{
          width: calc(297mm - 12mm); /* 例：左右合計12mm を引く */
          margin: 0 auto;
          text-align: center; /* ← inline-block を中央に寄せるため */
        }
        
        /* テーブルの基本（これだけでよいことが多い） */
        table{ border-collapse: collapse; table-layout: fixed; }
        
        /* ★表紙：中央寄せ（DomPDFは table に display を当てると壊れるため wrapper で寄せる） */
        .cover-frame{ width: 100%; }
        .cover-center{ width: 264mm; margin: 0 auto; }

        /* ★表紙は必ず横書き：共通CSSの縦書き(writing-mode)や回転(transform)を打ち消す */
        html, body,
        section.sheet,
        .cover, .cover-frame,
        table, tbody, tr, td, th
        {
          writing-mode: horizontal-tb !important;
          text-orientation: mixed !important;
          transform: none !important;
        } 
       
       /* 表紙だけ：高さが増えないように */
        .cover-frame td, .cover-frame th { padding: 0 !important; border: 0 !important; } 
        
        /* ★中央寄せは layout の .cover/.cover-frame（inline-block）で吸収する */
</style>
@endsection

@section('content')
  {{-- Data表示は将来埋める前提で “いったん空欄” --}}
  @php
    $coverGuest = (string)($cover_guest_name ?? '');
    $coverDate  = (string)($cover_date ?? '');
    $coverOrg   = (string)($cover_org ?? '');
  @endphp

  <section class="sheet" aria-label="表紙">
   <div class="page-frame text-center"><!-- ここが実効幅281mmの中央寄せコンテナ -->
         <table class="table  b-none text-center mb-0 mt-11"
             style="width:264mm; margin: 0 auto; outline:2px solid #000; outline-offset:-2px;">
            <tbody>
              <tr>
                <td style="height:24mm;" colspan="3" valign="bottom" class="text-start b-none ps-20">
                  <h24>山田太郎様{{ $coverGuest }}</h24>
                </td>
              </tr>
              <tr>
                <td style="height:20mm;" class="text-start b-none ps-130" colspan="3">&nbsp;</td>
              </tr>
              <tr>
                <td class="b-none" colspan="3" valign="middle" style="height:32mm; text-align:center;">
                  <!-- ★184mm の青帯を中央寄せ（列幅に依存しない） -->
                  <table class="b-none" style="width:184mm; height:32mm; margin:0 auto; border-collapse:collapse; table-layout:fixed;">
                    <tr>
                      <td class="b-none" bgcolor="#0070C0" style="height:32mm; text-align:center; vertical-align:middle;">
                        <h30 style="color:#ffffff">ふるさと上限額シミュレーション</h30>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
              <tr>
                <td style="height:40mm;" class="text-center b-none" colspan="3" valign="bottom"><h24>2025年9月16日{{ $coverDate }}</h24></td>
              </tr>
              <tr>
                <td style="height:30mm;" class="text-center b-none" colspan="3" valign="bottom"><h24>公認会計士・税理士 ABC 税理士法人{{ $coverOrg }}</h24></td>
              </tr>
              <tr>
                <td class="b-none" colspan="3" style="height:26mm;"></td>
              </tr>
            </tbody>
          </table>
     </div>
    </div> 
  </section>
@endsection
