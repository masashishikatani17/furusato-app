<!-- resources/views/pdf/6_jintekikojosatyosei.blade.php -->
@extends('pdf.layouts.print')

@section('title','令和７年の所得・税額予想 - 人的控除差調整額')

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
     
        
  /* 表を右/左に寄せる */
  .ms-auto { margin-left: auto !important; }
  .me-auto { margin-right: auto !important; }

  .clear-both { clear: both !important; }

  /* === 斜線（右上→左下）：DomPDF用（JSを使わない） === */
  td.diag-auto, th.diag-auto { position: relative; overflow: hidden; }
  td.diag-auto::after, th.diag-auto::after{
    content: "";
    position: absolute;
    inset: 0;
    pointer-events: none;
    /* 右上→左下の斜線 */
    background:
      linear-gradient(to bottom right,
        transparent 49.4%,
        var(--diag-color,#000) 49.6%,
        var(--diag-color,#000) 50.4%,
        transparent 50.6%);
      }

      /* table-compact-p14 内の ps-3 は padding を上書き */
      table.table-compact-p14 td.ps-3,
      table.table-compact-p14 th.ps-3 {
        padding: 1px 2px 1px 12px !important;
      }
</style>
@endsection

@section('content')
  <div class="page-frame text-center">
  <div class="page-content">
    <!-- タイトル行 -->
    <table class="table b-none no-overlap mb-5"
           style="width: 230mm; table-layout: fixed; border-collapse: collapse;
                  margin: 0 auto; clear:both;">
      <tr>
        <td class="text-center"><h18>人的控除差調整額</h18></td>
      </tr>
    </table>

    <!-- ★ flex をやめて、横並びはレイアウト用テーブルで固定（DomPDF安定） -->
    <table class="table b-none no-overlap"
           style="width:260mm; table-layout:fixed; border-collapse:collapse; margin:0 auto;">
      <colgroup>
        <col style="width:125mm;">
        <col style="width:10mm;">
        <col style="width:125mm;">
      </colgroup>
      <tr>
        <!-- 左側 -->
        <td class="b-none" style="vertical-align:top; padding:0; text-align:left;">
        <h16 class="mb-3">■人的控除差調整額を創設した趣旨</h16>
        <div class="ms-4 mt-3 mb-10 me-5" style="line-height:1.8;">
          <h14u>
          　 平成19年度より税源移譲のため所得税の最低税率を10％から5％に引き下げる一方、個人住民税の所得割の税率を5％から10％に引き上げることとしました。<br>
          　ところが所得税と個人住民税では配偶者控除や扶養控除などの人的控除額に差があり、同じ収入でも個人住民税の課税所得金額が大きくなります。<br>
          　そこで個人住民税の課税所得金額を所得税のそれに合わせるため調整することとしました。これを人的控除差調整額といい、住民税の課税明細書では税額控除欄に調整控除として計算・表示されています。
          </h14u>
        </div>

        <h16 class="mb-3 mt-10">■調整控除額の計算</h16>
        <h14u class="ms-3 mb-1">①個人住民税の合計課税所得金額が200万円以下の場合</h14u>
        <table class="table table-compact-p14 ms-5 mb-5" style="width: 110mm;font-size:14px;line-height:1.7;">
          <tbody>
            <tr>
              <td class="text-start me-3 ps-3 b-b-no b-r-no" width="70%">a&nbsp;&nbsp;&nbsp;&nbsp;５万円＋人的控除額の差の合計額</td>
              <td width="8%" class="b-b-no b-x-no">････</td>
              <td class="text-end b-b-no b-l-no" width="22%">150,000</td>
            </tr>
            <tr>
              <td class="text-start ps-3 b-t-no b-b-no b-r-no">b&nbsp;&nbsp;&nbsp;&nbsp;合計課税所得金額</td>
              <td class="b-none">････</td>
              <td class="text-end b-t-no b-b-no b-l-no">20,000,000</td>
            </tr>
            <tr>
              <td class="text-start ps-3 b-t-no b-r-no">c&nbsp;&nbsp;&nbsp;&nbsp;いずれか低い金額×税率(2％＋3％)</td>
              <td class="b-t-no b-r-no b-l-no">････</td>
              <td class="text-end b-l-no b-t-no">7,500</td>
            </tr>
          </tbody>
        </table>

        <h14u class="mt-5 ms-3 mb-1 clear-both">②個人住民税の合計課税所得金額が200万円超の場合</h14u>
        <table class="table table-compact-p14 ms-5 mb-2" style="width: 110mm;font-size:14px;line-height:1.7;">
          <tbody>
            <tr>
              <td class="text-start ps-3 b-b-no b-r-no" width="70%">a&nbsp;&nbsp;&nbsp;&nbsp;(５万円＋人的控除額の差の合計額) <br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ー(合計課税所得金額ー200万円)</td>
              <td width="8%" class="b-b-no b-l-no b-r-no">････</td>
              <td class="text-end b-b-no b-l-no" width="22%">200,000</td>
            </tr>
            <tr>
              <td class="text-start ps-3 b-t-no b-b-no b-r-no">b&nbsp;&nbsp;&nbsp;&nbsp;５万円</td>
              <td class="b-none">････</td>
              <td class="text-end b-l-no b-t-no b-b-no">50,000</td>
            </tr>
            <tr>
              <td class="text-start ps-3 b-t-no b-r-no">c&nbsp;&nbsp;&nbsp;&nbsp;いずれか大きい金額×税率(2％＋3％)</td>
              <td class="b-l-no b-r-no b-t-no">････</td>
              <td class="text-end b-l-no b-t-no">10,000</td>
            </tr>
          </tbody>
        </table>
        </td>

        <!-- gap -->
        <td class="b-none" style="padding:0;">&nbsp;</td>

        <!-- 右側 -->
        <td class="b-none" style="vertical-align:top; padding:0; text-align:left;">
        <table class="table table-compact-p14 text-start no-overlap no-outer-border"
               style="width: 100%; font-size:14px; line-height:1.6; margin: 0 auto;">
          <colgroup>
            <col style="width:30mm">
            <col style="width:20mm">
            <col style="width:50mm">
            <col style="width:20mm">
            <col style="width:10mm">
          </colgroup>
          <tbody>
            <tr>
              <td colspan="3" class="text-center bg-grey"><h14>控除の種類</h14></td>
              <td class="text-center bg-grey"><h14>令和７年</h14></td>
              <td class="b-none"></td>
            </tr>
						<tr>
						  <td colspan="2" rowspan="3" class="text-start ps-3">障害者控除</td>
						  <td class="text-start">一般の障害者</td>
						  <td class="text-end">1万円</td>
						  <td class=" b-none">２人</td>
					  </tr>
						<tr>
						  <td class="text-start">特別障害者</td>
						  <td class="text-end">10万円</td>
						  <td class="b-none">1人</td>
					  </tr>
						<tr>
						  <td class="text-start">同居特別障害者</td>
						  <td class="text-end">22万円</td>
						  <td class="b-none">1人</td>
					  </tr>
						<tr>
						  <td colspan="3" class="text-start ps-3">寡婦控除</td>
						  <td class="text-end">1万円</td>
						  <td class="b-none">〇</td>
					  </tr>
						<tr>
						  <td rowspan="2" class="text-start ps-3">ひとり親控除</td>
						  <td>父</td>
						  <td>&nbsp;</td>
						  <td class="text-end">1万円</td>
						  <td class="b-none">〇</td>
					  </tr>
						<tr>
						  <td>母</td>
						  <td>&nbsp;</td>
						  <td class="text-end">5万円</td>
						  <td class="b-none">〇</td>
					  </tr>
						<tr>
						  <td colspan="3" class="text-start ps-3">勤労学生控除</td>
						  <td class="text-end">1万円</td>
						  <td class="b-none">〇</td>
					  </tr>
						<tr>
						  <td rowspan="7" class="text-start ps-3">配偶者控除</td>
						  <td colspan="2" class="text-center bg-grey">本人の合計所得金額</td>
						  <td class="diag-auto" data-length-mm="22" data-height-mm="10" 
						      style="--diag-width:1px; --diag-color:#000;">&nbsp;			  
							</td>
						  <td class="b-none">&nbsp;</td>
					  </tr>
						<tr>
						  <td rowspan="3">一般</td>
						  <td class="text-end">900万円以下</td>
						  <td class="text-end">5万円</td>
						  <td class="b-none">&nbsp;</td>
					  </tr>
						<tr>
						  <td class="text-end">900万円超950万円以下</td>
						  <td class="text-end">4万円</td>
						  <td class="b-none">〇</td>
					  </tr>
						<tr>
						  <td class="text-end">950万円超1,000万円以下</td>
						  <td class="text-end">2万円</td>
						  <td class="b-none">&nbsp;</td>
					  </tr>
						<tr>
						  <td rowspan="3">老人</td>
						  <td class="text-end">900万円以下</td>
						  <td class="text-end">10万円</td>
						  <td class="b-none">〇</td>
					  </tr>
						<tr>
						  <td class="text-end">900万円超950万円以下</td>
						  <td class="text-end">6万円</td>
						  <td class="b-none">&nbsp;</td>
					  </tr>
						<tr>
						  <td class="text-end">950万円超1,000万円以下</td>
						  <td class="text-end">3万円</td>
						  <td class="b-none">&nbsp;</td>
					  </tr>
						<tr>
						  <td rowspan="4" class="text-start ps-3">扶養控除</td>
						  <td colspan="2" class="text-start">一般の控除対象扶養親族</td>
						  <td class="text-end">5万円</td>
						  <td class="b-none">3人</td>
					  </tr>
						<tr>
						  <td colspan="2" class="text-start">特定扶養親族</td>
						  <td class="text-end">18万円</td>
						  <td class="b-none">1人</td>
					  </tr>
						<tr>
						  <td rowspan="2" class="text-start">老人扶養<br>親族</td>
						  <td class="text-start">同居老親等以外の者</td>
						  <td class="text-end">10万円</td>
						  <td class="b-none">2人</td>
					  </tr>
						<tr>
						  <td class="text-start">同居老親等</td>
						  <td class="text-end">13万円</td>
						  <td class="b-none">1人</td>
					  </tr>
						<tr>
						  <td rowspan="3" class="text-start ps-3">基礎控除</td>
						  <td colspan="2" class="bg-grey">本人の合計所得金額</td>
						  <td class="diag-auto" data-length-mm="22" data-height-mm="10" 
						      style="--diag-width:1px; --diag-color:#000;">&nbsp;			  
			        </td>
						  <td class="b-none">&nbsp;</td>
					  </tr>
						<tr>
						  <td colspan="2" class="text-end">2,500万円以下</td>
						  <td class="text-end">5万円</td>
						  <td class="b-none">〇</td>
					  </tr>
						<tr>
						  <td colspan="2" class="text-end">2,500万円超</td>
						  <td class="text-end">0万円</td>
						  <td class="b-none">&nbsp;</td>
					  </tr>
						<tr>
						  <td colspan="6" class="text-start b-l-no b-r-no b-none"><h13>※これらは調整控除における人的控除額の差であり、実際の控除差とは異なる。</h13></td>
					  </tr>
	      	</tbody>
	    	</table>		  
        </td>
      </tr>
    </table><!-- /左右２カラム -->
    </div><!-- 本文終り -->
      <div class="page-footer">
        <div class="footer-inner">
          <table class="table b-none no-overlap mb-0">
            <tr>
              <td><h14u>６ページ</h14u></td>
            </tr>
          </table>
        </div>
      </div>
  </div><!-- /.page-frame -->
@endsection