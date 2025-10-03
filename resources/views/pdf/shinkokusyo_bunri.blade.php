@extends('pdf.layouts.print')
@section('title','確定申告書（分離課税用）- 指定都市（DOM再現）')

@section('head')
<style>
    /* A4横／余白なし（印刷時） */
    @page { size: A4 landscape; margin: 0; }
    html, body { height: 100%; margin: 0; background: #fff; }
     /* 印刷時の色/線の再現性を高める */
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    /* mm指定の罫線・幅を安定化（border込みで計算） */
    *, *::before, *::after { box-sizing: border-box; }

    /* 見出し高さ（重なり防止用／必要に応じて 8〜12mm で調整） */
    :root { --header-h: 8mm; }

    /* 用紙キャンバス */
    .sheet {
      width: 297mm;
      height: 210mm;
      margin: 0 auto;
      position: relative;
      background: #fff;
    }

    /* 見出し（テーブルで2カラム＆ボトム揃え／枠は透明） */
    .header-table {
      position: absolute;
      left: 18mm;
      top: 5mm;                              /* 用紙上端からの開始位置 */
      width: 261mm;                          /* 下の表と同じ幅に固定 */
      height: var(--header-h, 8mm);          /* ヘッダー高さ */
      border-collapse: collapse;
      table-layout: fixed;
      z-index: 10;
    }
    .header-table, .header-table td {
      border: 0;                             /* ボーダー非表示（透明） */
      border-color: transparent;
    }
    .header-table td {
      padding: 0;
      vertical-align: bottom;                /* ★ 下端で揃える：見た目が最も安定 */
      line-height: 1;
      white-space: nowrap;
      font-family: ipaexg, "DejaVu Sans", sans-serif;
    }
    .header-table .title-cell { font-size: 18px; }
    .header-table .tag-cell   { font-size: 14px; text-align: right; padding-right: 50px; }
     

    /* 表（Excel→DOM再現） */
    .table {
      position: absolute;
      left: 0mm;         /* 必要に応じて調整 */
      top: calc(var(--header-h, 8mm) + 8mm);  /* 見出し高さ + 余白（重なる場合は値を増やす/詰める） */
      border-collapse: collapse;
      border-spacing: 0;
      z-index: 1;
      width: 261mm;       /* 見出しと同じ幅に寄せる */
      font-size: 14px;
	    border: 0.6mm solid #000;
    }

    td, th {
      border: 0.3mm solid #000;   /* 罫線太さは要件に応じ調整可 */
      padding: 0mm 0mm;           /* Excel見た目に合わせて微調整 */
      font-size: 14px;            /* おおむね9pt相当。必要に応じて調整 */
      line-height: 1.2;
      vertical-align: middle;
    }
    .center { text-align: center; }
    .right  { text-align: right;  }
	  
/*セルの罫線を太くするためのユーティリティ */
    .bl-strong { border-left: 0.6mm solid #000 !important; }
    .br-strong { border-right: 0.6mm solid #000 !important; } 
	  .bt-strong { border-top: 0.6mm solid #000 !important; }
    .bb-strong { border-bottom: 0.6mm solid #000 !important; }
	  
/* ▼ 行全体（tr）の罫線を太くするために付けるバージョン：セル(td/th)へ適用して安定化 */
    .bt-row > td, .bt-row > th { border-top: 0.6mm solid #000 !important; }
    .bb-row > td, .bb-row > th { border-bottom: 0.6mm solid #000 !important; }

 /* ▼ 左パディング用ユーティリティ（Bootstrapのps-*が効かないため自前で上書き） */
    .pl-1 { padding-left: 1mm !important; }
    .pl-2 { padding-left: 2mm !important; }
    .pl-3 { padding-left: 3mm !important; }
  </style>
@endsection  
@section('content')
　<div class="sheet">
    <!-- 見出し（テーブル2列：左=タイトル／右=タグ。下端揃え） -->
    <table class="header-table" aria-hidden="true">
      <colgroup>
        <col />                 <!-- 左カラム：可変 -->
        <col style="width: 60mm;" />  <!-- 右カラム：固定幅で右寄せ -->
      </colgroup>
      <tr>
        <td class="title-cell"><strong>確定申告書(分離課税用)</strong></td>
        <td class="tag-cell"><strong>指定都市</strong></td>
      </tr>
    </table>

    <!-- 表本体（Excel 指定都市シートに合わせた構成） -->
     <table class="table" align="center" cellpadding="0" cellspacing="0">
      <tr>
        <!-- 左側（区分）ブロック -->
        <td colspan="4" rowspan="2" align="center" style="font-size:18px;" class="br-strong bb-strong"><strong>区　　分</strong></td>
        <!-- ② の“境界”= 区分ブロックの直後に来るセル（＝令和6年度ブロックの先頭）に太い左枠を付与 -->
        <td colspan="2" align="center" valign="middle" style="height: 5.5mm;"><strong>令和６年度</strong></td>
         
        <td colspan="2" align="center"><strong>令和７年度</strong></td>
        <td rowspan="2" align="center" class="bl-strong bb-strong" style="width: 21mm;height: 5.5mm;"><strong>所得税</strong></td>
        <td colspan="2" align="center"><strong>住民税</strong></td>
        <td rowspan="2" align="center" style="width: 44mm;" class="bl-strong bb-strong">備　考</td>
      </tr>
      <tr style="height: 5.5mm;">
        <!-- 2行目でも、令和6年度ブロックの最初のセルに左太線 -->
        <td align="center" valign="middle" class="bl-strong bb-strong" style="width: 24.5mm;"><strong>所得税</strong></td>         
        <td align="center" style="width: 24.5mm;" class="bb-strong"><strong>住民税</strong></td>
        <td align="center" style="width: 24.5mm;" class="bb-strong"><strong>所得税</strong></td>
        <td align="center" style="width: 24.5mm;" class="bb-strong"><strong>住民税</strong></td>
        <td align="center" style="width: 21mm;" class="bb-strong"><strong style="font-size:12px;">区市町村民税</strong></td>
        <td align="center" style="width: 21mm;" class="bb-strong"><strong style="font-size:12px;">都道府県民税</strong></td>
      </tr>

      <tr style="height: 5.1mm;">
        <td rowspan="11" align="center" style="width: 10mm;font-size:18px; border: 2px solid #000 !important;"><strong>所<br><br>得<br><br>金<br><br>額</strong></td>
        <td rowspan="9" align="center" style="width: 8mm;">分<br><br>離<br><br>課<br><br>税</td>
        <td rowspan="2" align="center" style="width: 8mm;">短<br>期</td>
        <td align="center" style="width: 30mm;height: 5mm;">一　般　分</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
        <td align="center" class="bl-strong"><strong>30％</strong></td>
        <td align="center"><strong>5.40％</strong></td>
        <td align="center"><strong>3.60％</strong></td>
        <td rowspan="3" class="bl-strong"></td>
      </tr>
      <tr style="height: 5.1mm;">
        <td align="center">軽　減　分</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
        <td align="center" class="bl-strong"><strong>15％</strong></td>
        <td align="center"><strong>3％</strong></td>
        <td align="center"><strong>2％</strong></td>
       </tr>

      <tr style="height: 5.1mm;">
        <td rowspan="3" align="center">長<br><br>期</td>
        <td align="center">一　般　分</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
        <td align="center" class="bl-strong"><strong>15％</strong></td>
        <td align="center"><strong>3％</strong></td>
        <td align="center"><strong>2％</strong></td>
       </tr>
      <tr style="height: 10mm;">
        <td align="center">特　定　分</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
        <td align="center" class="bl-strong"><strong>10%<br>15%</strong></td>
        <td align="center"><strong>2.4％<br>3％</strong></td>
        <td align="center"><strong>1.6％<br>2％</strong></td>
        <td class="bl-strong pl-1" style="font-size:11px;color:#333333;">2,000万円以下の部分<br>2,000万円超の部分</td>
      </tr>
      <tr style="height: 10mm;">
        <td align="center">軽　課　分</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
        <td align="center" class="bl-strong"><strong>10%<br>15%</strong></td>
        <td align="center"><strong>2.4％<br>3％</strong></td>
        <td align="center"><strong>1.6％<br>2％</strong></td>
        <td class="bl-strong pl-1" style="font-size:11px;color:#333333;">6,000万円以下の部分<br>6,000万円超の部分</td>
      </tr>

      <tr style="height: 5.1mm;">
        <td colspan="2" align="center">一般株式等の譲渡</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
        <td align="center" class="bl-strong"><strong>15％</strong></td>
        <td align="center"><strong>3％</strong></td>
        <td align="center"><strong>2％</strong></td>
        <td rowspan="25" class="bl-strong pl-1" style="vertical-align: top;"> <br>
          <br>
          <br>
          <br>
        <span style="font-size:12px;color:#333333;">5分5乗方式</span><br><span style="font-size:10px;color:#333333;">(退職金－退職所得控除額)÷２</span></td>
      </tr>
      <tr style="height: 5.1mm;">
        <td colspan="2" align="center">上場株式等の譲渡</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
        <td align="center" class="bl-strong"><strong>15％</strong></td>
        <td align="center"><strong>3％</strong></td>
        <td align="center"><strong>2％</strong></td>
      </tr>
      <tr style="height: 5.1mm;">
        <td colspan="2" align="center">上場株式等の配当等</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
        <td align="center" class="bl-strong"><strong>15％</strong></td>
        <td align="center"><strong>3％</strong></td>
        <td align="center"><strong>2％</strong></td>
      </tr>
      <tr style="height: 5.1mm;">
        <td colspan="2" align="center">先　　物　　取　　引</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
        <td align="center" class="bl-strong"><strong>15％</strong></td>
        <td align="center"><strong>3％</strong></td>
        <td align="center"><strong>2％</strong></td>
      </tr>
      <tr style="height: 5.1mm;">
        <td colspan="3" align="center">山　　　　林</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
        <td align="center" class="bl-strong"><strong>累進税率</strong></td>
        <td align="center"><strong>6％</strong></td>
        <td align="center"><strong>4％</strong></td>
      </tr>
      <tr class="bb-row" style="height: 5.1mm;">
        <td colspan="3" align="center">退　　　　職</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
        <td align="center" class="bl-strong"><strong>累進税率</strong></td>
        <td align="center"><strong>6％</strong></td>
        <td align="center"><strong>4％</strong></td>
      </tr>

      <tr style="height: 5.1mm;">
        <td rowspan="19" align="center" style="font-size:18px; border: 2px solid #000 !important;"><strong>税<br><br>金<br><br>の<br><br>計<br><br>算</strong></td>
        <td colspan="3" align="center" style="height: 5.3mm;">総 合 課 税 の 合 計 額</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
        <td rowspan="2" class="bl-strong bb-strong"></td>
        <td rowspan="2" class="bb-strong"></td>
        <td rowspan="2" class="bb-strong"></td>
      </tr>
      <tr class="bb-row" style="height: 501mm;">
        <td colspan="3" align="center">所得から差し引かれる金額</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
      </tr>

      <tr>
        <td rowspan="8" align="center" class="bb-row bb-strong">課<br>税<br>所<br>得<br>金<br>額</td>
        <td colspan="2" class="pl-1">総合課税</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
        <td rowspan="8" class="bl-strong bb-strong"></td>
        <td rowspan="8" class="bb-strong"></td>
        <td rowspan="8" class="bb-strong"></td>
      </tr>
      <tr style="height: 5.1mm;">
        <td colspan="2" class="pl-1">短期譲渡</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
      </tr>
      <tr style="height: 5.1mm;">
        <td colspan="2" class="pl-1">長期譲渡</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
      </tr>
      <tr style="height: 5.1mm;">
        <td colspan="2" class="pl-1">一般・上場株式の譲渡</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
      </tr>
      <tr style="height: 5.1mm;">
        <td colspan="2" class="pl-1">上場株式の配当等</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
      </tr>
      <tr style="height: 5.1mm;">
        <td colspan="2" class="pl-1">先物取引</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
      </tr>
      <tr style="height: 5.1mm;">
        <td colspan="2" class="pl-1">山林</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
      </tr>
      <tr class="bb-row" style="height: 5.1mm;">
        <td colspan="2" class="pl-1">退職</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
      </tr>

      <tr>
        <td rowspan="8" align="center" class="bb-strong">税<br><br><br>額</td>
        <td colspan="2" class="pl-1" style="height: 5.1mm;">総合課税</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
        <td rowspan="8" align="center" class="bl-strong" style="vertical-align: top;"><strong>累進税率</strong></td>
        <td rowspan="8" align="center" style="vertical-align: top;"><strong>6％</strong></td>
        <td rowspan="8" align="center" style="vertical-align: top;"><strong>4％</strong></td>
      </tr>
      <tr style="height: 5.1mm;">
        <td colspan="2" class="pl-1">短期譲渡</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
      </tr>
      <tr style="height: 5.1mm;">
        <td colspan="2" class="pl-1">長期譲渡</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
      </tr>
      <tr style="height: 5.1mm;">
        <td colspan="2" class="pl-1">一般・上場株式の譲渡</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
      </tr>
      <tr style="height: 5.1mm;">
        <td colspan="2" class="pl-1">上場株式の配当等</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
      </tr>
      <tr style="height: 5.1mm;">
        <td colspan="2" class="pl-1">先物取引</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
      </tr>
      <tr style="height: 5.1mm;">
        <td colspan="2" class="pl-1">山林</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
      </tr>
      <tr class="bb-row" style="height: 5.1mm;">
        <td colspan="2" class="pl-1">退職</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
      </tr>

      <tr style="height: 5.1mm;">
        <td colspan="3" align="center">税額合計</td>
        <td class="bl-strong"></td><td></td><td></td><td></td>
        <td align="center" class="bl-strong">―</td><td align="center">―</td><td align="center">―</td>
      </tr>
    </table>
  </div>
@endsection