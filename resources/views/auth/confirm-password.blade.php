{{-- resources/views/auth/confirm-password.blade.php --}}
@extends('layouts.min')

@section('title', 'パスワード確認')

@section('content')
<div class="container-blue" style="max-width: 760px; margin: 0 auto;">
  <div class="card-header d-flex justify-content-between gap-2">
    <div>
      <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
      <h0 class="mb-0 ms-3 mt-2"> パスワード確認</h0>
    </div>
    <div class="d-flex me-3 mt-2"></div>
  </div>

  <div class="card-body">
    <div class="border rounded p-3" style="max-width: 560px; margin: 0 auto;">
      <div class="mb-3">
        重要な操作の前に、パスワードを再入力してください。
      </div>

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

      <form method="POST" action="{{ route('password.confirm.store') }}">
        @csrf

        <table class="table table-base mb-3 align-middle" style="width: 100%;">
          <tbody>
            <tr style="height: 44px;">
              <th class="text-start" style="width: 160px; background-color:#d0e5f4;">Password</th>
              <td>
                <input
                  id="password"
                  type="password"
                  name="password"
                  class="form-control"
                  required
                  autofocus
                  autocomplete="current-password"
                >
              </td>
            </tr>
          </tbody>
        </table>

        <hr>

        <div class="btn-footer">
          <div class="d-flex justify-content-end align-items-center gap-2">
            <button type="submit" class="btn-base-blue">確 認</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection