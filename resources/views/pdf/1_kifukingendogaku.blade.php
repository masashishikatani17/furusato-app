{{-- resources/views/pdf/1_kifukingendogaku.blade.php --}}
@extends('pdf.layouts.print')

@section('title','住民税の節税額')

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
        
         .cover-frame{ width: 100%; }
        .cover-center{ width: 264mm; margin: 0 auto; }

        /* ★表紙は必ず横書き：共通CSSの縦書き(writing-mode)や回転(transform)を打ち消す */
        html, body,
        section.sheet,
        .cover, .cover-frame,
        table, tbody, tr, td, th,
         {
          writing-mode: horizontal-tb !important;
          text-orientation: mixed !important;
          transform: none !important;
        } 
        
      /* ページ右下の固定フッター（下から12mm） */
        .page-footer{
          position: fixed;
          left: 0;
          right: 0;
          bottom: 12mm;          /* ← ここが「下から」 */
          width: 100%;
        }
        
        
      /* === 斜線（左上→右下）：DomPDF対応（JS不要） === */
        td.diag-auto, th.diag-auto {
          background-image: linear-gradient(to bottom left,
            transparent 49.4%,
            #000 49.6%,
            #000 50.4%,
            transparent 50.6%);
          background-repeat: no-repeat;
          background-size: 100% 100%;
        }
</style>
@endsection

