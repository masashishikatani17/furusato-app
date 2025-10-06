@extends('layouts.min')

@section('content')
<div class="container" style="min-width: 960px; max-width: 1080px;">
  <form method="POST" action="{{ route('furusato.save') }}" id="furusato-input-form">
    @csrf
    <input type="hidden" name="data_id" value="{{ $dataId ?? '' }}">
    <input type="hidden" name="redirect_to" value="">

    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0">ふるさと納税：インプット表（v0.4）</h5>
      <div class="d-flex flex-wrap justify-content-end gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="furusato-back-to-syori" formnovalidate>戻る</button>
        <a href="{{ route('furusato.master', $dataId ? ['data_id' => $dataId] : [], false) }}" class="btn btn-outline-secondary btn-sm">マスター</a>
        <button type="submit" class="btn btn-success btn-sm" formnovalidate onclick="this.form.redirect_to.value='';">保存</button>
        <button type="submit" class="btn btn-primary btn-sm" formaction="{{ route('furusato.calc') }}" onclick="this.form.redirect_to.value='';">送信</button>
      </div>
    </div>

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

    @php
      $inputs = $out['inputs'] ?? [];
      $warekiPrevLabel = $warekiPrev ?? '前年';
      $warekiCurrLabel = $warekiCurr ?? '当年';
      $showTokubetsu = in_array((int) ($kihuYear ?? 0), [2024, 2025], true);

      $taxRows = [
        ['key' => 'kazeishotoku', 'label' => '課税所得金額'],
        ['key' => 'zeigaku', 'label' => '算出税額'],
        ['key' => 'haito', 'label' => '配当控除', 'hasBreakdown' => true],
        ['key' => 'jutaku', 'label' => '住宅借入金等特別控除', 'hasBreakdown' => true],
        ['key' => 'seito', 'label' => '政党等寄附金特別控除', 'hasBreakdown' => true],
        ['key' => 'sashihiki', 'label' => '差引税額', 'isTotal' => true],
        ['key' => 'tokubetsu_R6', 'label' => '令和6年度分特別税額控除'],
        ['key' => 'kijun', 'label' => '基準税額'],
        ['key' => 'fukkou', 'label' => '復興特別所得税額'],
        ['key' => 'gokei', 'label' => '税額合計', 'isTotal' => true],
      ];

      if (! $showTokubetsu) {
        $taxRows = array_values(array_filter($taxRows, fn ($row) => $row['key'] !== 'tokubetsu_R6'));
      }

      $sections = [
        [
          'title' => '収入金額等',
          'group' => 'syunyu',
          'rows' => [
            ['key' => 'jigyo_eigyo', 'label' => '営業等'],
            ['key' => 'jigyo_nogyo', 'label' => '農業'],
            ['key' => 'fudosan', 'label' => '不動産'],
            ['key' => 'haito', 'label' => '配当'],
            ['key' => 'kyuyo', 'label' => '給与'],
            ['key' => 'zatsu_nenkin', 'label' => '雑（公的年金等）'],
            ['key' => 'zatsu_gyomu', 'label' => '雑（業務）'],
            ['key' => 'zatsu_sonota', 'label' => '雑（その他）'],
            ['key' => 'joto_tanki', 'label' => '総合譲渡（短期）'],
            ['key' => 'joto_choki', 'label' => '総合譲渡（長期）'],
            ['key' => 'ichiji', 'label' => '一時'],
          ],
        ],
        [
          'title' => '所得金額等',
          'group' => 'shotoku',
          'rows' => [
            ['key' => 'jigyo_eigyo', 'label' => '営業等'],
            ['key' => 'jigyo_nogyo', 'label' => '農業'],
            ['key' => 'fudosan', 'label' => '不動産'],
            ['key' => 'rishi', 'label' => '利子'],
            ['key' => 'haito', 'label' => '配当'],
            ['key' => 'kyuyo', 'label' => '給与'],
            ['key' => 'zatsu_nenkin', 'label' => '雑（公的年金等）'],
            ['key' => 'zatsu_gyomu', 'label' => '雑（業務）'],
            ['key' => 'zatsu_sonota', 'label' => '雑（その他）'],
            ['key' => 'joto_tanki', 'label' => '総合譲渡（短期）'],
            ['key' => 'joto_choki', 'label' => '総合譲渡（長期）'],
            ['key' => 'ichiji', 'label' => '一時'],
            ['key' => 'gokei', 'label' => '合計', 'isTotal' => true, 'hasBreakdown' => false],
          ],
        ],
        [
          'title' => '所得から差し引かれる金額',
          'group' => 'kojo',
          'rows' => [
            ['key' => 'shakaihoken', 'label' => '社会保険料控除'],
            ['key' => 'shokibo', 'label' => '小規模企業共済等掛金控除'],
            ['key' => 'seimei', 'label' => '生命保険料控除'],
            ['key' => 'jishin', 'label' => '地震保険料控除'],
            ['key' => 'kafu', 'label' => '寡婦控除'],
            ['key' => 'hitorioya', 'label' => 'ひとり親控除'],
            ['key' => 'kinrogakusei', 'label' => '勤労学生控除'],
            ['key' => 'shogaisha', 'label' => '障害者控除'],
            ['key' => 'haigusha', 'label' => '配偶者控除'],
            ['key' => 'haigusha_tokubetsu', 'label' => '配偶者特別控除'],
            ['key' => 'fuyo', 'label' => '扶養控除'],
            ['key' => 'kiso', 'label' => '基礎控除'],
            ['key' => 'shokei', 'label' => '所得控除額合計', 'isTotal' => true, 'hasBreakdown' => false],
            ['key' => 'zasson', 'label' => '雑損控除'],
            ['key' => 'iryo', 'label' => '医療費控除'],
            ['key' => 'kifukin', 'label' => '寄附金控除'],
            ['key' => 'gokei', 'label' => '差引所得金額', 'isTotal' => true, 'hasBreakdown' => false],
          ],
        ],
        [
          'title' => '税金の金額',
          'group' => 'tax',
          'rows' => $taxRows,
        ],
      ];
    @endphp

    @php ob_start(); @endphp
      <div>
        <h5 class="card-title mb-3">確定申告書(総合課税)</h5>
        <div class="table-responsive">
          <table class="table table-bordered table-sm align-middle mb-0">
            <thead class="table-light text-center align-middle">
              <tr>
                <th rowspan="2" colspan="4">項目</th>
                <th rowspan="2" colspan="2"></th>
                <th colspan="2">所得税</th>
                <th colspan="2">住民税</th>
              </tr>
              <tr>
                <th>{{ $warekiPrevLabel }}</th>
                <th>{{ $warekiCurrLabel }}</th>
                <th>{{ $warekiPrevLabel }}</th>
                <th>{{ $warekiCurrLabel }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($sections as $section)
                @php $rowCount = count($section['rows']); @endphp
                @foreach ($section['rows'] as $row)
                  @php
                    $hasBreakdown = $row['hasBreakdown'] ?? true;
                    $isTotal = $row['isTotal'] ?? false;
                  @endphp
                  <tr>
                    @if ($loop->first)
                      <th scope="rowgroup" rowspan="{{ $rowCount }}" class="text-center align-middle bg-light">{{ $section['title'] }}</th>
                    @endif
                    <th scope="row" colspan="3" class="align-middle{{ $isTotal ? ' fw-bold' : '' }}">{{ $row['label'] }}</th>
                    <td class="text-center align-middle">
                      <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                    </td>
                    <td class="text-center align-middle">
                      @if ($hasBreakdown)
                        <button type="button" class="btn btn-outline-secondary btn-sm">内訳</button>
                      @else
                        <span class="text-muted">&mdash;</span>
                      @endif
                    </td>
                    @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                      @foreach ($periods as $period)
                        @php
                          $name = sprintf('%s_%s_%s_%s', $section['group'], $row['key'], $tax, $period);
                          $value = old($name, $inputs[$name] ?? null);
                        @endphp
                        <td>
                          <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
                        </td>
                      @endforeach
                    @endforeach
                  </tr>
                @endforeach
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @php $sogoContent = ob_get_clean(); @endphp

    @if ((int) ($bunriFlag ?? 0) === 1)
      <div class="card mb-4">
        <div class="card-header pb-0">
          <ul class="nav nav-tabs card-header-tabs" id="furusato-input-tabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="tab-sogo" data-bs-toggle="tab" data-bs-target="#pane-sogo" type="button" role="tab" aria-controls="pane-sogo" aria-selected="true">確定申告書(総合課税)</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="tab-bunri" data-bs-toggle="tab" data-bs-target="#pane-bunri" type="button" role="tab" aria-controls="pane-bunri" aria-selected="false">確定申告書(分離課税)</button>
            </li>
          </ul>
        </div>
        <div class="card-body">
          <div class="tab-content" id="furusato-input-tab-content">
            <div class="tab-pane fade show active" id="pane-sogo" role="tabpanel" aria-labelledby="tab-sogo">
              {!! $sogoContent !!}
            </div>
            <div class="tab-pane fade" id="pane-bunri" role="tabpanel" aria-labelledby="tab-bunri">
              <div>
                <h5 class="card-title mb-3">確定申告書(分離課税)</h5>
                <div class="text-muted small">分離課税の帳票UIは次フェーズで実装</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    @else
      <div class="card mb-4">
        <div class="card-body">
          {!! $sogoContent !!}
        </div>
      </div>
    @endif
  </form>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('furusato-back-to-syori')?.addEventListener('click', function (event) {
  event.preventDefault();
  const form = document.getElementById('furusato-input-form');
  if (!form) {
    return;
  }
  form.redirect_to.value = 'syori';
  form.submit();
});
</script>
@endpush