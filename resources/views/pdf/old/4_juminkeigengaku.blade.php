{{-- resources/views/pdf/4_juminkeigengaku.blade.php --}}
@extends('pdf.layouts.print')

@section('title','住民税の軽減額')

@section('head')
<style>
  /* A4横 + 余白（上 右 下 左） */
        @page { size: A4 landscape; margin: 10mm 6mm 10mm 6mm; }
        
        /* 中央寄せしたい場合だけ（任意） */
        .page-frame{
          width: calc(297mm - 12mm); /* 例：左右合計12mm を引く */
          margin: 0 auto;
          text-align: center; /* ← inline-block を中央に寄せるため */
        }
        
        /* テーブルの基本（これだけでよいことが多い） */
        table{ border-collapse: collapse; table-layout: fixed; }
      
</style>
@endsection

@section('content')
 @php
   $fmt = static fn($v) => number_format((int)($v ?? 0));
   $rows = is_array($jumin_rows ?? null) ? $jumin_rows : [];
   $sum  = is_array($jumin_summary ?? null) ? $jumin_summary : [];
 @endphp
 <div class="page-frame text-center"><!-- ここが実効幅281mmの中央寄せコンテナ -->
  <div class="page-content">
  <table class="table b-none no-overlap mb-1"
         style="width: 250mm; table-layout: fixed; border-collapse: collapse; margin: 0 auto; clear:both;">
    <tr>
      <td class="text-start"><h16>★上限額まで寄附した場合</h16></td>
    </tr>
  </table>

  <table class="table table-base ms-12"
         style="width: 250mm; table-layout: fixed; border-collapse: collapse;
         outline:2px solid #000; outline-offset:-2px;">
    <tr>
      <td class="text-center bg-grey"><h18>所得税・住民税の軽減額の計算過程</h18></td>
    </tr>
  </table>

  <!-- ★ flex をやめて、横並びはレイアウト用テーブルで固定（DomPDF安定） -->
  <table class="table b-none no-overlap"
         style="width:250mm; table-layout:fixed; border-collapse:collapse; margin:0 auto;">
    <colgroup>
      <col style="width:140mm;">
      <col style="width:10mm;">
      <col style="width:100mm;">
    </colgroup>
    <tr>
      <!-- 左側（140mm） -->
      <td class="b-none" style="vertical-align:top; padding:0; text-align:left;">

        <table class="table b-none no-overlap ms-1 mt-3 mb-1"
               style="width: 140mm; table-layout: fixed; border-collapse: collapse; margin: 0 auto; clear:both;">
          <tr>
            <td class="text-start ps-0"><h18>■所得税の軽減額</h18></td>
          </tr>
        </table>

        <table class="table table-compact-p no-overlap table-105mm ms-5" style="width:105mm;table-layout:fixed; font-size:14px;line-height:1.5;">
          <colgroup>
            <col style="width:75mm">
            <col style="width:30mm">
          </colgroup>
          <tbody>
            <tr>
              <td class="text-start"><h14>ふるさと納税をしなかった場合の税額</h14></td>
              <td class="text-end"><h14u>{{ $fmt($itax_no_furusato ?? 0) }}</h14u></td>
            </tr>
            <tr>
              <td class="text-start"><h14>上限額までふるさと納税をした場合の税額</h14></td>
              <td class="text-end"><h14u>{{ $fmt($itax_at_max ?? 0) }}</h14u></td>
            </tr>
            <tr class="bg-cream">
              <td><h14>差額（所得税の軽減額）</h14></td>
              <td class="text-end b-strong"><h14>{{ $fmt($itax_saved ?? 0) }}</h14></td>
            </tr>
          </tbody>
        </table>

      </td>

      <!-- gap（10mm） -->
      <td class="b-none" style="padding:0;">&nbsp;</td>

      <!-- 右側（100mm） -->
      <td class="b-none" style="vertical-align:top; padding:0; text-align:left;">

        <table class="table b-none no-overlap mt-3 mb-1"
               style="width: 100mm; table-layout: fixed; border-collapse: collapse; margin: 0 auto; clear:both;">
          <tr>
            <td class="text-start"><h18>★寄附金額と減税額の比較</h18></td>
          </tr>
        </table>

        <table class="table b-none no-overlap" style="border-collapse:collapse; margin:0;">
          <tr>
            <!-- 左：罫線ありの表 -->
            <td class="b-none" style="vertical-align:top; padding:0;">
              <table class="table table-compact-p no-overlap ms-3" style="width:52mm; table-layout:fixed; table-layout:fixed;font-size:14px; line-height:1.5;">
                <colgroup>
                  <col style="width:30mm">
                  <col style="width:22mm">
                </colgroup>
                <tbody>
                  <tr>
                    <td class="text-start b-b-no"><h14>寄附金額</h14></td>
                    <td class="text-end b-b-no"><h14>{{ $fmt($donation_amount ?? 0) }}</h14></td>
                  </tr>
                  <tr>
                    <td class="text-start b-t-no"><h14>減税額</h14></td>
                    <td class="text-end b-t-no"><h14>{{ $fmt($tax_saved_total ?? 0) }}</h14></td>
                  </tr>
                  <tr>
                    <td><h14>差引：負担額</h14></td>
                    <td class="text-end"><h14>{{ $fmt($burden_amount ?? 0) }}</h14></td>
                  </tr>
                </tbody>
              </table>
            </td>

            <!-- 右：罫線なし（←所得税+住民税） -->
            <td class="b-none" style="vertical-align:middle; padding:0 0 0 6mm;">
              <table class="table b-none no-overlap table-30mm" style="border-collapse:collapse;">
                <tbody>
                  <tr>
                    <td class="b-none text-start"><h14u>←所得税+住民税</h14u></td>
                  </tr>
                </tbody>
              </table>
            </td>
          </tr>
        </table>

      </td>
    </tr>
  </table>


    <table class="table b-none no-overlap mt-3 mb-1"
           style="width: 250mm; table-layout: fixed; border-collapse: collapse; margin: 0 auto; clear:both;">
      <tr>
        <td class="text-start"><h18>■住民税の軽減額</h18></td>
      </tr>
    </table>
   
    <table class="table table-compact-p14 text-center mt-1 ms-12" 
      style="width: 250mm;  line-height:1.5; outline:2px solid #000; outline-offset:-2px;">
        <colgroup>
          <col style="width:8mm">
          <col style="width:9mm">
          <col style="width:54mm">
          <col style="width:8mm">
          <col style="width:27mm">
          <col style="width:27mm">
          <col style="width:27mm">
          <col style="width:90mm">
        </colgroup>
        <tbody>
          <tr style="height:6.3mm">
            <td colspan="3" class="bg-grey"><h14u>項　　　目</h14u></td>
            <td class="bg-grey"></td>
            <td class="bg-grey">市区町村民税</td>
            <td class="bg-grey">都道府県民税</td>
            <td class="bg-grey">合　計</td>
            <td class="bg-grey">備　　　考</td>
          </tr>
          <tr>
            <td colspan="3" class="text-start">合計(調整控除前所得割額)</td>
            <td>①</td>
            <td class="text-end">{{ $fmt($rows['mae']['muni'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($rows['mae']['pref'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($rows['mae']['total'] ?? 0) }}</td>
            <td class="text-start b-b-no">３ページの課税所得金額・税額の予測より</td>
          </tr>
          <tr>
            <td colspan="3" class="text-start">調整控除額</td>
            <td>②</td>
            <td class="text-end">{{ $fmt($rows['chosei_kojo']['muni'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($rows['chosei_kojo']['pref'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($rows['chosei_kojo']['total'] ?? 0) }}</td>
            <td class="text-start b-t-no b-b-no">６ページの人的控除差調整額より</td>
          </tr>
          <tr>
            <td colspan="3" class="text-start">調整控除後所得割額</td>
            <td>③</td>
            <td class="text-end">{{ $fmt($rows['go']['muni'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($rows['go']['pref'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($rows['go']['total'] ?? 0) }}</td>
            <td class="text-start b-t-no b-b-no">&nbsp;</td>
          </tr>
          <tr>
            <td rowspan="11" class="text-center"><h15>寄<br>附<br>金<br>税<br>額<br>控<br>除<br>額</h15></td>
            <td rowspan="5">基<br>本<br>控<br>除<br>額</td>
            <td class="text-start">控除対象額</td>
            <td>④</td>
            <td class="text-end">{{ $fmt($rows['kihon']['target']['muni'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($rows['kihon']['target']['pref'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($rows['kihon']['target']['total'] ?? 0) }}</td>
            <td class="text-start b-t-no b-b-no"> 寄附金額(１ページの合計欄)－2,000</td>
          </tr>
          <tr>
            <td class="text-start">控除限度額</td>
            <td>⑤</td>
            <td class="text-end">{{ $fmt($rows['kihon']['cap30']['muni'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($rows['kihon']['cap30']['pref'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($rows['kihon']['cap30']['total'] ?? 0) }}</td>
            <td class="text-start b-t-no b-b-no">２ページの総所得金額等×30％</td>
          </tr>
          <tr>
            <td class="text-start">④と⑤のいずれか小さい額</td>
            <td>⑥</td>
            <td class="text-end">{{ $fmt($rows['kihon']['min']['muni'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($rows['kihon']['min']['pref'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($rows['kihon']['min']['total'] ?? 0) }}</td>
            <td class="text-start b-t-no b-b-no">&nbsp;</td>
          </tr>
          <tr>
            <td class="text-start">控除率</td>
            <td>⑦</td>
            <td class="text-end">{{ (int)($rows['kihon']['rate']['muni'] ?? 0) }}%</td>
            <td class="text-end">{{ (int)($rows['kihon']['rate']['pref'] ?? 0) }}%</td>
            <td class="text-end">{{ (int)($rows['kihon']['rate']['total'] ?? 0) }}%</td>
            <td class="text-start b-t-no b-b-no">&nbsp;</td>
          </tr>
          <tr>
            <td class="text-start">控除額(⑥×⑦)</td>
            <td>⑧</td>
            <td class="text-end">{{ $fmt($rows['kihon']['amount']['muni'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($rows['kihon']['amount']['pref'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($rows['kihon']['amount']['total'] ?? 0) }}</td>
            <td class="text-start b-t-no b-b-no">&nbsp;</td>
          </tr>
          <tr>
            <td rowspan="5">特<br>例<br>控<br>除<br>額</td>
            <td class="text-start">控除対象額</td>
            <td>⑨</td>
            <td class="text-end">{{ $fmt($rows['tokurei']['target']['muni'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($rows['tokurei']['target']['pref'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($rows['tokurei']['target']['total'] ?? 0) }}</td>
            <td class="text-start b-t-no b-b-no">寄附金額(１ページのふるさと納税欄)－2,000</td>
          </tr>
          <tr>
            <td class="text-start">特例控除割合</td>
            <td>⑩</td>
            <td class="text-end">{{ number_format((float)($rows['tokurei']['rate_final_pct'] ?? 0), 2) }}%</td>
            <td class="text-end">{{ number_format((float)($rows['tokurei']['rate_final_pct'] ?? 0), 2) }}%</td>
            <td class="text-end">{{ number_format((float)($rows['tokurei']['rate_final_pct'] ?? 0), 2) }}%</td>
            <td class="text-start b-t-no b-b-no"> ７ページの特例控除割合を参照</td>
          </tr>
          <tr>
            <td class="text-start">⑨×⑩</td>
            <td><span class="marubox">11</span></td>
            <td class="text-end">{{ $rows['tokurei']['calc11']['muni'] ?? '0.00' }}</td>
            <td class="text-end">{{ $rows['tokurei']['calc11']['pref'] ?? '0.00' }}</td>
            <td class="text-end">{{ $rows['tokurei']['calc11']['total'] ?? '0.00' }}</td>
            <td class="text-start b-t-no b-b-no"> 小数点以下２位まで計算表示する</td>
          </tr>
          <tr>
            <td class="text-start">控除限度額</td>
            <td><span class="marubox">12</span></td>
            <td class="text-end">{{ $fmt($rows['tokurei']['cap20']['muni'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($rows['tokurei']['cap20']['pref'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($rows['tokurei']['cap20']['total'] ?? 0) }}</td>
            <td class="text-start b-t-no b-b-no">調整控除後所得割額(③)×20％</td>
          </tr>
          <tr>
            <td class="text-start"><span class="marubox">11</span>と<span class="marubox">12</span>のいずれか小さい額</td>
            <td><span class="marubox">13</span></td>
            <td class="text-end">{{ $fmt($rows['tokurei']['jogen']['muni'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($rows['tokurei']['jogen']['pref'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($rows['tokurei']['jogen']['total'] ?? 0) }}</td>
            <td class="text-start b-t-no b-b-no"> １円未満切り上げ</td>
          </tr>
          <tr>
            <td colspan="2">合　　計</td>
            <td><span class="marubox">14</span></td>
            <td class="text-end bg-cream b-t-strong b-l-strong">{{ $fmt($rows['kifukin_total']['muni'] ?? 0) }}</td>
            <td class="text-end bg-cream b-t-strong b-l-strong">{{ $fmt($rows['kifukin_total']['pref'] ?? 0) }}</td>
            <td class="text-end bg-cream b-t-strong b-l-strong b-r-strong">{{ $fmt($rows['kifukin_total']['total'] ?? 0) }}</td>
            <td class="text-start b-t-no b-b-no">&nbsp;</td>
          </tr>
        </tbody>
    </table>
    <table class="table table-compact-p14 text-start table-160mm mt-2 ms-12" 
    style="width:160mm; line-height:1.5; outline:2px solid #000; outline-offset:-2px;">
	    <colgroup>
	      <col style="width:79mm">
	      <col style="width:27mm">
	      <col style="width:27mm">
	      <col style="width:27mm">	      
        </colgroup>
    	    <tbody>
    	      <tr>
    	        <td class="text-start">ふるさと納税以外の減税額</td>
    	        <td class="text-end">{{ $fmt($sum['other']['muni'] ?? 0) }}</td>
    	        <td class="text-end">{{ $fmt($sum['other']['pref'] ?? 0) }}</td>
    	        <td class="text-end">{{ $fmt($sum['other']['total'] ?? 0) }}</td>
            </tr>
            <tr>
    	        <td class="text-start"><h14>ふるさと納税だけの減税額</h14></td>
    	        <td class="text-end bg-cream">{{ $fmt($sum['furusato_only']['muni'] ?? 0) }}</td>
    	        <td class="text-end bg-cream">{{ $fmt($sum['furusato_only']['pref'] ?? 0) }}</td>
    	        <td class="text-end bg-cream b-strong"><h14>{{ $fmt($sum['furusato_only']['total'] ?? 0) }}</h14></td>
            </tr>
    	      <tr>
    	        <td class="text-start">税額控除不能分（３ページの★）</td>
    	        <td class="text-end">{{ $fmt($sum['unable']['muni'] ?? 0) }}</td>
    	        <td class="text-end">{{ $fmt($sum['unable']['pref'] ?? 0) }}</td>
    	        <td class="text-end">{{ $fmt($sum['unable']['total'] ?? 0) }}</td>
            </tr>
    	      <tr>
    	        <td class="text-start">住民税の最終減税額</td>
    	        <td class="text-end bg-cream">{{ $fmt($sum['final']['muni'] ?? 0) }}</td>
    	        <td class="text-end bg-cream">{{ $fmt($sum['final']['pref'] ?? 0) }}</td>
    	        <td class="text-end bg-cream b-strong"><h14>{{ $fmt($sum['final']['total'] ?? 0) }}</h14></td>
            </tr>
         </tbody>
      </table>
      </div><!-- 本文終り -->
      <div class="page-footer">
        <div class="footer-inner">
          <table class="table b-none no-overlap mb-0">
            <tr>
              <td><h14u>４ページ</h14u></td>
            </tr>
          </table>
        </div>
      </div>
  </div><!-- /.page-frame -->
@endsection