@section('content')
  <div class="page-frame text-center"><!-- ここが実効幅281mmの中央寄せコンテナ -->
    <table align="center" class="table b-none no-overlap mt-10"
           style="width: 232mm; table-layout: fixed; border-collapse: collapse; clear:both;">
      <tr>
        <td class="text-start">
          <h15>　令和７年度のふるさと納税による寄附金上限額は次の通りです。これ以上の額を寄附すると持ち出しとなります。具体的には<br>「<strong>寄附金額別損得シミュレーション</strong>」(５ページ)をご覧ください。
        </td>
      </tr>
    </table>

    <table class="table b-none no-overlap mt-5 mb-2"
           style="width: 248mm; table-layout:fixed; border-collapse:collapse; margin:0 auto;">
      <tr>
        <td class="text-start"><h18>■ふるさと納税の寄附金上限額と残りの寄附可能額</h18></td>
      </tr>
    </table>
      <table align="center" class="table table-compact-p text-center no-overlap b-y-strong b-x-strong"
         style="width: 248mm;line-height:1.8; outline:2px solid #000; outline-offset:-2px;">
        
          <colgroup>
            <col style="width:61mm">
            <col style="width:38mm">
            <col style="width:37mm">
            <col style="width:37mm">
            <col style="width:37mm">
            <col style="width:38mm">
          </colgroup>
        <tbody>  
          <tr>
            <td rowspan="2" class="diag-auto" data-length-mm="64" data-height-mm="21"
                style="--diag-width:1px; --diag-color:#000;">&nbsp;</td>
            <td rowspan="2" class="text-center"><h18u>寄附金額</h18u></td>
            <td colspan="3" class="text-center"><h18u>減税額</h18u></td>
            <td rowspan="2" class="text-center"><h18u>負担額</h18u></td>
          </tr>
          <tr>
            <td class="text-center"><h18u>所得税</h18u></td>
            <td class="text-center"><h18u>住民税</h18u></td>
            <td class="text-center"><h18u>合　計</h18u></td>
          </tr>
          <tr>
            <td class="text-start ps-1 b-b-no"><h18u>&nbsp;寄附金上限額(※)</h18u></td>
            <td class="text-end b-b-no"><h18u>円</h18u></td>
            <td class="text-end b-b-no"><h18u>円</h18u></td>
            <td class="text-end b-b-no"><h18u>円</h18u></td>
            <td class="text-end b-b-no"><h18u>円</h18u></td>
            <td class="text-end b-b-no"><h18u>円</h18u></td>
          </tr>
          <tr>
            <td class="text-start ps-1 b-t-no"><h18u>&nbsp;今までに寄附した額</h18u></td>
            <td class="text-end b-t-no"><h18u>円</h18u></td>
            <td class="text-end b-t-no"><h18u>円</h18u></td>
            <td class="text-end b-t-no"><h18u>円</h18u></td>
            <td class="text-end b-t-no"><h18u>円</h18u></td>
            <td class="text-end b-t-no"><h18u>円</h18u></td>
          </tr>
          <tr>
            <td class="text-start ps-1"><h18u>&nbsp;差額(残りの寄附可能額)</h18u></td>
            <td class="text-end"><h18u>円</h18u></td>
            <td class="text-end"><h18u>円</h18u></td>
            <td class="text-end"><h18u>円</h18u></td>
            <td class="text-end"><h18u>円</h18u></td>
            <td class="text-end"><h18u>円</h18u></td>
          </tr>
        </tbody>
      </table>
   <h14u class="text-start ms-12 mt-1">&nbsp;&nbsp;※寄附金上限額は1,000円未満を切り捨てています。
   </h14u>
    <!-- ★ flex をやめて、横並びはレイアウト用テーブルで固定（DomPDF安定） -->
      <table class="table b-none no-overlap mt-5"
             style="margin: 0 auto; width:248mm; table-layout:fixed; border-collapse:collapse;">
        <colgroup>
          <col style="width:162mm;">
          <col style="width:10mm;">
          <col style="width:76mm;">
        </colgroup>
        <tr>
          <!-- 左側（162mm） -->
          <td class="b-none" style="vertical-align:top;">
          <table class="table b-none no-overlap ps-5"
                 style="width: 162mm; table-layout:fixed; border-collapse:collapse; margin:0 auto;">
            <tr>
              <td class="text-start"><h18>■今までに寄附した額の内訳（令和〇年）</h18></td>
            </tr>
          </table>
  
          <table class="table table-compact-p b-none no-overlap table-162mm" 
          style="font-size:13px;line-height:1.3;">
            <colgroup>
              <col style="width:67mm">
              <col style="width:19mm">
              <col style="width:19mm">
              <col style="width:19mm">
              <col style="width:19mm">
              <col style="width:19mm">
            </colgroup>
            <tbody>
              <tr>
                <td rowspan="3" class="bg-grey">寄附先</td>
                <td colspan="3" rowspan="2" class="bg-grey">所得税</td>
                <td colspan="2" class="bg-grey">住民税</td>
              </tr>
              <tr class="bg-grey">
                <td>市区町村</td>
                <td>都道府県</td>
              </tr>
              <tr class="bg-grey">
                <td>所得控除</td>
                <td>税額控除</td>
                <td>合　計</td>
                <td>税額控除</td>
                <td>税額控除</td>
              </tr>
              <tr class="bg-cream">
                <td class="text-start"><hb>都道府県・市町村（ふるさと納税）</hb></td>
                <td class="text-end"><hb></hb></td>
                <td>－</td>
                <td class="text-end"><hb></hb></td>
                <td class="text-end"><hb></hb></td>
                <td class="text-end"><hb></hb></td>
              </tr>
              <tr>
                <td class="text-start bg-grey">住所地の共同募金、日赤等</td>
                <td class="text-end"></td>
                <td>－</td>
                <td class="text-end"></td>
                <td class="text-end"></td>
                <td class="text-end"></td>
              </tr>
              <tr>
                <td class="text-start bg-grey">政党等</td>
                <td class="text-end"></td>
                <td class="text-end"></td>
                <td class="text-end"></td>
                <td>－</td>
                <td>－</td>
              </tr>
              <tr>
                <td class="text-start bg-grey">認定ＮＰＯ法人等</td>
                <td class="text-end"></td>
                <td class="text-end"></td>
                <td class="text-end"></td>
                <td class="text-end"></td>
                <td class="text-end"></td>
              </tr>
              <tr>
                <td class="text-start bg-grey">公益社団法人等</td>
                <td class="text-end"></td>
                <td class="text-end"></td>
                <td class="text-end"></td>
                <td class="text-end"></td>
                <td class="text-end"></td>
              </tr>
              <tr>
                <td class="text-start bg-grey">国</td>
                <td class="text-end"></td>
                <td>－</td>
                <td class="text-end"></td>
                <td>－</td>
                <td>－</td>
              </tr>
              <tr>
                <td class="text-start bg-grey">その他</td>
                <td class="text-end"></td>
                <td>－</td>
                <td class="text-end"></td>
                <td class="text-end"></td>
                <td class="text-end"></td>
              </tr>
              <tr class="bg-cream">
                <td><hb>合　　　計</hb></td>
                <td class="text-end"><hb></hb></td>
                <td class="text-end"><hb></hb></td>
                <td class="text-end"><hb></hb></td>
                <td class="text-end"><hb></hb></td>
                <td class="text-end"><hb></hb></td>
              </tr>
            </tbody>
          </table>
          <h14u class="text-start mt-1">※住民税の特例控除はふるさと納税のみが対象です。</h14u>
          </td>
  
          <!-- gap（10mm） -->
          <td class="b-none" style="padding:0;">&nbsp;</td>
  
          <!-- 右側（76mm） -->
          <td class="b-none" style="vertical-align:top; padding:0;">
          <table class="table b-none no-overlap mb-0"
                 style="width: 76mm; clear:both;">
            <tr>
              <td><h16>＜寄附金の申告方法＞</h16></td>
            </tr>
          </table>
  
          <table class="table table-compact-p text-center no-overlap table-76mm mt-1" style="font-size:13px;line-height:1.3;">
            <colgroup>
              <col style="width:19mm">
              <col style="width:19mm">
              <col style="width:19mm">
              <col style="width:19mm">
            </colgroup>
            <tbody>
              <tr>
                <td colspan="2" rowspan="2" class="bg-grey">所得税</td>
                <td colspan="2" class="bg-grey">住民税</td>
              </tr>
              <tr class="bg-grey">
                <td>市区町村</td>
                <td>都道府県</td>
              </tr>
              <tr class="bg-grey">
                <td>所得控除</td>
                <td>税額控除</td>
                <td>税額控除</td>
                <td>税額控除</td>
              </tr>
              <tr>
                <td class="bg-grey">○</td>
                <td class="bg-grey">－</td>
                <td class="bg-grey">○</td>
                <td class="bg-grey">○</td>
              </tr>
              <tr>
                <td class="bg-grey">○</td>
                <td class="bg-grey">－</td>
                <td class="bg-grey">○(※)</td>
                <td class="bg-grey">○(※)</td>
              </tr>
              <tr>
                <td class="bg-grey">○</td>
                <td class="bg-grey">○</td>
                <td class="bg-grey">－</td>
                <td class="bg-grey">－</td>
              </tr>
              <tr>
                <td class="bg-grey">○</td>
                <td class="bg-grey">○</td>
                <td class="bg-grey">○(※)</td>
                <td class="bg-grey">○(※)</td>
              </tr>
              <tr>
                <td class="bg-grey">○</td>
                <td class="bg-grey">○</td>
                <td class="bg-grey">○(※)</td>
                <td class="bg-grey">○(※)</td>
              </tr>
              <tr>
                <td class="bg-grey">○</td>
                <td class="bg-grey">－</td>
                <td class="bg-grey">－</td>
                <td class="bg-grey">－</td>
              </tr>
              <tr>
                <td class="bg-grey">○</td>
                <td class="bg-grey">－</td>
                <td class="bg-grey">○(※)</td>
                <td class="bg-grey">○(※)</td>
              </tr>
            </tbody>
          </table>
  
          <table class="table b-none no-overlap mb-0"
                 style="width: 76mm; clear:both;">
            <tr>
              <td class="text-start">
                <h14u>※都道府県、市区町村が条例で指定したもの<br>&nbsp;&nbsp;に限る。</h14u>
              </td>
            </tr>
          </table>
          </td>
        </tr>
      </table><!-- 左右ブロック横並び終り -->
    
      <div class="page-footer">
        <div class="footer-inner">
          <table class="table b-none no-overlap mb-0"
                 style="margin: 0 auto; width: 248mm; table-layout: fixed; border-collapse: collapse; clear:both;">
            <tr>
              <td class="text-end ps-0"><h14u>1ページ</h14u></td>
            </tr>
          </table>
        </div>
      </div>
    
  </div><!-- /.page-frame -->
@endsection
