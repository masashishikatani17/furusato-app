{{-- resources/views/pdf/2_syotokukinkojyosoku.blade.php --}}
@extends('pdf.layouts.print')

@section('title','令和７年の所得・税額予想')

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
    $income = is_array($income_table_curr ?? null) ? $income_table_curr : [];
    $kojo   = is_array($kojo_table_curr ?? null) ? $kojo_table_curr : [];
    $sogo   = is_array($income['sogo'] ?? null) ? $income['sogo'] : [];
    $bunri  = is_array($income['bunri'] ?? null) ? $income['bunri'] : [];
    $fmt = static fn($v) => number_format((int)($v ?? 0));

    $kRows = is_array($kojo['rows'] ?? null) ? $kojo['rows'] : [];
    $kShokei = $kojo['shokei'] ?? ['itax'=>0,'rtax'=>0];
    $kZasson = $kojo['zasson'] ?? ['itax'=>0,'rtax'=>0];
    $kIryo   = $kojo['iryo']   ?? ['itax'=>0,'rtax'=>0];
    $kKifukinItax = (int)($kojo['kifukin_itax'] ?? 0);
    $kTotal  = $kojo['total']  ?? ['itax'=>0,'rtax'=>0];
  @endphp
  <div class="page-frame text-center"><!-- ここが実効幅281mmの中央寄せコンテナ -->
    <div class="page-content">
    <table class="table b-none no-overlap mb-2"
           style="width: 250mm; table-layout: fixed; border-collapse: collapse;
                  margin: 0 auto; clear:both;">
      <tr>
        <td class="text-center"><h18>{{ $wareki_year ?? '令和７年' }}の所得金額・所得控除額の予測</h18></td>
      </tr>
      <tr>
        <td class="text-start"><h16>★上限額まで寄附した場合</h16></td>
      </tr>
    </table>

    <!-- ★ flex をやめて、横並びはレイアウト用テーブルで固定（DomPDF安定） -->
    <table class="table b-none no-overlap"
           style="width:250mm; table-layout:fixed; border-collapse:collapse; margin:0 auto;">
      <colgroup>
        <col style="width:118mm;">
        <col style="width:10mm;">
        <col style="width:122mm;">
      </colgroup>
      <tr>
        <!-- 左側 -->
        <td class="b-none" style="vertical-align:top; padding:0; text-align:left; outline:2px solid #000; outline-offset:-2px;">
          <div style="text-align:left;">
            <table class="table table-compact-p text-start no-overlap table-118mm" 
                   style="width:118mm; margin:0 auto; font-size:13px; line-height:1.6;">
              <colgroup>
                <col style="width:11mm">
                <col style="width:11mm">
                <col style="width:11mm">
                <col style="width:27mm">
                <col style="width:29mm">
                <col style="width:29mm">
              </colgroup>
              <tbody>
                <tr>
                  <td colspan="4" class="bg-grey"><h14>区　　分</h14></td>
                  <td class="bg-grey"><h14>所得税</h14></td>
                  <td class="bg-grey"><h14>住民税</h14></td>
                </tr>
                <tr>
                  <td rowspan="23" class="text-center"><h18>所<br>得<br>金<br>額<br>等</h18></td>
                  <td rowspan="11" class="text-center"><h18>総<br>合<br>課<br>税</h18></td>
                  <td rowspan="2">事業</td>
                  <td class="text-start">営業等</td>
                  <td class="text-end">{{ $fmt($sogo['jigyo_eigyo']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($sogo['jigyo_eigyo']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td class="text-start">農業</td>
                  <td class="text-end">{{ $fmt($sogo['jigyo_nogyo']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($sogo['jigyo_nogyo']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td colspan="2" class="text-start">不動産</td>
                  <td class="text-end">{{ $fmt($sogo['fudosan']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($sogo['fudosan']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td colspan="2" class="text-start">利子</td>
                  <td class="text-end">{{ $fmt($sogo['rishi']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($sogo['rishi']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td colspan="2" class="text-start">配当</td>
                  <td class="text-end">{{ $fmt($sogo['haito']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($sogo['haito']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td colspan="2" class="text-start">給与</td>
                  <td class="text-end">{{ $fmt($sogo['kyuyo']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($sogo['kyuyo']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td rowspan="3">雑</td>
                  <td class="text-start">公的年金等</td>
                  <td class="text-end">{{ $fmt($sogo['zatsu_nenkin']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($sogo['zatsu_nenkin']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td class="text-start">業務</td>
                  <td class="text-end">{{ $fmt($sogo['zatsu_gyomu']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($sogo['zatsu_gyomu']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td class="text-start">その他</td>
                  <td class="text-end">{{ $fmt($sogo['zatsu_sonota']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($sogo['zatsu_sonota']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td colspan="2" class="text-start">総合譲渡・一時</td>
                  <td class="text-end">{{ $fmt($sogo['joto_ichiji']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($sogo['joto_ichiji']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td colspan="2">合　　計</td>
                  <td class="text-end">{{ $fmt($income['sogo_total']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($income['sogo_total']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td rowspan="9" class="text-center"><h18>分<br>離<br>課<br>税</h18></td>
                  <td rowspan="2">短譲<br>期渡</td>
                  <td class="text-start">一般分</td>
                  <td class="text-end">{{ $fmt($bunri['tanki_ippan']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($bunri['tanki_ippan']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td class="text-start">軽減分</td>
                  <td class="text-end">{{ $fmt($bunri['tanki_keigen']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($bunri['tanki_keigen']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td rowspan="3" class="lh-1">長<br>期<br>譲<br>渡</td>
                  <td class="text-start">一般分</td>
                  <td class="text-end">{{ $fmt($bunri['choki_ippan']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($bunri['choki_ippan']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td class="text-start">特定分</td>
                  <td class="text-end">{{ $fmt($bunri['choki_tokutei']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($bunri['choki_tokutei']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td class="text-start">軽課分</td>
                  <td class="text-end">{{ $fmt($bunri['choki_keika']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($bunri['choki_keika']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td colspan="2" class="text-start">一般株式等の譲渡</td>
                  <td class="text-end">{{ $fmt($bunri['ippan_kabu']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($bunri['ippan_kabu']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td colspan="2" class="text-start">上場株式等の譲渡</td>
                  <td class="text-end">{{ $fmt($bunri['jojo_kabu']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($bunri['jojo_kabu']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td colspan="2" class="text-start">上場株式等の配当等</td>
                  <td class="text-end">{{ $fmt($bunri['jojo_haito']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($bunri['jojo_haito']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td colspan="2" class="text-start">先物取引</td>
                  <td class="text-end">{{ $fmt($bunri['sakimono']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($bunri['sakimono']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td colspan="3" class="text-start">山林</td>
                  <td class="text-end">{{ $fmt($bunri['sanrin']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($bunri['sanrin']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td colspan="3" class="text-start">退職</td>
                  <td class="text-end">{{ $fmt($bunri['taishoku']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($bunri['taishoku']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td colspan="3">合　　計</td>
                  <td class="text-end">{{ $fmt($income['grand_total']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($income['grand_total']['rtax'] ?? 0) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </td>

        <!-- gap -->
        <td class="b-none" style="padding:0;">&nbsp;</td>

        <!-- 右側 -->
        <td class="b-none" style="vertical-align:top; padding:0; text-align:left;">
          
            <table class="table table-compact-p text-start no-overlap table-119mm" 
                   style="width:119mm; margin:0 auto; font-size:13px; line-height:1.6; outline:2px solid #000; outline-offset:-2px;">
              <colgroup>
                <col style="width:11mm">
                <col style="width:54mm">
                <col style="width:27mm">
                <col style="width:27mm">
              </colgroup>
              <tbody>
                <tr>
                  <td colspan="2" class="bg-grey"><h14>区　　分</h14></td>
                  <td class="bg-grey"><h14>所得税</h14></td>
                  <td class="bg-grey"><h14>住民税</h14></td>
                </tr>
                <tr>
                  <td rowspan="18"><h18>所<br>得<br>か<br>ら<br>差<br>し<br>引<br>か<br>れ<br>る<br>金<br>額</h18></td>
                  <td class="text-start">社会保険料控除</td>
                  <td class="text-end">{{ $fmt($kRows['shakaihoken']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($kRows['shakaihoken']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td class="text-start">小規模企業共済等掛金控除</td>
                  <td class="text-end">{{ $fmt($kRows['shokibo']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($kRows['shokibo']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td class="text-start">生命保険料控除</td>
                  <td class="text-end">{{ $fmt($kRows['seimei']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($kRows['seimei']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td class="text-start">地震保険料控除</td>
                  <td class="text-end">{{ $fmt($kRows['jishin']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($kRows['jishin']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td class="text-start">寡婦控除</td>
                  <td class="text-end">{{ $fmt($kRows['kafu']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($kRows['kafu']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td class="text-start">ひとり親控除</td>
                  <td class="text-end">{{ $fmt($kRows['hitorioya']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($kRows['hitorioya']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td class="text-start">勤労学生控除</td>
                  <td class="text-end">{{ $fmt($kRows['kinrogakusei']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($kRows['kinrogakusei']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td class="text-start">障害者控除</td>
                  <td class="text-end">{{ $fmt($kRows['shogaisha']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($kRows['shogaisha']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td class="text-start">配偶者控除</td>
                  <td class="text-end">{{ $fmt($kRows['haigusha']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($kRows['haigusha']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td class="text-start">配偶者特別控除</td>
                  <td class="text-end">{{ $fmt($kRows['haigusha_tok']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($kRows['haigusha_tok']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td class="text-start">扶養控除</td>
                  <td class="text-end">{{ $fmt($kRows['fuyo']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($kRows['fuyo']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td class="text-start">特定親族特別控除</td>
                  <td class="text-end">{{ $fmt($kRows['tokutei_shinz']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($kRows['tokutei_shinz']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td class="text-start">基礎控除</td>
                  <td class="text-end">{{ $fmt($kRows['kiso']['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($kRows['kiso']['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td>小　　計</td>
                  <td class="text-end">{{ $fmt($kShokei['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($kShokei['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td class="text-start">雑損控除</td>
                  <td class="text-end">{{ $fmt($kZasson['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($kZasson['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td class="text-start">医療費控除</td>
                  <td class="text-end">{{ $fmt($kIryo['itax'] ?? 0) }}</td>
                  <td class="text-end">{{ $fmt($kIryo['rtax'] ?? 0) }}</td>
                </tr>
                <tr>
                  <td class="text-start">寄附金控除</td>
                  <td class="text-end b-strong"><hb>{{ number_format((int)($kKifukinItax ?? 0)) }}</hb></td>
                  <td>-</td>
                </tr>
                <tr><td>合　　計</td><td class="text-end">{{ $fmt($kTotal['itax'] ?? 0) }}</td><td class="text-end">{{ $fmt($kTotal['rtax'] ?? 0) }}</td></tr>
              </tbody>
            </table>
          

          <table class="table b-none no-overlap"
                 style="width: 122mm; table-layout: fixed; border-collapse: collapse;
                        margin: 0 auto; clear:both;">
            <tr>
              <td class="text-start"><h14u>※寄附金控除は寄附金額から2,000円を控除した額となります。</h14u></td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
    </div><!-- 本文終り -->
      <div class="page-footer">
        <div class="footer-inner">
          <table class="table b-none no-overlap mb-0">
            <tr>
              <td><h14u>２ページ</h14u></td>
            </tr>
          </table>
        </div>
      </div>
  </div><!-- /.page-frame -->
@endsection