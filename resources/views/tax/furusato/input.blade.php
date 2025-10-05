@extends('layouts.min')

@section('content')
<div class="container" style="min-width: 960px; max-width: 1080px;">
  <h5 class="mb-3">ふるさと納税：インプット表（v0.4）</h5>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('furusato.calc') }}" class="row g-3">
    @csrf
    @isset($dataId)
      <input type="hidden" name="data_id" value="{{ $dataId }}">
    @endisset

    <div class="col-12">
      <div class="card mb-4">
        <div class="card-header">所得金額（損益通算前）</div>
        <div class="card-body">
          @php($incomeLabels = [
            'jiryo_eigyo' => '事業所得（営業等）',
            'jiryo_nogyo' => '事業所得（農業）',
            'fudosan' => '不動産所得',
            'haito' => '配当所得',
            'kyuyo' => '給与所得',
            'zatsu_nenkin' => '雑所得（公的年金等）',
            'zatsu_gyomu' => '雑所得（業務）',
            'zatsu_sonota' => '雑所得（その他）',
            'sogo_joto_tanki' => '総合譲渡所得（短期）',
            'sogo_joto_choki' => '総合譲渡所得（長期）',
            'ichiji' => '一時所得',
            'bunri_tanki_ippan' => '分離課税短期譲渡所得金額 一般',
            'bunri_tanki_keigen' => '分離課税短期譲渡所得金額 軽減',
            'bunri_choki_ippan' => '分離課税長期譲渡所得金額 一般',
            'bunri_choki_tokutei' => '分離課税長期譲渡所得金額 特定',
            'bunri_choki_keika' => '分離課税長期譲渡所得金額 軽課',
            'ippan_kabu_joto' => '一般株式等に係る譲渡所得等の金額',
            'jojo_kabu_joto' => '上場株式等に係る譲渡所得の金額',
            'jojo_kabu_haito' => '上場株式等に係る配当所得等の金額',
            'sakimono_zatsu' => '先物取引に係る雑所得等の金額',
            'sanrin' => '山林所得金額',
            'taishoku' => '退職所得金額',
          ])
          <div class="table-responsive">
            <table class="table table-bordered align-middle">
              <thead class="table-light">
                <tr>
                  <th style="width: 45%">項目</th>
                  <th style="width: 27%">前期</th>
                  <th style="width: 28%">当期</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($incomeLabels as $key => $label)
                  <tr>
                    <th scope="row">{{ $label }}</th>
                    <td>
                      <input type="number" class="form-control" name="{{ $key }}_prev" min="0" required value="{{ old($key.'_prev', $out['inputs'][$key.'_prev'] ?? 0) }}">
                    </td>
                    <td>
                      <input type="number" class="form-control" name="{{ $key }}_curr" min="0" required value="{{ old($key.'_curr', $out['inputs'][$key.'_curr'] ?? 0) }}">
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card mb-4">
        <div class="card-header">控除・人数入力</div>
        <div class="card-body">
          <h6 class="mb-3">保険・共済等控除</h6>
          <div class="row g-3 mb-4">
            @php($deductions = [
              'shakaihoken_kojo_curr' => '社会保険料控除',
              'shokibo_kyosai_kojo_curr' => '小規模企業共済等掛金控除',
              'seimei_hoken_kojo_curr' => '生命保険料控除',
              'jishin_hoken_kojo_curr' => '地震保険料控除',
            ])
            @foreach ($deductions as $name => $label)
              <div class="col-md-3">
                <label class="form-label">{{ $label }}</label>
                <input type="number" class="form-control" name="{{ $name }}" min="0" required value="{{ old($name, $out['inputs'][$name] ?? 0) }}">
              </div>
            @endforeach
          </div>

          <h6 class="mb-3">フラグ控除</h6>
          <div class="row g-3 mb-4">
            @php($flagOptions = ['0' => 'いいえ', '1' => 'はい'])
            @foreach ([
              'kafu_kojo_flag' => '寡婦控除',
              'hitori_oya_kojo_flag' => 'ひとり親控除',
              'kinro_gakusei_kojo_flag' => '勤労学生控除',
            ] as $name => $label)
              <div class="col-md-3">
                <label class="form-label">{{ $label }}</label>
                <select class="form-select" name="{{ $name }}" required>
                  @foreach ($flagOptions as $value => $text)
                    <option value="{{ $value }}" @selected(old($name, (string)($out['inputs'][$name] ?? '0')) === $value)>{{ $text }}</option>
                  @endforeach
                </select>
              </div>
            @endforeach
          </div>

          <h6 class="mb-3">障害者控除（人数）</h6>
          <div class="row g-3 mb-4">
            @foreach ([
              'shogaisha_count' => '障害者',
              'tokubetsu_shogaisha_count' => '特別障害者',
              'dokyo_tokubetsu_shogaisha_count' => '同居特別障害者',
            ] as $name => $label)
              <div class="col-md-3">
                <label class="form-label">{{ $label }}</label>
                <input type="number" class="form-control" name="{{ $name }}" min="0" required value="{{ old($name, $out['inputs'][$name] ?? 0) }}">
              </div>
            @endforeach
          </div>

          <h6 class="mb-3">配偶者関連</h6>
          <div class="row g-3 mb-4">
            @foreach ([
              'haigusha_kojo_kingaku' => '配偶者控除（金額）',
              'haigusha_tokubetsu_kojo_kingaku' => '配偶者特別控除（金額）',
            ] as $name => $label)
              <div class="col-md-3">
                <label class="form-label">{{ $label }}</label>
                <input type="number" class="form-control" name="{{ $name }}" min="0" required value="{{ old($name, $out['inputs'][$name] ?? 0) }}">
              </div>
            @endforeach
          </div>

          <h6 class="mb-3">扶養控除（人数）</h6>
          <div class="row g-3 mb-4">
            @foreach ([
              'fuyo_ippan_count' => '一般',
              'fuyo_tokutei_count' => '特定扶養親族',
              'fuyo_rojin_count' => '老人扶養親族',
              'fuyo_dokyo_rojin_count' => '同居老人扶養親族',
              'tokutei_shinzoku_tokubetsu_count' => '特定親族特別控除 対象人数',
            ] as $name => $label)
              <div class="col-md-3">
                <label class="form-label">{{ $label }}</label>
                <input type="number" class="form-control" name="{{ $name }}" min="0" required value="{{ old($name, $out['inputs'][$name] ?? 0) }}">
              </div>
            @endforeach
          </div>

          <h6 class="mb-3">その他の所得控除</h6>
          <div class="row g-3">
            @foreach ([
              'zasson_kojo_kingaku' => '雑損控除',
              'iryo_hi_kojo_kingaku' => '医療費控除',
            ] as $name => $label)
              <div class="col-md-3">
                <label class="form-label">{{ $label }}</label>
                <input type="number" class="form-control" name="{{ $name }}" min="0" required value="{{ old($name, $out['inputs'][$name] ?? 0) }}">
              </div>
            @endforeach
          </div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card mb-4">
        <div class="card-header">寄付金控除（内訳）</div>
        <div class="card-body">
          <div class="row g-3">
            @foreach ([
              'tokutei_kifukin_kingaku' => '特定寄付金',
              'furusato_nozei_kingaku' => 'ふるさと納税',
              'seitotou_kifukin_kingaku' => '政党等寄付金',
              'nintei_npo_kifukin_kingaku' => '認定NPO法人寄付金',
              'koueki_shadan_kifukin_kingaku' => '公益社団法人等寄付金',
              'kyobo_nisseki_kifukin_kingaku' => '共同募金・日赤',
              'jorei_npo_kifukin_kingaku' => '条例指定NPO',
            ] as $name => $label)
              <div class="col-md-3">
                <label class="form-label">{{ $label }}</label>
                <input type="number" class="form-control" name="{{ $name }}" min="0" required value="{{ old($name, $out['inputs'][$name] ?? 0) }}">
              </div>
            @endforeach
          </div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card mb-4">
        <div class="card-header">その他</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label">ワンストップ適用フラグ</label>
              <select class="form-select" name="one_stop_flag" required>
                @foreach ($flagOptions as $value => $text)
                  <option value="{{ $value }}" @selected(old('one_stop_flag', (string)($out['inputs']['one_stop_flag'] ?? '0')) === $value)>{{ $text }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">特別税額控除</label>
              <input type="number" class="form-control" name="tokubetsu_zeigaku_kojo_kingaku" min="0" required value="{{ old('tokubetsu_zeigaku_kojo_kingaku', $out['inputs']['tokubetsu_zeigaku_kojo_kingaku'] ?? 0) }}">
            </div>
            <div class="col-md-3">
              <label class="form-label">源泉徴収税額</label>
              <input type="number" class="form-control" name="gensen_choshu_zeigaku" min="0" required value="{{ old('gensen_choshu_zeigaku', $out['inputs']['gensen_choshu_zeigaku'] ?? 0) }}">
            </div>
            <div class="col-md-3">
              <label class="form-label">指定都市区分</label>
              <select class="form-select" name="shitei_toshi_flag" required>
                <option value="0" @selected(old('shitei_toshi_flag', (string)($out['inputs']['shitei_toshi_flag'] ?? '0')) === '0')>指定都市以外</option>
                <option value="1" @selected(old('shitei_toshi_flag', (string)($out['inputs']['shitei_toshi_flag'] ?? '0')) === '1')>指定都市</option>
              </select>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 text-end">
      <button type="submit" class="btn btn-primary">送信</button>
    </div>
  </form>

  @isset($out['inputs'])
    <div class="card mt-4">
      <div class="card-header">送信内容（デバッグ表示）</div>
      <div class="card-body table-responsive">
        <table class="table table-striped">
          <thead class="table-light">
            <tr>
              <th>キー</th>
              <th>値</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($out['inputs'] as $key => $value)
              <tr>
                <td>{{ $key }}</td>
                <td>{{ $value }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endisset
</div>
@endsection