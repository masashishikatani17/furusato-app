{{-- resources/views/pdf/furusato_bundle.blade.php --}}
@extends('pdf.layouts.print_bundle')

@section('title','ふるさと納税 帳票一括')

@section('head')
  {{-- 各帳票から抽出した style をまとめて投入 --}}
  @if (!empty($bundle_styles) && is_array($bundle_styles))
    @foreach ($bundle_styles as $styleTag)
      {!! $styleTag !!}
    @endforeach
  @endif

  <style>
    /* ページ区切りのみ bundle の責務 */
    /* bundle では各ページの <main.page> が page-break を持つ想定 */
  </style>
@endsection

@section('content')
  @foreach (($pages ?? []) as $html)
    {!! $html !!}
  @endforeach
@endsection