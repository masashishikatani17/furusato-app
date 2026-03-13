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
                  <option value="キャッシュカード" @selected(old('payment_method')==='キャッシュカード')>キャッシュカード</option>
                  <option value="銀行振込" @selected(old('payment_method')==='銀行振込')>銀行振込</option>
                </select>
              </td>
            </tr>
            <tr class="debit-field d-none">
              <th class="bg-ccc">口座種別</th>
              <td class="text-start">
                <select name="bank_account_type" class="form-select" style="width: 200px;">
                  <option value="">（選択してください）</option>
                  <option value="1" @selected(old('bank_account_type')==='1')>普通</option>
                  <option value="2" @selected(old('bank_account_type')==='2')>当座</option>
                </select>
              </td>
            </tr>
            <tr class="debit-field d-none">
              <th class="bg-ccc">銀行コード</th>
              <td class="text-start">
                <input type="text" name="bank_code" class="form-control text-start" value="{{ old('bank_code') }}" inputmode="numeric" maxlength="4" style="width: 200px;">
              </td>
            </tr>
            <tr class="debit-field d-none">
              <th class="bg-ccc">支店コード</th>
              <td class="text-start">
                <input type="text" name="branch_code" class="form-control text-start" value="{{ old('branch_code') }}" inputmode="numeric" maxlength="5" style="width: 200px;">
                <div class="text-muted small">通常銀行:3桁 / ゆうちょ銀行(9900):5桁</div>
              </td>
            </tr>
            <tr class="debit-field d-none">
              <th class="bg-ccc">口座番号</th>
              <td class="text-start">
                <input type="text" name="bank_account_number" class="form-control text-start" value="{{ old('bank_account_number') }}" inputmode="numeric" maxlength="8" style="width: 200px;">
                <div class="text-muted small">通常銀行:7桁 / ゆうちょ銀行(9900):8桁</div>
              </td>
            </tr>
            <tr class="debit-field d-none">
              <th class="bg-ccc">口座名義</th>
              <td class="text-start">
                <input type="text" name="bank_account_name" class="form-control text-start" value="{{ old('bank_account_name') }}" maxlength="30" style="width: 300px;">
                <div class="text-muted small">半角英大文字 / 半角カナ / 数字 / 空白 / 記号(- . / ( ) &)</div>
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
            <tr><th class="text-start ps-2 bg-ccc">申込内容</th><td id="confirm-plan" class="text-start ps-2"></td></tr>
            <tr><th class="text-start ps-2 bg-ccc">口数</th><td id="confirm-quantity" class="text-start ps-2"></td></tr>
            <tr><th class="text-start ps-2 bg-ccc">利用人数（席数）</th><td id="confirm-seats" class="text-start ps-2"></td></tr>
            <tr><th class="text-start ps-2 bg-ccc">年額合計</th><td id="confirm-price" class="text-start ps-2"></td></tr>
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
    const paymentMethodEl = document.getElementById('payment-method');

    const isDebit = () => (paymentMethodEl?.value || '') === 'キャッシュカード';

    const syncDebitFieldVisibility = () => {
      const debitRows = form.querySelectorAll('.debit-field');
      const show = isDebit();
      debitRows.forEach((row) => row.classList.toggle('d-none', !show));

      const requiredNames = ['bank_account_type', 'bank_code', 'branch_code', 'bank_account_number', 'bank_account_name'];
      requiredNames.forEach((name) => {
        const el = form.querySelector(`[name="${name}"]`);
        if (!el) return;
        el.toggleAttribute('required', show);
      });
    };

    // 指定step内の「最初に invalid な要素」を返す（なければnull）
    const firstInvalidInStep = (stepNo) => {
      const root = document.getElementById(`step-${stepNo}`);
      if (!root) return null;

      // input/select/textarea を広めに見る（hiddenは除外）
      const fields = root.querySelectorAll('input, select, textarea');
      for (const el of fields) {
        if (!el) continue;
        if (el.type === 'hidden') continue;
        // disabled は対象外
        if (el.disabled) continue;
        // required/minlength/type=email など HTML属性の妥当性チェックを利用
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
        // reportValidity でブラウザのメッセージを表示
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

    // Step2の追加検証（quantity範囲）
    const validateStep2OrFocus = () => {
      syncDebitFieldVisibility();
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

      if (isDebit()) {
        const bankCode = String((form.querySelector('[name="bank_code"]')?.value || '')).trim();
        const branchCode = String((form.querySelector('[name="branch_code"]')?.value || '')).trim();
        const accountNumber = String((form.querySelector('[name="bank_account_number"]')?.value || '')).trim();
        const expectedBranch = bankCode === '9900' ? 5 : 3;
        const expectedAccount = bankCode === '9900' ? 8 : 7;

        if (branchCode.length !== expectedBranch) {
          const el = form.querySelector('[name="branch_code"]');
          el?.setCustomValidity(bankCode === '9900' ? 'ゆうちょ銀行の支店コードは5桁で入力してください。' : '支店コードは3桁で入力してください。');
          el?.reportValidity?.();
          el?.focus?.();
          el?.setCustomValidity('');
          return false;
        }

        if (accountNumber.length !== expectedAccount) {
          const el = form.querySelector('[name="bank_account_number"]');
          el?.setCustomValidity(bankCode === '9900' ? 'ゆうちょ銀行の口座番号は8桁で入力してください。' : '口座番号は7桁で入力してください。');
          el?.reportValidity?.();
          el?.focus?.();
          el?.setCustomValidity('');
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
        // confirm 表示
        const get = (name) => {
          const el = form.querySelector(`[name="${name}"]`);
          return el ? (el.value || '') : '';
        };
        document.getElementById('confirm-company').textContent = get('company_name');
        document.getElementById('confirm-branch').textContent = get('branch_name');
        document.getElementById('confirm-owner').textContent = get('owner_name');
        document.getElementById('confirm-email').textContent = get('email');
        document.getElementById('confirm-plan').textContent = '5人プラン（年額30,000円）';
        const q = Number(get('quantity') || 0);
        const quantity = (!Number.isFinite(q) || q < 1) ? 1 : Math.min(999, Math.floor(q));
        document.getElementById('confirm-quantity').textContent = String(quantity);
        document.getElementById('confirm-seats').textContent = yen(quantity * 5);
        document.getElementById('confirm-price').textContent = yen(quantity * 30000) + '円';
        document.getElementById('confirm-payment').textContent = get('payment_method');
      }
    };

    const validateStep1 = () => {
      const required = ['company_name','branch_name','owner_name','email','password','password_confirmation'];
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
      const required = ['quantity','payment_method'];
      for (const k of required) {
        const el = form.querySelector(`[name="${k}"]`);
        if (!el || !el.value) return false;
      }
      const qEl = form.querySelector('[name="quantity"]');
      const q = qEl ? Number(qEl.value || 0) : 0;
      if (!Number.isFinite(q) || q < 1 || q > 999) return false;

      if (isDebit()) {
        const debitRequired = ['bank_account_type', 'bank_code', 'branch_code', 'bank_account_number', 'bank_account_name'];
        for (const key of debitRequired) {
          const el = form.querySelector(`[name="${key}"]`);
          if (!el || !el.value) return false;
        }
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
    syncDebitFieldVisibility();
    show(1);
    // 口数プレビュー
    const qEl = form.querySelector('[name="quantity"]');
    if (qEl) {
      qEl.addEventListener('input', updatePreview);
      updatePreview();
    }

    paymentMethodEl?.addEventListener('change', syncDebitFieldVisibility);

    // submit時：ブラウザ標準バリデーション（focus不可エラー）を避けるために
    // ここで必ず Step1/2 を検証し、NGなら該当stepへ戻してフォーカスする
    form.addEventListener('submit', (e) => {
      // 送信直前に自前検証
      if (!validateStep1OrFocus()) {
        e.preventDefault();
        return;
      }
      if (!validateStep2OrFocus()) {
        e.preventDefault();
        return;
      }
      // ここまで来たらOK（novalidateなので form.submit() は通常通り進む）
    });
  })();
</script>
@endpush
@endsection
