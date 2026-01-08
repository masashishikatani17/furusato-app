
{{-- resources/views/pdf/0_hyoshi.blade.php --}}
@extends('pdf.layouts.print')

@section('title','表紙')

@section('head')
<style>
  /* テーブルの基本 */
  table { border-collapse: collapse; table-layout: fixed; }
    
</style>
@endsection

@section('content')
  {{-- Data表示は将来埋める前提で “いったん空欄” --}}
  @php
    $coverGuest = (string)($cover_guest_name ?? '');
    $coverDate  = (string)($cover_date ?? '');
    $coverOrg   = (string)($cover_org ?? '');
  @endphp

  <section class="sheet" aria-label="表紙">
   <div class="cover">
     <div class="cover-frame mt-11" style="--cover-max-width:264mm;">
      <table class="table b-none"
             style="width:264mm; height:176mm; margin:0 auto; table-layout:fixed;">
          <colgroup>
            <col style="width:40mm;">
            <col style="width:184mm;">
            <col style="width:40mm;">
          </colgroup>
        <tbody>
          <tr>
            <td style="height:24mm;" colspan="3" valign="bottom" class="text-start b-none ps-20">
              <h24>山田太郎様{{ $coverGuest }}</h24>
            </td>
          </tr>
          <tr>
            <td style="height:20mm;" class="text-start b-none ps-130" colspan="3">&nbsp;</td>
          </tr>
          <tr>
            <td class="b-none">&nbsp;</td>
            <td class="text-center pb-0" valign="middle" bgcolor="#0070C0" style=" height:32mm;">
              <h30 style="color:#ffffff">ふるさと上限額シミュレーション</h30>
            </td>
            <td class="b-none">&nbsp;</td>
          </tr>
          <tr>
            <td style="height:40mm;" class="b-none" colspan="3" valign="bottom"><h24>2025年9月16日{{ $coverDate }}</h24></td>
          </tr>
          <tr>
            <td style="height:30mm;" class="b-none" colspan="3" valign="bottom"><h24>公認会計士・税理士 ABC 税理士法人{{ $coverOrg }}</h24></td>
          </tr>
          <tr>
            <td class="b-none" colspan="3" style="height:30mm;"></td>
          </tr>
        </tbody>
      </table>
     </div>
    </div> 
  </section>
@endsection
