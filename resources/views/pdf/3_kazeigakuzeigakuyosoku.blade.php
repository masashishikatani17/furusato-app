{{-- resources/views/pdf/3_kazeigakuzeigakuyosoku.blade.php --}}
@extends('pdf.layouts.print')

@section('title','令和7年の課税所得金額・税額の予測')

@section('head')
<style>
      /* A4横 + 余白（上 右 下 左）：全帳票で統一 */
        @page { size: A4 landscape; margin: 8mm 4mm 8mm 12mm; }
        
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

   /* === 斜線（右上→左下）：DomPDF対応（JS不要） ===
     ※疑似要素で被せる方式は DomPDF で点線/混色になり得るため、背景として描く
  */
  td.diag-auto, th.diag-auto{
    background-image: linear-gradient(to bottom right,
      transparent 49%,
      var(--diag-color, #000) 49%,
      var(--diag-color, #000) 51%,
      transparent 51%);
    background-repeat: no-repeat;
    background-size: 100% 100%;
  }
</style>
@endsection

@section('content')
  <div class="page-frame text-center"><!-- ここが実効幅281mmの中央寄せコンテナ -->
    <!-- タイトル行 -->
    <table class="table b-none no-overlap mt-10 mb-2"
           style="width: 230mm; table-layout: fixed; border-collapse: collapse;
                  margin: 0 auto; clear:both;">
      <tr>
        <td class="text-center"><h18>令和7年の課税所得金額・税額の予測</h18></td>
      </tr>
      <tr>
        <td class="text-start"><h16>★上限額まで寄附した場合</h16></td>
      </tr>
    </table>

    <div class="table-frame">
      <table class="table table-compact-p text-center mb-0"
             style="width:235mm; font-size:15px; line-height:1.6; margin: 0 auto;">
        <colgroup>
          <col style="width:10mm">
          <col style="width:49mm">
          <col style="width:30mm">
          <col style="width:30mm">
          <col style="width:29mm">
          <col style="width:29mm">
          <col style="width:29mm">
          <col style="width:29mm">
        </colgroup>
        <tbody>
          <tr style="line-height: 27px;">
            <td colspan="2" rowspan="3" class="b-b-strong b-r-strong"><h14u>項　　目</h14u></td>
            <td colspan="2" class="b-r-strong"><h18u>課税所得金額</h18u></td>
            <td colspan="4"><h18u>税　　額</h18u></td>
          </tr>
          <tr>
            <td rowspan="2" class="b-b-strong">所得税</td>
            <td rowspan="2" class="b-b-strong b-r-strong">住民税</td>
            <td rowspan="2" class="b-b-strong">所得税</td>
            <td colspan="3">住民税</td>
          </tr>
          <tr>
            <td class="b-b-strong">市区町村民税</td>
            <td class="b-b-strong">都道府県民税</td>
            <td class="b-b-strong">合　計</td>
          </tr>

          <tr>
            <td colspan="2" class="text-start b-r-strong b-b-no">総合課税</td>
            <td class="text-end">40,050,000</td>
            <td class="text-end b-r-strong">41,430,000</td>
            <td class="text-end">13,226,500</td>
            <td class="text-end">2,485,800</td>
            <td class="text-end">1,657,200</td>
            <td class="text-end">4,143,000</td>
          </tr>
          <tr>
            <td colspan="2" class="text-start b-r-strong b-y-no">短期譲渡</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end b-r-strong">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
          </tr>
          <tr>
            <td colspan="2" class="text-start b-r-strong b-y-no">長期譲渡</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end b-r-strong">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
          </tr>
          <tr>
            <td colspan="2" class="text-start b-r-strong b-y-no">一般・上場株式等の譲渡</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end b-r-strong">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
          </tr>
          <tr>
            <td colspan="2" class="text-start b-r-strong b-y-no">上場株式の配当等</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end b-r-strong">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
          </tr>
          <tr>
            <td colspan="2" class="text-start b-r-strong b-y-no">先物取引</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end b-r-strong">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
          </tr>
          <tr>
            <td colspan="2" class="text-start b-r-strong b-y-no">山林</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end b-r-strong">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
          </tr>
          <tr>
            <td colspan="2" class="text-start b-r-strong b-y-no">退職</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end b-r-strong">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
          </tr>
          <tr>
            <td colspan="2" class="b-b-strong b-r-strong">合　　計</td>
            <td colspan="2" class="diag-auto b-b-strong b-r-strong"
                style="--diag-width:1px; --diag-color:#000;">&nbsp;</td>
            <td class="text-end b-b-strong">&nbsp;</td>
            <td class="text-end b-b-strong">&nbsp;</td>
            <td class="text-end b-b-strong">&nbsp;</td>
            <td class="text-end b-b-strong">&nbsp;</td>
          </tr>

          <tr>
            <td width="28" rowspan="6"><h14u>税<br>額<br>控<br>除</h14u></td>
            <td class="text-start b-r-strong">調整控除</td>
            <td colspan="2" class="diag-auto b-r-strong" style="--diag-width:1px; --diag-color:#000;">&nbsp;</td>
            <td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td>
          </tr>
          <tr>
            <td class="text-start b-r-strong">配当控除</td>
            <td colspan="2" class="diag-auto b-r-strong" style="--diag-width:1px; --diag-color:#000;">&nbsp;</td>
            <td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td>
          </tr>
          <tr>
            <td class="text-start b-r-strong">住宅借入金等特別控除</td>
            <td colspan="2" class="diag-auto b-r-strong" style="--diag-width:1px; --diag-color:#000;">&nbsp;</td>
            <td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td>
          </tr>
          <tr>
            <td class="text-start b-r-strong">政党等寄附金等特別控除</td>
            <td colspan="2" class="diag-auto b-r-strong" style="--diag-width:1px; --diag-color:#000;">&nbsp;</td>
            <td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td>
          </tr>
          <tr>
            <td class="text-start b-r-strong">寄附金税額控除</td>
            <td colspan="2" class="diag-auto b-r-strong" style="--diag-width:1px; --diag-color:#000;">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end b-strong"><hb>0</hb></td>
            <td class="text-end b-strong"><hb>0</hb></td>
            <td class="text-end b-t-strong b-b-strong"><hb>0</hb></td>
          </tr>
          <tr>
            <td class="text-start b-r-strong">災害減免額</td>
            <td colspan="2" class="diag-auto b-r-strong" style="--diag-width:1px; --diag-color:#000;">&nbsp;</td>
            <td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td>
          </tr>

          <tr>
            <td rowspan="3"><h14u>税<br>額</h14u></td>
            <td class="text-start b-r-strong">差引所得税額（所得割額）</td>
            <td colspan="2" class="diag-auto b-r-strong" style="--diag-width:1px; --diag-color:#000;">&nbsp;</td>
            <td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td>
          </tr>
          <tr>
            <td class="text-start b-r-strong">復興特別所得税額</td>
            <td colspan="2" class="diag-auto b-r-strong" style="--diag-width:1px; --diag-color:#000;">&nbsp;</td>
            <td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td>
          </tr>
          <tr>
            <td class="b-r-strong">合　　計</td>
            <td colspan="2" class="diag-auto b-r-strong" style="--diag-width:1px; --diag-color:#000;">&nbsp;</td>
            <td class="text-end b-t-strong b-l-strong b-r-strong">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
          </tr>
        </tbody>
      </table>
    </div>

    <table class="table b-none no-overlap mt-2"
           style="width: 225mm; table-layout: fixed; border-collapse: collapse;
                  margin: 0 auto; clear:both;">
      <tr>
        <td class="text-start"><h13>
          ※寄附金税額控除の計算過程は「所得税・住民税の軽減額の計算過程(4ページ)」にあります。<br>
          ※寄附金の上限額を計算することが目的なので納付税額までは計算しておりません。
        </h13></td>
      </tr>
    </table>
      <div class="page-footer">
        <div class="footer-inner">
          <table class="table b-none no-overlap mb-0"
                 style="width: 248mm; table-layout: fixed; border-collapse: collapse; margin: 0 auto; clear:both;">
            <tr>
              <td class="text-end"><h14u>３ページ</h14u></td>
            </tr>
          </table>
        </div>
      </div>
  </div><!-- /.page-frame -->
@endsection


