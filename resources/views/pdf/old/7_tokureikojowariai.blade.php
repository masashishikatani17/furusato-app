{{-- resources/views/pdf/7_tokureikojowariai.blade.php --}}
@extends('pdf.layouts.print')

@section('title','特例控除割合')

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
  <div class="page-frame">
  <div class="page-content">
    <table class="table b-none no-overlap text-center"
           style="width: 260mm; table-layout: fixed; border-collapse: collapse; margin: 0 auto 2mm; clear: both; page-break-inside: avoid;">
      <tr>
        <td class="text-center"><h18 class="text-center">特例控除割合</h18></td>
      </tr>
    </table>

    <table class="table table-base mt-3 mb-5 no-overlap mb-tight"
           style="width: 272mm; table-layout: fixed; border-collapse: collapse; margin: 0 auto 2mm; clear: both; page-break-inside: avoid;">
      <tr>
        <td class=" text-center b-l-no b-r-no">
          <h14>★特例控除加算に使用する特例控除割合は次のそれぞれの場合に応じ、それぞれに定める控除割合を用います。</h14>
          <div class="text-start ms-5 me-5 mt-3 mb-1">
            <h12>
              ※所得税は所得控除なので所得税の適用税率が高いと同じ寄附金であっても節税額が多くなります。そこで住民税の特例控除額を計算するに当たって適用される特例控除割合を<br>&nbsp;&nbsp;&nbsp;小さくしているのです。所得税と住民税の節税額の合計は変わりません。
            </h12>
          </div>
        </td>
      </tr>
    </table>

    <!-- ★ grid をやめて、横並びはレイアウト用テーブルで固定（DomPDF安定） -->
    <table class="table b-none no-overlap"
           style="width:272mm; table-layout:fixed; border-collapse:collapse; margin:0 auto;">
      <colgroup>
        <col style="width:120mm;">
        <col style="width:12mm;">
        <col style="width:140mm;">
      </colgroup>
      <tr>
        <td class="b-none" style="vertical-align:top; padding:0;">
        <!-- 説明（左） -->
        <table class="g-table--none text-start table-120mm ms-4">
          <tbody>
            <tr><td class="text-start"><h14>(ア)&nbsp;課税総所得金額がある場合で</h14></td></tr>
            <tr><td class="text-end pe-3"><h14>課税総所得金額ー人的控除差調整額≧０であるとき</h14></td></tr>
            <tr><td class="text-start"><h13>　次の区分に応じ、それぞれの特例控除割合を適用して計算します。</h13></td></tr>
          </tbody>
        </table>

        <!-- レート表（左） -->
        <table class="table table-compact-p ms-4 mt-1 mb-3 no-overlap mb-tight table-120mm" style="font-size:13px;line-height:1.5;">
          <colgroup>
            <col style="width:21mm">
            <col style="width:6mm">
            <col style="width:21mm">
            <col style="width:16mm">
            <col style="width:16mm">
            <col style="width:20mm">
            <col style="width:20mm">
          </colgroup>
          <tbody>
            <tr>
              <td colspan="3">住民税の課税総所得金額<br>－人的控除差調整額</td>
              <td>所得税率</td>
              <td>90％ー<br>所得税率</td>
              <td class="nowrap"><h12>復興税考慮後<br>の所得税率</h12></td>
              <td><hb>特例控除<br>割合</hb></td>
            </tr>
            <tr>
              <td class="text-end b-r-no">0</td>
              <td class="b-l-no b-r-no">～</td>
              <td class="text-end b-l-no">1,950,000</td>
              <td>5％</td>
              <td>85％</td>
              <td>&nbsp;&nbsp;&nbsp;5.105%</td>
              <td><hb>84.895%</hb></td>
            </tr>
            <tr>
              <td class="text-end b-r-no">1,951,000</td>
              <td class="b-l-no b-r-no">～</td>
              <td class="text-end b-l-no">3,300,000</td>
              <td>10％</td>
              <td>80％</td>
              <td>10.210%</td>
              <td><hb>79.790%</hb></td>
            </tr>
            <tr>
              <td class="text-end b-r-no">3,301,000</td>
              <td class="b-l-no b-r-no">～</td>
              <td class="text-end b-l-no">6,950,000</td>
              <td>20％</td>
              <td>70％</td>
              <td>20.420%</td>
              <td><hb>69.580%</hb></td>
            </tr>
            <tr>
              <td class="text-end b-r-no">6,951,000</td>
              <td class="b-l-no b-r-no">～</td>
              <td class="text-end b-l-no">9,000,000</td>
              <td>23％</td>
              <td>67％</td>
              <td>23.483%</td>
              <td><hb>66.517%</hb></td>
            </tr>
            <tr>
              <td class="text-end b-r-no">9,001,000</td>
              <td class="b-l-no b-r-no">～</td>
              <td class="text-end b-l-no">18,000,000</td>
              <td>33％</td>
              <td>57％</td>
              <td>33.693%</td>
              <td><hb>56.307%</hb></td>
            </tr>
            <tr>
              <td class="text-end b-r-no">18,001,000</td>
              <td class="b-l-no b-r-no">～</td>
              <td class="text-end b-l-no">40,000,000</td>
              <td>40％</td>
              <td>50％</td>
              <td>40.840%</td>
              <td><hb>49.160%</hb></td>
            </tr>
            <tr>
              <td class="text-end b-r-no">40,001,000</td>
              <td class="b-l-no b-r-no">～</td>
              <td class="text-end b-l-no">&nbsp;</td>
              <td>45％</td>
              <td>45％</td>
              <td>45.945%</td>
              <td><hb>44.055%</hb></td>
            </tr>
          </tbody>
        </table>

        <!-- 備考（左） -->
        <table class="g-table--none text-start table-120mm ms-4" style="font-size:13px; line-height:1.4;">
          <tbody>
            <tr>
              <td valign="top" class="text-end" style="width:3%;">※</td>
              <td class="text-start" style="width:97%;"><u>ここでいう課税総所得金額には分離課税の所得は含まれません。総合課税の所得だけで判定します。</u></td>
            </tr>
            <tr>
              <td valign="top" class="text-end pt-2" style="width:3%;">※</td>
              <td class="text-start pt-2"><span class="u-wave">分離課税所得金額があってもこの控除割合を使用します。</span></td>
            </tr>
            <tr>
              <td valign="top" class="text-end pt-2" style="width:3%;">※</td>
              <td class="text-start pt-2">ふるさと納税をしようと考えている方で課税総所得金額がマイナスとい<br>
              う方は少ないので、ほとんどのケースでこの控除割合が使用されます。</td>
            </tr>
          </tbody>
        </table>

        <!-- 追加ブロック（左） -->
        <table class="g-table--none text-start table-120mm mt-5 ms-4" style="margin:0 auto;">
          <colgroup>
            <col style="width:64mm">
            <col style="width:16mm">
            <col style="width:40mm">
          </colgroup>
          <tbody>
            <tr><td colspan="3" class="text-start"><h14>(イ)&nbsp;課税総所得金額がある場合で、課税総所得金額ー人的控除差調</h14></td></tr>
            <tr><td colspan="3" class="text-start ps-5"><h14>&nbsp;整額＜０、かつ課税山林所得金額と課税退職所得金額がないとき</h14></td></tr>
            <tr><td colspan="3" class="text-start ps-5 pb-2"><h14></h14></td></tr>
            <tr>
              <td valign="middle" class="text-end ps-1 pe-2" style="height:6mm;"><hb>特例控除割合</hb></td>
              <td valign="middle" class="text-center b-strong"><hb>90%</hb></td>
              <td>&nbsp;</td>
            </tr>
            <tr>
              <td colspan="3" valign="middle" class="text-start ps-3 pt-2">
                <h13><span class="u-wave">★分離課税所得金額があれば(エ)の控除割合を用います。</span></h13>
              </td>
            </tr>
          </tbody>
        </table>
        </td>
        <td class="b-none" style="padding:0;">&nbsp;</td>
        <td class="b-none" style="vertical-align:top; padding:0;">
          <table class="g-table--none ms-3 table-130mm">
            <tbody>
              <tr>
                <td class="text-start"><h14>(ウ)&nbsp;課税総所得金額がある場合で、課税総所得金額ー人的控除差調整額＜０</h14></td>
              </tr>
              <tr>
                <td class="text-start ps-5"><h14>&nbsp;であるとき、または課税総所得金額がない場合で、課税山林所得金額または</h14></td>
              </tr>
              <tr>
                <td class="text-start ps-5"><h14>&nbsp;課税退職所得金額があるとき</h14><h13></h13></td>
              </tr>
              <tr>
                <td class="text-start ps-3 pt-1 pb-3">
                  <h13><span class="u-wave">★分離課税所得金額があれば(エ)の控除割合を用います。</span></h13>
                </td>
              </tr>
            </tbody>
          </table>

          <table class="table text-start table-130mm ms-3 mt-3"
                 style="font-size:12px; line-height:1.5; outline:1px solid #000; outline-offset:-1px;">
            <tbody>
              <tr>
                <td class="text-start ps-5 pt-1 pb-1">
                  1.課税山林所得があるとき<br>
                  　　課税山林所得金額の５分の１に相当する金額について、(ア)の表の区分に応じた割合<br>
                  2.課税退職所得があるとき<br>
                  　　課税退職所得金額について、(ア)の表の区分に応じた割合<br>
                  ※両方ある場合はいずれか低い方<br>
                </td>
              </tr>
            </tbody>
          </table>

          <table class="g-table--none text-start table-140mm ms-3 mt-5" style="margin:0 auto;">
            <tbody>
              <tr>
                <td class="text-start"><h14>(エ)&nbsp;上記(イ)、(ウ)に該当する場合または課税総所得金額、課税退職所得金額、</h14></td>
              </tr>
              <tr>
                <td class="text-start ps-5"><h14>&nbsp;課税山林所得金額がない場合で、分離課税所得金額があるとき</h14></td>
              </tr>
              <tr>
                <td class="text-start ps-5"></td>
              </tr>
            </tbody>
          </table>

          <table class="table table-compact-p mt-3 ms-3 no-overlap mb-tight table-130mm">
            <colgroup>
              <col style="width:56mm">
              <col style="width:18mm">
              <col style="width:18mm">
              <col style="width:19mm">
              <col style="width:19mm">
            </colgroup>
            <tbody>
              <tr>
                <td>区　　分</td>
                <td>所得税率</td>
                <td>90％ー<br>所得税率</td>
                <td class="nowrap"><h12>復興税考慮後<br>の所得税率</h12></td>
                <td><hb>特例控除<br>割合</hb></td>
              </tr>
              <tr>
                <td class="text-start">分離短期譲渡所得</td>
                <td>30％</td>
                <td>60％</td>
                <td>30.630%</td>
                <td><hb>59.370%</hb></td>
              </tr>
              <tr>
                <td class="text-start nowrap">分離長期譲渡所得、上場株式等に<br>係る配当所得、株式等に係る譲渡<br>所得等、先物取引に係る雑所得等</td>
                <td>15％</td>
                <td>75％</td>
                <td>15.315%</td>
                <td><hb>74.685%</hb></td>
              </tr>
              <tr>
                <td class="text-start">課税山林所得金額</td>
                <td colspan="4" class="text-start">課税山林所得金額の５分の１に相当する金額に<br>ついて、(ア)の表の区分に応じた割合</td>
              </tr>
              <tr>
                <td class="text-start">課税退職所得金額</td>
                <td colspan="4" class="text-start">課税退職所得金額について、(ア)の表の区分に<br>応じた割合</td>
              </tr>
            </tbody>
          </table>
          <div class="text-start ms-3"><h13>※２以上に該当する場合は一番低い割合</h13></div>
        </td>
      </tr>
    </table><!-- /.cols-grid -->
    </div><!-- 本文終り -->
      <div class="page-footer">
        <div class="footer-inner">
          <table class="table b-none no-overlap mb-0">
            <tr>
              <td><h14u>７ページ</h14u></td>
            </tr>
          </table>
        </div>
      </div>
  </div><!-- /.page-frame -->
@endsection