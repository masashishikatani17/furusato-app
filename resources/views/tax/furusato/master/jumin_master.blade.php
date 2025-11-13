@extends('layouts.min')
@section('content')
<div class="container-grey mt-2" style="width: 920px;">
  <div class="card-header d-flex align-items-start">
    <img src="{{ asset('storage/images/kado_lefttop_m.jpg') }}" alt="…">
    <hb class="mb-0 mt-2">住民税率マスター</hb>
  </div>
  <div class="card-body">
    <div class="wrapper">
      @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @endif
      @if ($errors->any())
        <div class="alert alert-danger">
          <ul class="mb-0">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif
      <form method="POST" action="{{ route('furusato.master.jumin.save') }}" id="jumin-master-form">
        @csrf
        <input type="hidden" name="data_id" value="{{ $dataId }}">
        {{-- 戻る＝保存＋再計算の上でマスターTOPへ遷移 --}}
        <input type="hidden" name="redirect_to" value="master">
      <table class="table-base table-bordered align-middle">
        <thead>
          <tr>
            <th class="text-center" colspan="2" rowspan="2">区 分</th>
            <th class="text-center" colspan="2">指定都市</th>
            <th class="text-center" colspan="2">指定都市以外</th>
            <th class="text-center" rowspan="2">備 考</th>
          </tr>
          <tr>
            <th class="text-center th-ddd">市</th>
            <th class="text-center th-ddd">県</th>
            <th class="text-center th-ddd">市</th>
            <th class="text-center th-ddd">県</th>
          </tr>
        </thead>
        <tbody>
          @php
            $fmt = static fn (?float $v): string =>
              $v === null ? '' : rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');

            // rates をキー化（category|sub|remarkKey）
            $keyOf = static function($cat, $sub = null, $remarkContains = null) {
              return implode('|', [
                (string)$cat,
                (string)($sub ?? ''),
                (string)($remarkContains ?? ''),
              ]);
            };
            $map = [];
            foreach ($rates as $r) {
              $map[$keyOf($r->category, $r->sub_category, $r->remark)] = $r;
            }
            // remark の“以下/超”だけは包含で拾えるようヘルパ
            $pick = static function(string $cat, ?string $sub=null, ?string $remarkContains=null) use ($rates) {
              $alts = ($cat === '総合課税') ? ['総合課税','総合'] : [$cat];
              return $rates->first(function($r) use($alts,$sub,$remarkContains){
                if (!in_array((string)$r->category, $alts, true)) return false;
                if (($r->sub_category ?? null) !== $sub) return false;
                if ($remarkContains === null) return true;
                $rem = (string)($r->remark ?? '');
                return $rem !== '' && str_contains($rem, $remarkContains);
              });
            };
            /**
             * 1行レンダ
             * - editable 行  ：name="rates[i][...]" を持つ input text
             * - readonly 行 ：POST しない（name を付けない）＆見た目だけ表示
             */
            $row = function($i, $r, $readonly=false) use($fmt){
              $cityS = $fmt($r?->city_specified      ?? null);
              $prefS = $fmt($r?->pref_specified      ?? null);
              $cityN = $fmt($r?->city_non_specified  ?? null);
              $prefN = $fmt($r?->pref_non_specified  ?? null);
              $cat   = (string)($r?->category ?? '');
              $sub   = $r?->sub_category ?? null;
              $sort  = $r?->sort ?? 0;
              $remark= (string)($r?->remark ?? '');

              if ($readonly) {
                  // 読み取り専用行：POST せず、value だけを表示
                  return <<<HTML
                    <td class="text-end bg-light">
                      <input type="text"
                             class="form-control text-end"
                             value="{$cityS}"
                             readonly>
                    </td>
                    <td class="text-end bg-light">
                      <input type="text"
                             class="form-control text-end"
                             value="{$prefS}"
                             readonly>
                    </td>
                    <td class="text-end bg-light">
                      <input type="text"
                             class="form-control text-end"
                             value="{$cityN}"
                             readonly>
                    </td>
                    <td class="text-end bg-light">
                      <input type="text"
                             class="form-control text-end"
                             value="{$prefN}"
                             readonly>
                    </td>
                    <td class="text-start">
                      <span>{$remark}</span>
                    </td>
                  HTML;
              }

              // 編集可能行：data_id ごとの保存対象
              return <<<HTML
                <td class="text-end">
                  <input type="text"
                         name="rates[{$i}][city_specified]"
                         class="form-control text-end"
                         value="{$cityS}">
                </td>
                <td class="text-end">
                  <input type="text"
                         name="rates[{$i}][pref_specified]"
                         class="form-control text-end"
                         value="{$prefS}">
                </td>
                <td class="text-end">
                  <input type="text"
                         name="rates[{$i}][city_non_specified]"
                         class="form-control text-end"
                         value="{$cityN}">
                </td>
                <td class="text-end">
                  <input type="text"
                         name="rates[{$i}][pref_non_specified]"
                         class="form-control text-end"
                         value="{$prefN}">
                </td>
                <td class="text-start">
                  <input type="hidden" name="rates[{$i}][sort]" value="{$sort}">
                  <input type="hidden" name="rates[{$i}][category]" value="{$cat}">
                  <input type="hidden" name="rates[{$i}][sub_category]" value="{$sub}">
                  <span>{$remark}</span>
                </td>
              HTML;
            };
            $i = 0;
          @endphp

          {{-- 1) 総合課税（1行） --}}
          @php $r = $pick('総合課税'); @endphp
          <tr>
            <th class="text-start" colspan="2">総合課税</th>
            {!! $row($i++, $r) !!}
          </tr>

          {{-- 2) 短期譲渡（2行：一般/軽減、左セルrowspan=2） --}}
          @php $r1 = $pick('短期譲渡', '一般'); $r2 = $pick('短期譲渡', '軽減'); @endphp
          <tr>
            <th class="text-start" rowspan="2">短期譲渡</th>
            <th class="text-center th-ddd">一般</th>
            {!! $row($i++, $r1) !!}
          </tr>
          <tr>
            <th class="text-center th-ddd">軽減</th>
            {!! $row($i++, $r2) !!}
          </tr>

          {{-- 3) 長期譲渡（5行：一般、特定(以下/超)、軽課(以下/超) ／ 左セルrowspan=5） --}}
          @php
            $rA = $pick('長期譲渡', '一般');
            $rB1= $pick('長期譲渡', '特定', '以下');
            $rB2= $pick('長期譲渡', '特定', '超');
            $rC1= $pick('長期譲渡', '軽課', '以下');
            $rC2= $pick('長期譲渡', '軽課', '超');
          @endphp
          <tr>
            <th class="text-start" rowspan="5">長期譲渡</th>
            <th class="text-center th-ddd">一般</th>
            {!! $row($i++, $rA) !!}
          </tr>
          <tr>
            <th class="text-center th-ddd" rowspan="2">特定</th>
            {!! $row($i++, $rB1) !!}
          </tr>
          <tr>
            {!! $row($i++, $rB2) !!}
          </tr>
          <tr>
            <th class="text-center th-ddd" rowspan="2">軽課</th>
            {!! $row($i++, $rC1) !!}
          </tr>
          <tr>
            {!! $row($i++, $rC2) !!}
          </tr>

          {{-- 4) 一般株式等の譲渡（1行） --}}
          @php $r = $pick('一般株式等の譲渡'); @endphp
          <tr>
            <th class="text-start" colspan="2">一般株式等の譲渡</th>
            {!! $row($i++, $r) !!}
          </tr>

          {{-- 5) 上場株式等の譲渡（1行） --}}
          @php $r = $pick('上場株式等の譲渡'); @endphp
          <tr>
            <th class="text-start" colspan="2">上場株式等の譲渡</th>
            {!! $row($i++, $r) !!}
          </tr>

          {{-- 6) 上場株式等の配当等（1行） --}}
          @php $r = $pick('上場株式等の配当等'); @endphp
          <tr>
            <th class="text-start" colspan="2">上場株式等の配当等</th>
            {!! $row($i++, $r) !!}
          </tr>

          {{-- 7) 先物取引（1行） --}}
          @php $r = $pick('先物取引'); @endphp
          <tr>
            <th class="text-start" colspan="2">先物取引</th>
            {!! $row($i++, $r) !!}
          </tr>

          {{-- 8) 山林（1行） --}}
          @php $r = $pick('山林'); @endphp
          <tr>
            <th class="text-start" colspan="2">山林</th>
            {!! $row($i++, $r) !!}
          </tr>

          {{-- 9) 退職（1行） --}}
          @php $r = $pick('退職'); @endphp
          <tr>
            <th class="text-start" colspan="2">退職</th>
            {!! $row($i++, $r) !!}
          </tr>

          {{-- 10) 基本控除（読み取り専用） --}}
          @php $r = $pick('基本控除'); @endphp
          <tr>
            <th class="text-start" colspan="2">基本控除</th>
            {!! $row($i++, $r, true) !!}
          </tr>

          {{-- 11) 調整控除（読み取り専用） --}}
          @php $r = $pick('調整控除'); @endphp
          <tr>
            <th class="text-start" colspan="2">調整控除</th>
            {!! $row($i++, $r, true) !!}
          </tr>

          {{-- 12) 特例控除（読み取り専用） --}}
          @php $r = $pick('特例控除'); @endphp
          <tr>
            <th class="text-start" colspan="2">特例控除</th>
            {!! $row($i++, $r, true) !!}
          </tr>
        </tbody>
    </table>

    {{-- 均等割・その他税額（data_id ごとに編集） --}}
    @php
        // Controller から渡される equal（無ければデフォルト）
        $equal = $equal ?? [
            'pref_equal_share_prev'   => 1500,
            'pref_equal_share_curr'   => 1500,
            'muni_equal_share_prev'   => 3500,
            'muni_equal_share_curr'   => 3500,
            'other_taxes_prev'        => 0,
            'other_taxes_curr'        => 0,
        ];
    @endphp

    <div class="mt-3">
      <h5>均等割・その他税額</h5>
      <div class="row">
        <div class="col-md-6">
          <label class="form-label">前期：都道府県 均等割</label>
          <div class="d-flex align-items-center gap-1">
            <input type="text"
                   name="jumin[pref_equal_share_prev]"
                   class="form-control suji7 comma integer_comma text-end"
                   inputmode="decimal"
                   autocomplete="off"
                   value="{{ $equal['pref_equal_share_prev'] }}">
            <span>円</span>
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label">当期：都道府県 均等割</label>
          <div class="d-flex align-items-center gap-1">
            <input type="text"
                   name="jumin[pref_equal_share_curr]"
                   class="form-control suji7 comma integer_comma text-end"
                   inputmode="decimal"
                   autocomplete="off"
                   value="{{ $equal['pref_equal_share_curr'] }}">
            <span>円</span>
          </div>
        </div>
      </div>
      <div class="row mt-2">
        <div class="col-md-6">
          <label class="form-label">前期：市区町村 均等割</label>
          <div class="d-flex align-items-center gap-1">
            <input type="text"
                   name="jumin[muni_equal_share_prev]"
                   class="form-control suji7 comma integer_comma text-end"
                   inputmode="decimal"
                   autocomplete="off"
                   value="{{ $equal['muni_equal_share_prev'] }}">
            <span>円</span>
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label">当期：市区町村 均等割</label>
          <div class="d-flex align-items-center gap-1">
            <input type="text"
                   name="jumin[muni_equal_share_curr]"
                   class="form-control suji7 comma integer_comma text-end"
                   inputmode="decimal"
                   autocomplete="off"
                   value="{{ $equal['muni_equal_share_curr'] }}">
            <span>円</span>
          </div>
        </div>
      </div>
      <div class="row mt-2">
        <div class="col-md-6">
          <label class="form-label">前期：その他の税額</label>
          <div class="d-flex align-items-center gap-1">
            <input type="text"
                   name="jumin[other_taxes_prev]"
                   class="form-control suji7 comma integer_comma text-end"
                   inputmode="decimal"
                   autocomplete="off"
                   value="{{ $equal['other_taxes_prev'] }}">
            <span>円</span>
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label">当期：その他の税額</label>
          <div class="d-flex align-items-center gap-1">
            <input type="text"
                   name="jumin[other_taxes_curr]"
                   class="form-control suji7 comma integer_comma text-end"
                   inputmode="decimal"
                   autocomplete="off"
                   value="{{ $equal['other_taxes_curr'] }}">
            <span>円</span>
          </div>
        </div>
      </div>
    </div>
      <div class="d-flex justify-content-end align-items-center mt-3">
        {{-- 「戻る」＝ 保存＋再計算の上でマスター画面へ遷移 --}}
        <button type="submit" class="btn-base-blue">戻 る</button>
      </div>
      </form>
    </div>
  </div>
</div>
@endsection