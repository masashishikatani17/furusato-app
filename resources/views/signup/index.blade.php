{{-- resources/views/signup/index.blade.php --}}
@extends('layouts.min')

@section('title', 'お申し込み')

@section('content')
<div class="container-tya" style="width: 650px; margin: 0 auto;">
  <div class="card-header d-flex align-items-start">
      <img src="{{ asset('storage/images/kado_lefttop_tya.jpg') }}" alt="…">
      <h0 class="mb-0 ms-3 mt-2" style="color:#895827;"> お申込み</h0>
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

    <form id="signup-form" method="POST" action="{{ route('signup.submit') }}">
      @csrf

      {{-- ===== Step 1 ===== --}}
      <div id="step-1">
        <div class="apply-steps" aria-label="申込みステップ">
          <div class="apply-step is-active">STEP1・基本情報</div>
          <div class="apply-step">STEP2・お申込み内容</div>          
          <div class="apply-step">STEP3・内容確認</div>
        </div>
        <table class="table table-base mb-3 align-middle" style="width: 540px;">
          <tbody>
            <tr>
              <th class="text-start ps-2 bg-ccc" style="width: 160px;">会社名</th>
              <td class="text-start" style="width: 380px;">
                <input type="text" name="company_name" class="form-control kana25 text-start"
                       value="{{ old('company_name') }}" required maxlength="255">
              </td>
            </tr>
            <tr>
              <th class="text-start ps-2 bg-ccc">代表者名（Owner名）</th>
              <td class="text-start">
                <input type="text" name="owner_name" class="form-control kana25 text-start"
                       value="{{ old('owner_name') }}" required maxlength="255">
              </td>
            </tr>
            <t>
              <th class="text-start ps-2 bg-ccc">メールアドレス</th>
              <td class="text-start">
                <input type="email" name="email" class="form-control kana25 text-start"
                       value="{{ old('email') }}" required maxlength="255" autocomplete="username">
              </td>
            </tr>
            <tr>
              <th class="text-start ps-2 bg-ccc">パスワード</th>
              <td class="text-start">
                <input type="password" name="password" class="form-control kana20 text-start"
                       required minlength="8" maxlength="255" autocomplete="new-password">
              </td>
            </tr>
            <tr>
              <th class="text-start ps-2 bg-ccc">パスワード（確認）</th>
              <td class="text-start">
                <input type="password" name="password_confirmation" class="form-control kana20 text-start"
                       required minlength="8" maxlength="255" autocomplete="new-password">
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      {{-- ===== Step 2 ===== --}}
      <div id="step-2" class="d-none">
       <div class="apply-steps" aria-label="申込みステップ">
          <div class="apply-step">STEP1・基本情報</div>
          <div class="apply-step is-active">STEP2・お申込み内容</div>         
          <div class="apply-step">STEP3・内容確認</div>
        </div>
        <table class="table table-base mt-5 mb-5 align-middle" style="width: 320px;">
          <tbody>
            <tr>
              <th class="bg-ccc" style="width: 100px;">お申込内容</th>
              <td class="text-start">
                <select name="plan" class="form-select" required style="width: 200px;">
                  <option value="">（選択してください）</option>
                  {{-- たたき台：文言は後でUI担当が調整 --}}
                  <option value="プランA" @selected(old('plan')==='プランA')>プランA</option>
                  <option value="プランB" @selected(old('plan')==='プランB')>プランB</option>
                  <option value="プランC" @selected(old('plan')==='プランC')>プランC</option>
                  <option value="プランD" @selected(old('plan')==='プランD')>プランD</option>
                </select>
              </td>
            </tr>
            <tr>
              <th class="bg-ccc">お支払方法</th>
              <td class="text-start">
                <select name="payment_method" class="form-select" required style="width: 200px;">
                  <option value="">（選択してください）</option>
                  <option value="クレジットカード" @selected(old('payment_method')==='クレジットカード')>クレジットカード</option>
                  <option value="銀行振込" @selected(old('payment_method')==='銀行振込')>銀行振込</option>
                </select>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      {{-- ===== Step 3 ===== --}}
      <div id="step-3" class="d-none">
        <div class="apply-steps text-center" aria-label="申込みステップ">
          <div class="apply-step">STEP1・基本情報</div>
          <div class="apply-step">STEP2・お申込み内容</div>          
          <div class="apply-step is-active">STEP3・内容確認</div>
        </div>

        <div class="alert alert-secondary">
          入力内容を確認してください（この画面はたたき台です。UI担当が整えます）。
        </div>

        <table class="table table-base mb-3 align-middle" style="width: 480px;">
          <tbody>
            <tr><th class="text-start ps-2 bg-ccc" style="width:100px;">会社名</th><td id="confirm-company" class="text-start ps-2"></td></tr>
            <tr><th class="text-start ps-2 bg-ccc">代表者名</th><td id="confirm-owner" class="text-start ps-2"></td></tr>
            <tr><th class="text-start ps-2 bg-ccc">メールアドレス</th><td id="confirm-email" class="text-start ps-2"></td></tr>
            <tr><th class="text-start ps-2 bg-ccc">申込内容</th><td id="confirm-plan" class="text-start ps-2"></td></tr>
            <tr><th class="text-start ps-2 bg-ccc">支払方法</th><td id="confirm-payment" class="text-start ps-2"></td></tr>
          </tbody>
        </table>

        <div class="alert alert-info">
          お支払い手続きURL：
          @if (!empty($paymentUrl))
            <a href="{{ $paymentUrl }}" target="_blank" rel="noopener noreferrer">{{ $paymentUrl }}</a>
          @else
            <span class="text-muted">（未設定：.env に BILLING_ROBO_PAYMENT_URL を設定してください）</span>
          @endif
        </div>
      </div>

      <hr>

      <div class="btn-footer mb-3">
        <div class="d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center gap-2">
            <a class="btn-base-blue" href="{{ route('login') }}">ログインへ戻る</a>
          </div>
          <div class="d-flex align-items-center gap-2">
            <button type="button" id="btn-prev" class="btn-base-blue d-none">戻 る</button>
            <button type="button" id="btn-next" class="btn-base-blue">次 へ</button>
            <button type="submit" id="btn-submit" class="btn-base-blue d-none">この内容で申し込む</button>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script>
  (function () {
    const steps = [1,2,3];
    let current = 1;

    const elStep = (n) => document.getElementById(`step-${n}`);
    const btnPrev = document.getElementById('btn-prev');
    const btnNext = document.getElementById('btn-next');
    const btnSubmit = document.getElementById('btn-submit');

    const form = document.getElementById('signup-form');

    const show = (n) => {
      current = n;
      steps.forEach(s => {
        elStep(s).classList.toggle('d-none', s !== n);
      });
      btnPrev.classList.toggle('d-none', n === 1);
      btnNext.classList.toggle('d-none', n === 3);
      btnSubmit.classList.toggle('d-none', n !== 3);

      if (n === 3) {
        // confirm 表示
        const get = (name) => {
          const el = form.querySelector(`[name="${name}"]`);
          return el ? (el.value || '') : '';
        };
        document.getElementById('confirm-company').textContent = get('company_name');
        document.getElementById('confirm-owner').textContent = get('owner_name');
        document.getElementById('confirm-email').textContent = get('email');
        document.getElementById('confirm-plan').textContent = get('plan');
        document.getElementById('confirm-payment').textContent = get('payment_method');
      }
    };

    const validateStep1 = () => {
      const required = ['company_name','owner_name','email','password','password_confirmation'];
      for (const k of required) {
        const el = form.querySelector(`[name="${k}"]`);
        if (!el || !el.value) return false;
      }
      if (form.querySelector('[name="password"]').value !== form.querySelector('[name="password_confirmation"]').value) {
        return false;
      }
      return true;
    };

    const validateStep2 = () => {
      const required = ['plan','payment_method'];
      for (const k of required) {
        const el = form.querySelector(`[name="${k}"]`);
        if (!el || !el.value) return false;
      }
      return true;
    };

    btnPrev.addEventListener('click', () => show(Math.max(1, current - 1)));
    btnNext.addEventListener('click', () => {
      if (current === 1 && !validateStep1()) return;
      if (current === 2 && !validateStep2()) return;
      show(Math.min(3, current + 1));
    });

    // 初期表示
    show(1);
  })();
</script>
@endpush
@endsection
