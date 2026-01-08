{{-- resources/views/pdf/4_juminkeigengaku_onestop.blade.php --}}
@extends('pdf.layouts.print')

@section('title','住民税の軽減額（ワンストップ特例）')

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

</style>
@endsection

@section('content')
  <div class="page-frame text-center"><!-- ここが実効幅281mmの中央寄せコンテナ -->
    <table class="table table-base mt-8 mb-2"
           style="width: 250mm; border-collapse: collapse;">
      <tr>
        <td class="text-end bg-grey b-r-no" style="width: 150mm;">
          <h18>住民税の軽減額の計算過程</h18>
        </td>
        <td class="text-end bg-grey b-l-no pe-3" style="width: 100mm;">
          <h18>※ワンストップ特例のケース</h18>
        </td>
      </tr>
    </table>

    <div style="width: 250mm; margin: 0 auto; display: flex; align-items: flex-start; gap: 10mm;">
      <!-- 左側 -->
      <div style="width: 80mm; margin: 0 auto;">
        <table class="table b-none no-overlap mt-5 mb-3"
               style="width: 80mm; table-layout: fixed; border-collapse: collapse; margin: 0 auto; clear:both;">
          <tr>
            <td class="text-start"><h18>★寄附金額と減税額の比較</h18></td>
          </tr>
        </table>
        <table class="table table-compact-p no-overlap table72mm ms-10" style="font-size:14px;line-height:1.8;">
          <colgroup>
            <col style="width:40mm">
            <col style="width:32mm">
          </colgroup>
          <tr>
            <td class="text-start b-b-no"><h14>寄附金額</h14></td>
            <td class="text-end b-b-no"><h14></h14></td>
          </tr>
          <tr>
            <td class="text-start b-t-no"><h14>減税額</h14></td>
            <td class="text-end b-t-no"><h14></h14></td>
          </tr>
          <tr>
            <td><h14>差引：負担額</h14></td>
            <td class="text-end"><h14></h14></td>
          </tr>
        </table>
      </div><!-- /左側 -->

      <!-- 右側 -->
      <div style="width: 160mm; margin: 0 auto;">
        <table class="table b-none no-overlap mt-8 mb-3"
               style="width: 130mm; table-layout: fixed; border-collapse: collapse; margin: 0 auto; clear:both;">
          <tr>
            <td class="text-start ps-3 pt-5">
              ※ワンストップ特例では所得税の軽減の代わりに住民税から控除<br>&nbsp;&nbsp;&nbsp;されます。下記の申告特例控除額(⑯)の数値です。
            </td>
          </tr>
        </table>
      </div>
    </div>

    <div class="table-frame mt-5">
      <table align="center"
             class="table table-compact-p14 table-242mm mt-0"
             style="line-height:1.5; width:242mm; table-layout:fixed;">
        <colgroup>
          <col style="width:9mm">
          <col style="width:54mm">
          <col style="width:8mm">
          <col style="width:27mm">
          <col style="width:27mm"><col style="width:27mm">
          <col style="width:90mm">
        </colgroup>
        <tbody>
          <tr class="bg-grey" style="height:6.3mm">
            <td colspan="2"><h14u>項　　　目</h14u></td>
            <td>&nbsp;</td>
            <td>市区町村民税</td>
            <td>都道府県民税</td>
            <td>合　計</td>
            <td>備　　考</td>
          </tr>
          <tr>
            <td colspan="2" class="text-start">合計(調整控除前所得割額)</td>
            <td>①</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-start b-b-no">３ページの課税所得金額・税額の予測より</td>
          </tr>
          <tr>
            <td colspan="2" class="text-start">調整控除額</td>
            <td>②</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-start b-t-no b-b-no">６ページの人的控除差調整額より</td>
          </tr>
          <tr>
            <td colspan="2" class="text-start">調整控除後所得割額</td>
            <td>③</td>
            <td class="text-end"></td>
            <td class="text-end"></td>
            <td class="text-end"></td>
            <td class="text-start b-t-no b-b-no">&nbsp;</td>
          </tr>
          <tr>
            <td rowspan="5" class="text-center">基<br>本<br>控<br>除<br>額</td>
            <td class="text-start">控除対象額</td>
            <td>④</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-start b-t-no b-b-no">寄附金額(１ページの合計欄)－2,000</td>
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
            <td rowspan="5" class="text-center">特<br>例<br>控<br>除<br>額</td>
            <td class="text-start">控除対象額</td>
            <td>⑨</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-start b-t-no b-b-no"> 寄附金額(１ページのふるさと納税欄)－2,000</td>
          </tr>
          <tr>
            <td class="text-start">特例控除割合</td>
            <td>⑩</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-start b-t-no b-b-no">７ページの特例控除割合を参照</td>
          </tr>
          <tr>
            <td class="text-start">⑨×⑩</td>
            <td>⑪</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-start b-t-no b-b-no">小数点以下２位まで計算表示する</td>
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
            <td class="text-start b-t-no b-b-no">１円未満切り上げ</td>
          </tr>
          <tr>
            <td rowspan="3" style="lh-1">申控告除特額例&nbsp;&nbsp;&nbsp;&nbsp;</td>
            <td class="text-start">特例控除額</td>
            <td>⑭</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-start b-t-no b-b-no">⑬の特例控除額</td>
          </tr>
          <tr>
            <td class="text-start">所得税率の割合</td>
            <td>⑮</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-start b-t-no b-b-no"> 所得税率÷特例控除割合</td>
          </tr>
          <tr>
            <td class="text-start">⑭×⑮</td>
            <td>⑯</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-start b-t-no b-b-no">１円未満切り上げ</td>
          </tr>
          <tr>
            <td colspan="2">合　　計</td>
            <td>⑰</td>
            <td class="bg-cream text-end b-t-strong b-l-strong"><h14></h14></td>
            <td class="bg-cream text-end b-t-strong b-l-strong"><h14></h14></td>
            <td class="bg-cream text-end b-t-strong b-l-strong b-r-strong"><h14></h14></td>
            <td class="text-start b-t-no b-b-no">&nbsp;</td>
          </tr>
        </tbody>
      </table>
    </div>

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

