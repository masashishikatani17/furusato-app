{{-- resources/views/auth/forgot-password.blade.php --}}
@extends('layouts.min')

@section('title', 'パスワード再設定')

@section('content')
<div class="container-blue" style="width: 560px;">
  <div class="card-header d-flex justify-content-between gap-2">
    <div class="d-flex align-items-start">
      <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
      <h0 class="mb-0 ms-3 mt-2"> パスワード再設定</h0>
    </div>
    <div class="d-flex me-3 mt-2"></div>
  </div>

  <div class="card-body px-3 py-3">
      <div class="mb-3">
        登録メールアドレスを入力してください。パスワード再設定用のリンクを送信します。
      </div>

      @if (session('status'))
        <div class="alert alert-success mb-3">
          {{ session('status') }}
        </div>
      @endif

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

      <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <table class="table table-base mb-3 align-middle" style="width:440px;">
          <tbody>
            <tr style="height: 40px;">
              <th class="text-center" style="width: 80px; background-color:#d0e5f4;">Email</th>
              <td>
                <input
                  id="email"
                  type="email"
                  name="email"
                  class="form-control kana25"
                  value="{{ old('email') }}"
                  required
                  autofocus
                  autocomplete="username"
                >
              </td>
            </tr>
          </tbody>
        </table>

        <hr>

        <div class="btn-footer">
          <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
              <button type="submit" class="btn-base-green">送 信</button>
            </div>
            <div class="d-flex align-items-center gap-2">
              <a class="btn-base-blue" href="{{ route('login') }}">戻 る</a>
            </div>
          </div>
        </div>
      </form>
    
  </div>
</div>
@endsection