{{-- resources/views/pdf/3_kazeigakuzeigakuyosoku.blade.php --}}
@extends('pdf.layouts.print')

@section('title','令和7年の課税所得金額・税額の予測')

@section('head')
<style>
      /* A4横 + 余白（上 右 下 左）：全帳票で統一 */
        @page { size: A4 landscape; margin: 10mm 6mm 10mm 6mm; }
        
        /* 中央寄せしたい場合だけ（任意） */
        .page-frame{
          width: calc(297mm - 12mm); /* 例：左右合計12mm を引く */
          margin: 0 auto;
          text-align: center; /* ← inline-block を中央に寄せるため */
          padding-bottom: 18mm; 
        }
        
        /* テーブルの基本（これだけでよいことが多い） */
        table{ border-collapse: collapse; table-layout: fixed; }
        
      
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
  @php
    $r = is_array($report3_curr ?? null) ? $report3_curr : [];
    $taxable = is_array($r['taxable'] ?? null) ? $r['taxable'] : [];
    $zeigaku = is_array($r['zeigaku'] ?? null) ? $r['zeigaku'] : [];
    $itaxZ = is_array($zeigaku['itax'] ?? null) ? $zeigaku['itax'] : [];
    $juminZ = is_array($zeigaku['jumin'] ?? null) ? $zeigaku['jumin'] : [];
    $credits = is_array($r['credits'] ?? null) ? $r['credits'] : [];
    $final = is_array($r['final_tax'] ?? null) ? $r['final_tax'] : [];
    $finalJ = is_array($final['gokei_jumin'] ?? null) ? $final['gokei_jumin'] : ['muni'=>0,'pref'=>0,'total'=>0];

    $fmt = static fn($v) => number_format((int)($v ?? 0));
    $pick = static function(array $src, string $key, string $col) {
      $row = $src[$key] ?? null;
      if (!is_array($row)) return 0;
      return (int)($row[$col] ?? 0);
    };
  @endphp
  <div class="page-frame text-center"><!-- ここが実効幅281mmの中央寄せコンテナ -->
    <div class="page-content">
    <!-- タイトル行 -->
    <table class="table b-none no-overlap mt-5 mb-2"
           style="width: 230mm; table-layout: fixed; border-collapse: collapse;
                  margin: 0 auto; clear:both;">
      <tr>
        <td class="text-center"><h18>{{ $wareki_year ?? '令和7年' }}の課税所得金額・税額の予測</h18></td>
      </tr>
      <tr>
        <td class="text-start"><h16>★上限額まで寄附した場合</h16></td>
      </tr>
    </table>
    
      <table class="table table-compact-p text-center mb-0"
             style="width:237mm; font-size:15px; line-height:1.6; margin: 0 auto; outline:2px solid #000; outline-offset:-2px;">
        <colgroup>
          <col style="width:10mm">
          <col style="width:20mm">
          <col style="width:31mm">
          <col style="width:30mm">
          <col style="width:30mm">
          <col style="width:29mm">
          <col style="width:29mm">
          <col style="width:29mm">
          <col style="width:29mm">
        </colgroup>
        <tbody>
          <tr style="line-height: 27px;">
            <td colspan="3" rowspan="3" class="b-b-strong b-r-strong"><h14u>項　　目</h14u></td>
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
            <td colspan="3" class="text-start b-r-strong b-b-no">総合課税</td>
            <td class="text-end">{{ $fmt($taxable['sogo']['itax'] ?? 0) }}</td>
            <td class="text-end b-r-strong">{{ $fmt($taxable['sogo']['jumin'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($itaxZ['sogo'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($pick($juminZ,'sogo','muni')) }}</td>
            <td class="text-end">{{ $fmt($pick($juminZ,'sogo','pref')) }}</td>
            <td class="text-end">{{ $fmt($pick($juminZ,'sogo','total')) }}</td>
          </tr>
          <tr>
            <td colspan="3" class="text-start b-r-strong b-y-no">短期譲渡</td>
            <td class="text-end">{{ $fmt($taxable['tanki']['itax'] ?? 0) }}</td>
            <td class="text-end b-r-strong">{{ $fmt($taxable['tanki']['jumin'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($itaxZ['tanki'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($pick($juminZ,'tanki','muni')) }}</td>
            <td class="text-end">{{ $fmt($pick($juminZ,'tanki','pref')) }}</td>
            <td class="text-end">{{ $fmt($pick($juminZ,'tanki','total')) }}</td>
          </tr>
          <tr>
            <td colspan="3" class="text-start b-r-strong b-y-no">長期譲渡</td>
            <td class="text-end">{{ $fmt($taxable['choki']['itax'] ?? 0) }}</td>
            <td class="text-end b-r-strong">{{ $fmt($taxable['choki']['jumin'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($itaxZ['choki'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($pick($juminZ,'choki','muni')) }}</td>
            <td class="text-end">{{ $fmt($pick($juminZ,'choki','pref')) }}</td>
            <td class="text-end">{{ $fmt($pick($juminZ,'choki','total')) }}</td>
          </tr>
          <tr>
            <td colspan="3" class="text-start b-r-strong b-y-no">一般・上場株式等の譲渡</td>
            <td class="text-end">{{ $fmt($taxable['kabujoto']['itax'] ?? 0) }}</td>
            <td class="text-end b-r-strong">{{ $fmt($taxable['kabujoto']['jumin'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($itaxZ['kabujoto'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($pick($juminZ,'kabujoto','muni')) }}</td>
            <td class="text-end">{{ $fmt($pick($juminZ,'kabujoto','pref')) }}</td>
            <td class="text-end">{{ $fmt($pick($juminZ,'kabujoto','total')) }}</td>
          </tr>
          <tr>
            <td colspan="3" class="text-start b-r-strong b-y-no">上場株式の配当等</td>
            <td class="text-end">{{ $fmt($taxable['haito']['itax'] ?? 0) }}</td>
            <td class="text-end b-r-strong">{{ $fmt($taxable['haito']['jumin'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($itaxZ['haito'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($pick($juminZ,'haito','muni')) }}</td>
            <td class="text-end">{{ $fmt($pick($juminZ,'haito','pref')) }}</td>
            <td class="text-end">{{ $fmt($pick($juminZ,'haito','total')) }}</td>
          </tr>
          <tr>
            <td colspan="3" class="text-start b-r-strong b-y-no">先物取引</td>
            <td class="text-end">{{ $fmt($taxable['sakimono']['itax'] ?? 0) }}</td>
            <td class="text-end b-r-strong">{{ $fmt($taxable['sakimono']['jumin'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($itaxZ['sakimono'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($pick($juminZ,'sakimono','muni')) }}</td>
            <td class="text-end">{{ $fmt($pick($juminZ,'sakimono','pref')) }}</td>
            <td class="text-end">{{ $fmt($pick($juminZ,'sakimono','total')) }}</td>
          </tr>
          <tr>
            <td colspan="3" class="text-start b-r-strong b-y-no">山林</td>
            <td class="text-end">{{ $fmt($taxable['sanrin']['itax'] ?? 0) }}</td>
            <td class="text-end b-r-strong">{{ $fmt($taxable['sanrin']['jumin'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($itaxZ['sanrin'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($pick($juminZ,'sanrin','muni')) }}</td>
            <td class="text-end">{{ $fmt($pick($juminZ,'sanrin','pref')) }}</td>
            <td class="text-end">{{ $fmt($pick($juminZ,'sanrin','total')) }}</td>
          </tr>
          <tr>
            <td colspan="3" class="text-start b-r-strong b-y-no">退職</td>
            <td class="text-end">{{ $fmt($taxable['taishoku']['itax'] ?? 0) }}</td>
            <td class="text-end b-r-strong">{{ $fmt($taxable['taishoku']['jumin'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($itaxZ['taishoku'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($pick($juminZ,'taishoku','muni')) }}</td>
            <td class="text-end">{{ $fmt($pick($juminZ,'taishoku','pref')) }}</td>
            <td class="text-end">{{ $fmt($pick($juminZ,'taishoku','total')) }}</td>
          </tr>
          <tr>
            <td colspan="3" class="b-b-strong b-r-strong">合　　計</td>
            <td colspan="2" class="diag-auto b-b-strong b-r-strong"
                style="--diag-width:1px; --diag-color:#000;">&nbsp;</td>
            <td class="text-end b-b-strong">{{ $fmt($itaxZ['gokei'] ?? 0) }}</td>
            <td class="text-end b-b-strong">{{ $fmt($pick($juminZ,'gokei','muni')) }}</td>
            <td class="text-end b-b-strong">{{ $fmt($pick($juminZ,'gokei','pref')) }}</td>
            <td class="text-end b-b-strong">{{ $fmt($pick($juminZ,'gokei','total')) }}</td>
          </tr>

          <tr>
            <td rowspan="8"><h14u>税<br>額<br>控<br>除</h14u></td>
            <td class="text-start b-r-strong" colspan="2">
                <table style="width:100%; border-collapse:collapse; table-layout:fixed; border:0 !important;">
                  <tr>
                    <td style="border:0 !important; padding:0 !important; text-align:left;">調整控除</td>
                    <td style="border:0 !important; padding:0 !important; text-align:right;">①</td>
                  </tr>
                </table>
            </td>
            <td colspan="2" class="diag-auto b-r-strong" style="--diag-width:1px; --diag-color:#000;">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end">{{ $fmt($credits['chosei']['muni'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($credits['chosei']['pref'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($credits['chosei']['total'] ?? 0) }}</td>
          </tr>
          <tr>
            <td class="text-start b-r-strong" colspan="2">
                <table style="width:100%; border-collapse:collapse; table-layout:fixed; border:0 !important;">
                  <tr>
                    <td style="border:0 !important; padding:0 !important; text-align:left;">配当控除</td>
                    <td style="border:0 !important; padding:0 !important; text-align:right;">②</td>
                  </tr>
                </table>
            </td>
            <td colspan="2" class="diag-auto b-r-strong" style="--diag-width:1px; --diag-color:#000;">&nbsp;</td>
            <td class="text-end">{{ $fmt($credits['haito']['itax'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($credits['haito']['muni'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($credits['haito']['pref'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($credits['haito']['total'] ?? 0) }}</td>
          </tr>
          <tr>
            <td class="text-start b-r-strong" colspan="2">
                <table style="width:100%; border-collapse:collapse; table-layout:fixed; border:0 !important;">
                  <tr>
                    <td style="border:0 !important; padding:0 !important; text-align:left;" nowrap>住宅借入金等特別控除</td>
                    <td style="border:0 !important; padding:0 !important; text-align:right;">③</td>
                  </tr>
                </table>
            </td>
            <td colspan="2" class="diag-auto b-r-strong" style="--diag-width:1px; --diag-color:#000;">&nbsp;</td>
            <td class="text-end">{{ $fmt($credits['jutaku']['itax'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($credits['jutaku']['muni'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($credits['jutaku']['pref'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($credits['jutaku']['total'] ?? 0) }}</td>
          </tr>
          <tr>
            <td class="text-start b-r-strong" colspan="2">
                <table style="width:100%; border-collapse:collapse; table-layout:fixed; border:0 !important;">
                  <tr>
                    <td style="border:0 !important; padding:0 !important; text-align:left;" nowrap>政党等寄附金等特別控除</td>
                    <td style="border:0 !important; padding:0 !important; text-align:right;">④</td>
                  </tr>
                </table>
            </td>
            <td colspan="2" class="diag-auto b-r-strong" style="--diag-width:1px; --diag-color:#000;">&nbsp;</td>
            <td class="text-end">{{ $fmt($credits['seitoto']['itax'] ?? 0) }}</td>
            <td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td>
          </tr>
          <tr>
            <td class="b-r-strong" colspan="2">上記①～④控除後の所得割額
            </td>
            <td colspan="2" class="diag-auto b-r-strong" style="--diag-width:1px; --diag-color:#000;">&nbsp;</td>
            <td class="text-end">{{ $fmt($credits['after14']['itax'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($credits['after14']['muni'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($credits['after14']['pref'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($credits['after14']['total'] ?? 0) }}</td>
          </tr>
          <tr>
            <td rowspan="2" class="text-start">寄附金税額<br>控除</td>
            <td class="text-start b-r-strong">下記以外</td>
            <td colspan="2" class="diag-auto b-r-strong" style="--diag-width:1px; --diag-color:#000;">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end"><hb>{{ $fmt($credits['kifukin_other']['muni'] ?? 0) }}</hb></td>
            <td class="text-end"><hb>{{ $fmt($credits['kifukin_other']['pref'] ?? 0) }}</hb></td>
            <td class="text-end"><hb>{{ $fmt($credits['kifukin_other']['total'] ?? 0) }}</hb></td>
          </tr>
          <tr>
            <td class="text-start b-r-strong">ふるさと(※)</td>
            <td colspan="2" class="diag-auto b-r-strong" style="--diag-width:1px; --diag-color:#000;">&nbsp;</td>
            <td class="text-end">&nbsp;</td>
            <td class="text-end b-strong"><hb>{{ $fmt($credits['kifukin_furu']['muni'] ?? 0) }}</hb></td>
            <td class="text-end b-strong"><hb>{{ $fmt($credits['kifukin_furu']['pref'] ?? 0) }}</hb></td>
            <td class="text-end b-t-strong b-b-strong"><hb>{{ $fmt($credits['kifukin_furu']['total'] ?? 0) }}</hb></td>
          </tr>
          <tr>
            <td class="text-start b-r-strong" colspan="2">災害減免額</td>
            <td colspan="2" class="diag-auto b-r-strong" style="--diag-width:1px; --diag-color:#000;">&nbsp;</td>
            <td class="text-end">{{ $fmt($credits['saigai']['itax'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($credits['saigai']['muni'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($credits['saigai']['pref'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($credits['saigai']['total'] ?? 0) }}</td>
          </tr>

          <tr>
            <td rowspan="3" class="b-t-strong"><h14u>税<br>額</h14u></td>
            <td class="text-start b-r-strong b-t-strong" colspan="2">差引所得税額（所得割額）　★</td>
            <td colspan="2" class="diag-auto b-r-strong" style="--diag-width:1px; --diag-color:#000;">&nbsp;</td>
            <td class="text-end">{{ $fmt($final['kijun_itax'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($finalJ['muni'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($finalJ['pref'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($finalJ['total'] ?? 0) }}</td>
          </tr>
          <tr>
            <td class="text-start b-r-strong" colspan="2">復興特別所得税額</td>
            <td colspan="2" class="diag-auto b-r-strong b-t-strong" style="--diag-width:1px; --diag-color:#000;">&nbsp;</td>
            <td class="text-end b-t-strong">{{ $fmt($final['fukkou_itax'] ?? 0) }}</td>
            <td class="text-end b-t-strong">&nbsp;</td><td class="text-end b-t-strong">&nbsp;</td><td class="text-end b-t-strong">&nbsp;</td>
          </tr>
          <tr>
            <td class="b-r-strong" colspan="2">合　　計</td>
            <td colspan="2" class="diag-auto b-r-strong" style="--diag-width:1px; --diag-color:#000;">&nbsp;</td>
            <td class="text-end b-t-strong b-l-strong b-r-strong">{{ $fmt($final['gokei_itax'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($finalJ['muni'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($finalJ['pref'] ?? 0) }}</td>
            <td class="text-end">{{ $fmt($finalJ['total'] ?? 0) }}</td>
          </tr>
        </tbody>
      </table>

    <table class="table b-none no-overlap mt-2"
           style="width: 225mm; table-layout: fixed; border-collapse: collapse;
                  margin: 0 auto; clear:both;">
      <tr>
        <td class="text-start" style="line-height:1.5;"><h13>
          ※寄附金税額控除の計算過程は「所得税・住民税の軽減額の計算過程(4ページ)」にあります。<br>
          ★この数値がマイナスの場合、本来であれば0と表示されますが、注意喚起のためマイナスのまま表示しています。
        </h13></td>
      </tr>
    </table>
    </div><!-- 本文終り -->
      <div class="page-footer">
        <div class="footer-inner">
          <table class="table b-none no-overlap mb-0">
            <tr>
              <td><h14u>３ページ</h14u></td>
            </tr>
          </table>
        </div>
      </div>
  </div><!-- /.page-frame -->
@endsection


