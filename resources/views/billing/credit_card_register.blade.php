@extends('layouts.min')

@section('title', 'クレジットカード登録')

@section('content')
<div class="container-tya" style="width: 760px; margin: 0 auto;">
  <div class="card-header d-flex align-items-start">
      <img src="{{ asset('storage/images/kado_lefttop_tya.jpg') }}" alt="…">
      <h0 class="mb-0 ms-3 mt-2" style="color:#895827;"> クレジットカード登録</h0>
  </div>

  <div class="wrapper">
    @if ($errors->any())
      <div class="alert alert-danger mb-3">
        <div class="mb-1">入力内容を確認してください。</div>
        <ul class="mb-0">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="alert alert-info mb-3">
      カード情報は請求管理ロボの決済フォームで登録します。<br>
      登録完了後、自動で年次定期の請求情報を作成します。
    </div>

    <table class="table table-base mb-4 align-middle" style="width: 640px;">
      <tbody>
        <tr>
          <th class="text-start ps-2 bg-ccc" style="width: 180px;">会社名</th>
          <td class="text-start">{{ $company->name }}</td>
        </tr>
        <tr>
          <th class="text-start ps-2 bg-ccc">請求先コード</th>
          <td class="text-start">{{ $billingCode }}</td>
        </tr>
        <tr>
          <th class="text-start ps-2 bg-ccc">決済情報コード</th>
          <td class="text-start">{{ $paymentMethodCode }}</td>
        </tr>
        <tr>
          <th class="text-start ps-2 bg-ccc">メールアドレス</th>
          <td class="text-start">{{ $email }}</td>
        </tr>
        <tr>
          <th class="text-start ps-2 bg-ccc">電話番号</th>
          <td class="text-start">{{ $tel }}</td>
        </tr>
      </tbody>
    </table>

    <form id="mainform" method="POST" action="{{ $postUrl }}" novalidate>
      @csrf
      <input id="tkn" name="token" type="hidden" value="">
      <div id="CARD_INPUT_FORM"></div>
      <div id="EMV3DS_INPUT_FORM"></div>

      <div class="btn-footer mb-3">
        <div class="d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center gap-2">
            <a class="btn-base-blue" href="{{ route('login') }}">ログインへ戻る</a>
          </div>
          <div class="d-flex align-items-center gap-2">
            <button type="button" id="open-credit-card-form" class="btn-base-blue">カード情報を入力して登録</button>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script src="{{ $jqueryJsUrl }}"></script>
<script src="{{ $tokenJsUrl }}"></script>
<script src="{{ $emv3dsJsUrl }}"></script>
<script>
  (function () {
    let submitting = false;

    function execPurchase(resultCode, errMsg) {
      if (resultCode !== 'Success') {
        window.alert(errMsg || '3Dセキュア認証に失敗しました。');
        submitting = false;
        return;
      }

      document.getElementById('mainform').submit();
    }

    function execAuth(resultCode, errMsg) {
      if (resultCode !== 'Success') {
        window.alert(errMsg || 'カード情報のトークン化に失敗しました。');
        submitting = false;
        return;
      }

      ThreeDSAdapter.authenticate({
        tkn: $('#tkn').val(),
        aid: @json($creditAid),
        am: 0,
        tx: 0,
        sf: 0,
        em: @json($email),
        pn: @json($tel)
      }, execPurchase);
    }

    function doPurchase() {
      if (submitting) return;
      submitting = true;

      CPToken.CardInfo({
        aid: @json($creditAid)
      }, execAuth);
    }

    document.getElementById('open-credit-card-form').addEventListener('click', doPurchase);
  })();
</script>
@endpush