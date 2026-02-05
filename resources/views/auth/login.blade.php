{{-- resources/views/auth/login.blade.php --}}
@extends('layouts.min')

@section('title', 'ログイン')

@section('content')
<div class="container-blue" style="max-width: 480px; margin: 0 auto;">
  <div class="card-header d-flex align-items-start">
      <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
      <h0 class="mb-0 ms-3 mt-2"> ログイン</h0>
  </div>

  <div class="wrapper">
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

      <form method="POST" action="{{ route('login') }}">
        @csrf

        <table class="table table-base mb-3 align-middle" style="width: 400px;">
          <tbody>
            <tr style="height: 40px;">
              <th class="text-start ps-2 bg-ccc" style="width: 100px;">メールアドレス</th>
              <td class="text-start" style="width: 300px;">
                <input
                  id="email"
                  type="email"
                  name="email"
                  class="form-control text-start"
                  style="width: 290px;"
                  value="{{ old('email') }}"
                  required
                  autofocus
                  autocomplete="username"
                >
              </td>
            </tr>
            <tr style="height: 40px;">
              <th class="text-start ps-2 bg-ccc">パスワード</th>
              <td class="text-start">
                <input
                  id="password"
                  type="password"
                  name="password"
                  class="form-control text-start"
                  style="width: 90px;"
                  required
                  autocomplete="current-password"
                >
              </td>
            </tr>
            <tr style="height: 40px;">
              <th class="text-start ps-2 bg-ccc">パスワード保持</th>
              <td class="text-start">
                <div class="form-check pt-1 pb-0">
                  <input class="form-check-input" type="checkbox" id="remember_me" name="remember">
                  <label class="form-check-label text-start" for="remember_me">ログイン状態を保持</label>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <hr>

        <div class="btn-footer mb-3">
          <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
              @if (Route::has('password.request'))
                <a class="btn-base-blue" href="{{ route('password.request') }}">パスワードを忘れた場合</a>
              @else
                <span></span>
              @endif
            </div>
            <div class="d-flex align-items-center gap-2">
              <button type="submit" class="btn-base-blue">ログイン</button>
            </div>
          </div>
        </div>
      </form>
  </div>
</div>
@endsection