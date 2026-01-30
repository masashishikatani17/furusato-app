{{-- resources/views/auth/forgot-password.blade.php --}}
@extends('layouts.min')

@section('title', 'パスワード再設定')

@section('content')
<div class="container-blue" style="max-width: 760px; margin: 0 auto;">
  <div class="card-header d-flex justify-content-between gap-2">
    <div>
      <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
      <h0 class="mb-0 ms-3 mt-2"> パスワード再設定</h0>
    </div>
    <div class="d-flex me-3 mt-2"></div>
  </div>

  <div class="card-body">
    <div class="border rounded p-3" style="max-width: 560px; margin: 0 auto;">

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

        <table class="table table-base mb-3 align-middle" style="width: 100%;">
          <tbody>
            <tr style="height: 44px;">
              <th class="text-start" style="width: 160px; background-color:#d0e5f4;">Email</th>
              <td>
                <input
                  id="email"
                  type="email"
                  name="email"
                  class="form-control"
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
              <a class="btn-base-blue" href="{{ route('login') }}">戻 る</a>
            </div>
            <div class="d-flex align-items-center gap-2">
              <button type="submit" class="btn-base-blue">送 信</button>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection