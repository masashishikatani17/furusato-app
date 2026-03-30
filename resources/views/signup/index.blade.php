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

    <form id="signup-form" method="POST" action="{{ route('signup.submit') }}" novalidate>
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
              <th class="text-start ps-2 bg-ccc">支店名</th>
              <td class="text-start">
                <input type="text" name="branch_name" class="form-control kana25 text-start"
                       value="{{ old('branch_name') }}" required maxlength="255">
              </td>
            </tr>
            <tr>
              <th class="text-start ps-2 bg-ccc">代表者名</th>
              <td class="text-start">
                <input type="text" name="owner_name" class="form-control kana25 text-start"
                       value="{{ old('owner_name') }}" required maxlength="255">
              </td>
            </tr>
            <tr>
              <th class="text-start ps-2 bg-ccc">メールアドレス</th>
              <td class="text-start">
                <input type="email" name="email" class="form-control kana25 text-start"
                       value="{{ old('email') }}" required maxlength="255" autocomplete="username">
              </td>
            </tr>
            <tr>
              <th class="text-start ps-2 bg-ccc">電話番号</th>
              <td class="text-start">
                <input type="text" name="tel" class="form-control kana25 text-start"
                       value="{{ old('tel') }}" maxlength="15" inputmode="tel" autocomplete="tel"
                       placeholder="例）0312345678">
                <div class="text-muted small mt-1">※ クレジットカードを選択する場合は必須です（10桁または11桁）</div>
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
        <table class="table table-base mt-5 mb-5 align-middle" style="width: 420px;">
          <tbody>
            <tr>
              <th class="bg-ccc" style="width: 100px;">お申込内容</th>
              <td class="text-start">
                <div class="fw-bold">5人プラン（年額30,000円）</div>
                <div class="text-muted small">※ 1口 = 5ユーザー（例：13人利用 → 3口（15席））</div>
              </td>
            </tr>
            <tr>
              <th class="bg-ccc">口数</th>
              <td class="text-start">
                <input type="number" name="quantity" class="form-control text-start"
                       value="{{ old('quantity', 1) }}" min="1" max="999" required style="width: 200px;">
                <div class="text-muted small mt-1">席数：<span id="preview-seats">5</span> ／ 年額：<span id="preview-price">30,000</span>円</div>
              </td>
            </tr>
            <tr>
              <th class="bg-ccc">お支払方法</th>
              <td class="text-start">
                <select id="payment-method" name="payment_method" class="form-select" required style="width: 200px;">
                  <option value="">（選択してください）</option>
                  <option value="クレジットカード" @selected(old('payment_method')==='クレジットカード')>クレジットカード</option>
                  <option value="銀行振込" @selected(old('payment_method')==='銀行振込')>銀行振込</option>
                </select>
                <div class="text-muted small mt-1">
                  クレジットカードを選択した場合は、申込後にカード登録画面へ進みます。
                </div>
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
        <table class="table table-base mb-3 align-middle" style="width: 480px;">
          <tbody>
            <tr><th class="text-start ps-2 bg-ccc" style="width:100px;">会社名</th><td id="confirm-company" class="text-start ps-2"></td></tr>
            <tr><th class="text-start ps-2 bg-ccc">支店名</th><td id="confirm-branch" class="text-start ps-2"></td></tr>
            <tr><th class="text-start ps-2 bg-ccc">代表者名</th><td id="confirm-owner" class="text-start ps-2"></td></tr>
            <tr><th class="text-start ps-2 bg-ccc">メールアドレス</th><td id="confirm-email" class="text-start ps-2"></td></tr>
            <tr><th class="text-start ps-2 bg-ccc">電話番号</th><td id="confirm-tel" class="text-start ps-2"></td></tr>
            <tr><th class="text-start ps-2 bg-ccc">申込内容</th><td id="confirm-plan" class="text-start ps-2"></td></tr>
            <tr><th class="text-start ps-2 bg-ccc">口数</th><td id="confirm-quantity" class="text-start ps-2"></td></tr>
            <tr><th class="text-start ps-2 bg-ccc">利用人数（席数）</th><td id="confirm-seats" class="text-start ps-2"></td></tr>
            <tr><th class="text-start ps-2 bg-ccc">年額合計</th><td id="confirm-price" class="text-start ps-2"></td></tr>
            <tr><th class="text-start ps-2 bg-ccc">支払方法</th><td id="confirm-payment" class="text-start ps-2"></td></tr>
          </tbody>
        </table>

        <div id="confirm-payment-guide" class="alert alert-info"></div>
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
    const paymentUrl = @json($paymentUrl ?? '');

    const elStep = (n) => document.getElementById(`step-${n}`);
    const btnPrev = document.getElementById('btn-prev');
    const btnNext = document.getElementById('btn-next');
    const btnSubmit = document.getElementById('btn-submit');

    const form = document.getElementById('signup-form');
    const paymentMethodEl = document.getElementById('payment-method');

    const isCredit = () => (paymentMethodEl?.value || '') === 'クレジットカード';
    const normalizeTel = (value) => String(value || '').replace(/\D+/g, '');

    // 指定step内の「最初に invalid な要素」を返す（なければnull）
    const firstInvalidInStep = (stepNo) => {
      const root = document.getElementById(`step-${stepNo}`);
      if (!root) return null;

      // input/select/textarea を広めに見る（hiddenは除外）
      const fields = root.querySelectorAll('input, select, textarea');
      for (const el of fields) {
        if (!el) continue;
        if (el.type === 'hidden') continue;
        if (el.disabled) continue;
        if (typeof el.checkValidity === 'function' && !el.checkValidity()) {
          return el;
        }
      }
      return null;
    };

    // Step1の追加検証（パスワード一致・長さ）
    const validateStep1OrFocus = () => {
      const inv = firstInvalidInStep(1);
      if (inv) {
        show(1);
        if (typeof inv.reportValidity === 'function') inv.reportValidity();
        inv.focus();
        return false;
      }

      const pw = form.querySelector('[name="password"]');
      const pwc = form.querySelector('[name="password_confirmation"]');
      const pwVal = pw ? String(pw.value || '') : '';
      const pwcVal = pwc ? String(pwc.value || '') : '';

      if (pwVal.length < 8) {
        show(1);
        if (pw) {
          pw.setCustomValidity('パスワードは8文字以上で入力してください。');
          pw.reportValidity?.();
          pw.focus();
          pw.setCustomValidity('');
        }
        return false;
      }
      if (pwVal !== pwcVal) {
        show(1);
        if (pwc) {
          pwc.setCustomValidity('パスワード（確認）が一致しません。');
          pwc.reportValidity?.();
          pwc.focus();
          pwc.setCustomValidity('');
        }
        return false;
      }
      return true;
    };

    // Step2の追加検証（quantity範囲 + クレカ時の電話番号）
    const validateStep2OrFocus = () => {
      const inv = firstInvalidInStep(2);
      if (inv) {
        show(2);
        inv.reportValidity?.();
        inv.focus();
        return false;
      }
      const qEl = form.querySelector('[name="quantity"]');
      const q = qEl ? Number(qEl.value || 0) : 0;
      if (!Number.isFinite(q) || q < 1 || q > 999) {
        show(2);
        if (qEl) {
          qEl.setCustomValidity('口数は1〜999の範囲で入力してください。');
          qEl.reportValidity?.();
          qEl.focus();
          qEl.setCustomValidity('');
        }
        return false;
      }

      if (isCredit()) {
        const telEl = form.querySelector('[name="tel"]');
        const tel = normalizeTel(telEl ? telEl.value : '');
        if (!/^\d{10,11}$/.test(tel)) {
          show(1);
          if (telEl) {
            telEl.setCustomValidity('電話番号は10桁または11桁の数字で入力してください。');
            telEl.reportValidity?.();
            telEl.focus();
            telEl.setCustomValidity('');
          }
          return false;
        }
      }

      return true;
    };

    const yen = (n) => {
      try { return Number(n).toLocaleString('ja-JP'); } catch (e) { return String(n); }
    };

    const updatePreview = () => {
      const qEl = form.querySelector('[name="quantity"]');
      const q = qEl ? Number(qEl.value || 0) : 0;
      const quantity = (!Number.isFinite(q) || q < 1) ? 1 : Math.min(999, Math.floor(q));
      const seats = quantity * 5;
      const price = quantity * 30000;
      const sEl = document.getElementById('preview-seats');
      const pEl = document.getElementById('preview-price');
      if (sEl) sEl.textContent = yen(seats);
      if (pEl) pEl.textContent = yen(price);
    };

    const show = (n) => {
      current = n;
      steps.forEach(s => {
        elStep(s).classList.toggle('d-none', s !== n);
      });
      btnPrev.classList.toggle('d-none', n === 1);
      btnNext.classList.toggle('d-none', n === 3);
      btnSubmit.classList.toggle('d-none', n !== 3);

      if (n === 3) {
        const get = (name) => {
          const el = form.querySelector(`[name="${name}"]`);
          return el ? (el.value || '') : '';
        };
        document.getElementById('confirm-company').textContent = get('company_name');
        document.getElementById('confirm-branch').textContent = get('branch_name');
        document.getElementById('confirm-owner').textContent = get('owner_name');
        document.getElementById('confirm-email').textContent = get('email');
        document.getElementById('confirm-tel').textContent = get('tel');
        document.getElementById('confirm-plan').textContent = '5人プラン（年額30,000円）';
        const q = Number(get('quantity') || 0);
        const quantity = (!Number.isFinite(q) || q < 1) ? 1 : Math.min(999, Math.floor(q));
        document.getElementById('confirm-quantity').textContent = String(quantity);
        document.getElementById('confirm-seats').textContent = yen(quantity * 5);
        document.getElementById('confirm-price').textContent = yen(quantity * 30000) + '円';
        const payment = get('payment_method');
        document.getElementById('confirm-payment').textContent = payment;

        const guideEl = document.getElementById('confirm-payment-guide');
        if (guideEl) {
          if (payment === 'クレジットカード') {
            guideEl.innerHTML = 'お申し込み後、クレジットカード登録画面へ進みます。<br>カード登録完了後に年次定期の請求情報を作成します。';
          } else if (payment === '銀行振込') {
            guideEl.innerHTML = paymentUrl
              ? `お申し込み後、銀行振込の年次定期請求情報を作成します。<br>参考URL：<a href="${paymentUrl}" target="_blank" rel="noopener noreferrer">${paymentUrl}</a>`
              : 'お申し込み後、銀行振込の年次定期請求情報を作成します。';
          } else {
            guideEl.textContent = '';
          }
        }
      }
    };

    btnPrev.addEventListener('click', () => show(Math.max(1, current - 1)));
    btnNext.addEventListener('click', () => {
      if (current === 1 && !validateStep1OrFocus()) return;
      if (current === 2 && !validateStep2OrFocus()) return;
      show(Math.min(3, current + 1));
    });

    // 初期表示
    show(1);

    // 口数プレビュー
    const qEl = form.querySelector('[name="quantity"]');
    if (qEl) {
      qEl.addEventListener('input', updatePreview);
      updatePreview();
    }

    // submit時：ブラウザ標準バリデーション（focus不可エラー）を避けるために
    // ここで必ず Step1/2 を検証し、NGなら該当stepへ戻してフォーカスする
    form.addEventListener('submit', (e) => {
      if (!validateStep1OrFocus()) {
        e.preventDefault();
        return;
      }
      if (!validateStep2OrFocus()) {
        e.preventDefault();
        return;
      }
    });
  })();
</script>
@endpush
@endsection