{{-- resources/views/pdf/4_juminkeigengaku.blade.php --}}
@extends('pdf.layouts.print')

@section('title','住民税の軽減額')

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
        
      /* ページ右下の固定フッター（下から12mm） */
        .page-footer{
          position: fixed;
          left: 0;
          right: 0;
          bottom: 12mm;          /* ← ここが「下から」 */
          width: 100%;
        }

        /* 250mm枠の箱：border込みで250mmに収める */
        .table-frame.w-250mm{
          width: 250mm !important;
          margin: 0 auto !important;
          box-sizing: border-box !important;
          padding: 0 !important;
        }
        .table-frame.w-250mm > table{
          width: 100% !important;
          margin: 0 !important;
        }
</style>
@endsection

@section('content')
 <div class="page-frame text-center"><!-- ここが実効幅281mmの中央寄せコンテナ -->

  <table class="table b-none no-overlap mt-5 mb-1"
         style="width: 250mm; table-layout: fixed; border-collapse: collapse; margin: 0 auto; clear:both;">
    <tr>
      <td class="text-start"><h16>★上限額まで寄附した場合</h16></td>
    </tr>
  </table>

  <table class="table table-base"
         style="width: 250mm; table-layout: fixed; border-collapse: collapse; margin: 0 auto;
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
              <td class="text-end"><h14u></h14u></td>
            </tr>
            <tr>
              <td class="text-start"><h14>上限額までふるさと納税をした場合の税額</h14></td>
              <td class="text-end"><h14u></h14u></td>
            </tr>
            <tr class="bg-cream">
              <td><h14>差額（所得税の軽減額）</h14></td>
              <td class="text-end b-strong"><h14></h14></td>
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
                    <td class="text-start"><h14>寄附金額</h14></td>
                    <td class="text-end"><h14></h14></td>
                  </tr>
                  <tr>
                    <td class="text-start"><h14>減税額</h14></td>
                    <td class="text-end"><h14></h14></td>
                  </tr>
                  <tr>
                    <td><h14>差引：負担額</h14></td>
                    <td class="text-end"><h14></h14></td>
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
   
    <table class="table table-compact-p14 text-center mt-1" 
      style="width: 250mm; line-height:1.5; outline:2px solid #000; outline-offset:-2px;">
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
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-start b-b-no">３ページの課税所得金額・税額の予測より</td>
          </tr>
          <tr>
            <td colspan="3" class="text-start">調整控除額</td>
            <td>②</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-start b-t-no b-b-no">６ページの人的控除差調整額より</td>
          </tr>
          <tr>
            <td colspan="3" class="text-start">調整控除後所得割額</td>
            <td>③</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-start b-t-no b-b-no">&nbsp;</td>
          </tr>
          <tr>
            <td rowspan="11" class="text-center"><h15>寄<br>附<br>金<br>税<br>額<br>控<br>除<br>額</h15></td>
            <td rowspan="5">基<br>本<br>控<br>除<br>額</td>
            <td class="text-start">控除対象額</td>
            <td>④</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-start b-t-no b-b-no"> 寄附金額(１ページの合計欄)－2,000</td>
          </tr>
          <tr>
            <td class="text-start">控除限度額</td>
            <td>⑤</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-start b-t-no b-b-no">２ページの総所得金額等×30％</td>
          </tr>
          <tr>
            <td class="text-start">④と⑤のいずれか小さい額</td>
            <td>⑥</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-start b-t-no b-b-no">&nbsp;</td>
          </tr>
          <tr>
            <td class="text-start">控除率</td>
            <td>⑦</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-start b-t-no b-b-no">&nbsp;</td>
          </tr>
          <tr>
            <td class="text-start">控除額(⑥×⑦)</td>
            <td>⑧</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-start b-t-no b-b-no">&nbsp;</td>
          </tr>
          <tr>
            <td rowspan="5">特<br>例<br>控<br>除<br>額</td>
            <td class="text-start">控除対象額</td>
            <td>⑨</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-start b-t-no b-b-no">寄附金額(１ページのふるさと納税欄)－2,000</td>
          </tr>
          <tr>
            <td class="text-start">特例控除割合</td>
            <td>⑩</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-start b-t-no b-b-no"> ７ページの特例控除割合を参照</td>
          </tr>
          <tr>
            <td class="text-start">⑨×⑩</td>
            <td>⑪</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-start b-t-no b-b-no"> 小数点以下２位まで計算表示する</td>
          </tr>
          <tr>
            <td class="text-start">控除限度額</td>
            <td>⑫</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-start b-t-no b-b-no">調整控除後所得割額(③)×20％</td>
          </tr>
          <tr>
            <td class="text-start">⑪と⑫のいずれか小さい額</td>
            <td>⑬</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-start b-t-no b-b-no"> １円未満切り上げ</td>
          </tr>
          <tr>
            <td colspan="2">合　　計</td>
            <td>⑭</td>
            <td class="text-end bg-cream b-t-strong b-l-strong">&nbsp;</td>
            <td class="text-end bg-cream b-t-strong b-l-strong">&nbsp;</td>
            <td class="text-end bg-cream b-t-strong b-l-strong b-r-strong">&nbsp;</td>
            <td class="text-start b-t-no b-b-no">&nbsp;</td>
          </tr>
        </tbody>
    </table>
    <table class="table table-compact-p14 text-start table-160mm mt-2" 
    style="width:160mm; line-height:1.5; margin: 0 auto; outline:2px solid #000; outline-offset:-2px;">
	    <colgroup>
	      <col style="width:79mm">
	      <col style="width:27mm">
	      <col style="width:27mm">
	      <col style="width:27mm">	      
        </colgroup>
    	    <tbody>
    	      <tr>
    	        <td class="text-start">ふるさと納税以外の減税額</td>
    	        <td class="text-end">&nbsp;</td>
    	        <td class="text-end">&nbsp;</td>
    	        <td class="text-end">&nbsp;</td>
            </tr>
            <tr>
    	        <td class="text-start"><h14>ふるさと納税だけの減税額</h14></td>
    	        <td class="text-end bg-cream">&nbsp;</td>
    	        <td class="text-end bg-cream">&nbsp;</td>
    	        <td class="text-end bg-cream b-strong"><h14>&nbsp;</h14></td>
            </tr>
    	      <tr>
    	        <td class="text-start">税額控除不能分（３ページの★）</td>
    	        <td class="text-end">&nbsp;</td>
    	        <td class="text-end">&nbsp;</td>
    	        <td class="text-end">&nbsp;</td>
            </tr>
    	      <tr>
    	        <td class="text-start">住民税の最終減税額</td>
    	        <td class="text-end bg-cream">&nbsp;</td>
    	        <td class="text-end bg-cream">&nbsp;</td>
    	        <td class="text-end bg-cream b-strong"><h14>&nbsp;</h14></td>
            </tr>
         </tbody>
      </table>

      <div class="page-footer">
        <div class="footer-inner">
          <table class="table b-none no-overlap mb-0"
                 style="width: 248mm; table-layout: fixed; border-collapse: collapse; margin: 0 auto; clear:both;">
            <tr>
              <td class="text-end"><h14u>４ページ</h14u></td>
            </tr>
          </table>
        </div>
      </div>
  </div><!-- /.page-frame -->
@endsection


