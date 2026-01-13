{{-- resources/views/pdf/5_sonntokusimulation.blade.php --}}
@extends('pdf.layouts.print')

@section('title','寄附金額別損得シミュレーション')

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
    <table class="table b-none mt-5 mb-2"
           style="width: 252mm; border-collapse: collapse;">
      <tr>
        <td class="text-center"><h18>寄附金額別損得シミュレーション</h18></td>
      </tr>
    </table>

    <table class="table b-none no-overlap"
           style="width:252mm; table-layout:fixed; border-collapse:collapse; margin:0 auto;">
      <colgroup>
        <col style="width:123mm;">
        <col style="width:6mm;">
        <col style="width:123mm;">
      </colgroup>
     <tbody>
      <tr>
        <td class="b-none" style="vertical-align:top; padding:0;">
        <table class="table b-none no-overlap mt-3 mb-2"
               style="width: 123mm; table-layout: fixed; border-collapse: collapse;
                      margin: 0 auto; clear:both;">
          <tr>
            <td><h18>■５万円ごとの区分</h18></td>
          </tr>
        </table>
        
          <table class="table table-compact-p no-overlap mb-tight table-123mm" 
          style="font-size:13px;line-height:1.2;outline:2px solid #000; outline-offset:-2px;">
            <colgroup>
              <col style="width:8mm">
              <col style="width:23mm">
              <col style="width:23mm">
              <col style="width:23mm">
              <col style="width:23mm">
              <col style="width:23mm">
            </colgroup>
            <tbody>
              <tr>
                <td rowspan="2"><h14>区分</h14></td>
                <td><h14>寄附金額</h14></td>
                <td><h14>減税額</h14></td>
                <td><h14>差  引</h14></td>
                <td><h14>返戻品額</h14></td>
                <td><h14>実質負担額</h14></td>
              </tr>
              <tr>
                <td>①</td>
                <td>②</td>
                <td>①－②＝③</td>
                <td>①×30％＝④</td>
                <td>③－④</td>
              </tr>
              <tr><td class="text-end">1</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              <tr><td class="text-end">2</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              <tr><td class="text-end">3</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              <tr><td class="text-end">4</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              <tr><td class="text-end">5</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              <tr><td class="text-end">6</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              <tr><td class="text-end">7</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              <tr><td class="text-end">8</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              <tr><td class="text-end">9</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              <tr><td class="text-end">10</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              <tr><td class="text-end">11</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              <tr><td class="text-end">12</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              <tr><td class="text-end">13</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              <tr><td class="text-end">14</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              <tr><td class="text-end">15</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              <tr><td class="text-end">16</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              <tr><td class="text-end">17</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              <tr><td class="text-end">18</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              <tr><td class="text-end">19</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              <tr><td class="text-end">20</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              <tr><td class="text-end">21</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              <tr><td class="text-end">22</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              <tr><td class="text-end">23</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              <tr><td class="text-end">24</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              <tr><td class="text-end">25</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              <tr><td class="text-end">26</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              <tr><td class="text-end">27</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              <tr><td class="text-end">28</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              <tr><td class="text-end">29</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              <tr><td class="text-end">30</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
            </tbody>
          </table>
        
        </td>
        <td class="b-none" style="padding:0;">&nbsp;</td>
        <td class="b-none" style="vertical-align:top; padding:0;">
          <table class="table b-none no-overlap mt-3 mb-2"
                 style="width: 123mm; table-layout: fixed; border-collapse: collapse;
                        margin: 0 auto; clear:both;">
            <tr>
              <td><h18>■１万円ごとの区分</h18></td>
            </tr>
          </table>
            <table class="table table-compact-p no-overlap mb-tight table-123mm mb-0" 
            style="font-size:13px;line-height:1.2;outline:2px solid #000; outline-offset:-2px;">
              <colgroup>
                <col style="width:8mm">
                <col style="width:23mm">
                <col style="width:23mm">
                <col style="width:23mm">
                <col style="width:23mm">
                <col style="width:23mm">
              </colgroup>
              <tbody>
                <tr>
                  <td rowspan="2"><h14>区分</h14></td>
                  <td><h14>寄附金額</h14></td>
                  <td><h14>減税額</h14></td>
                  <td><h14>差  引</h14></td>
                  <td><h14>返戻品額</h14></td>
                  <td><h14>実質負担額</h14></td>
                </tr>
                <tr>
                  <td>①</td>
                  <td>②</td>
                  <td>①－②＝③</td>
                  <td>①×30％＝④</td>
                  <td>③－④</td>
                </tr>
                <tr><td class="text-end">1</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
                <tr><td class="text-end">2</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
                <tr><td class="text-end">3</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
                <tr><td class="text-end">4</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
                <tr><td class="text-end">5</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
                <tr><td class="text-end">6</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
                <tr><td class="text-end">7</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
                <tr><td class="text-end">8</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
                <tr><td class="text-end">9</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
                <tr><td class="text-end">10</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
                <tr><td class="text-end">11</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
                <tr><td class="text-end">12</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
                <tr><td class="text-end">13</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
                <tr><td class="text-end">14</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
                <tr><td class="text-end">15</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
                <tr><td class="text-end">16</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
                <tr><td class="text-end">17</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
                <tr><td class="text-end">18</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
                <tr><td class="text-end">19</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
                <tr><td class="text-end">20</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
                <tr><td class="text-end">21</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
                <tr><td class="text-end">22</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
                <tr><td class="text-end">23</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
                <tr><td class="text-end">24</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
                <tr><td class="text-end">25</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
                <tr><td class="text-end">26</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
                <tr><td class="text-end">27</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
                <tr><td class="text-end">28</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
                <tr><td class="text-end">29</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
                <tr><td class="text-end">30</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td><td class="text-end">&nbsp;</td></tr>
              </tbody>
            </table>
        </td>
      </tr>
     </tbody>
    </table>

    <div class="page-footer">
        <div class="footer-inner">
          <table class="table b-none no-overlap mb-0"
                 style="width: 248mm; table-layout: fixed; border-collapse: collapse; margin: 0 auto; clear:both;">
            <tr>
              <td class="text-end"><h14u>５ページ</h14u></td>
            </tr>
          </table>
        </div>
      </div>
  </div><!-- /.page-frame -->
@endsection

