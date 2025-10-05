@extends('layouts.min')
@section('content')
<div class="container" style="min-width: 960px; max-width: 1080px;">
  <h5 class="mb-3">ふるさと納税：上限簡易試算（Excel vol23 ベース）</h5>

  <form method="POST" action="{{ route('furusato.calc') }}" class="row g-3">
    @csrf
    @isset($dataId)
      <input type="hidden" name="data_id" value="{{ $dataId }}">
    @endisset

    <div class="col-12">
      <div class="card mb-4">
        <div class="card-header">基礎計算入力（計算結果シート）</div>
        <div class="card-body row g-3">
          <div class="col-md-3">
            <label class="form-label">W17</label>
            <input type="number" class="form-control" name="w17" value="{{ old('w17', 2000000) }}" required>
            <div class="form-text">計算結果!W17（給与所得等）</div>
          </div>
          <div class="col-md-3">
            <label class="form-label">W18</label>
            <input type="number" class="form-control" name="w18" value="{{ old('w18', 3000000) }}" required>
            <div class="form-text">計算結果!W18（合算控除後所得）</div>
          </div>
          <div class="col-md-3">
            <label class="form-label">AB56</label>
            <input type="number" class="form-control" name="ab56" min="1" value="{{ old('ab56', 10000) }}" required>
            <div class="form-text">計算結果!AB56（控除割合の母数）</div>
          </div>
          <div class="col-md-3">
            <label class="form-label">AB6</label>
            <input type="number" class="form-control" name="ab6" value="{{ old('ab6', 300000) }}" required>
            <div class="form-text">計算結果!AB6（寄付予定額）</div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card mb-4">
        <div class="card-header">基本情報（インプット表・世帯情報）</div>
        <div class="card-body row g-3">
          <div class="col-md-3">
            <label class="form-label">世帯区分（A2）</label>
            @php($householdOptions = [1 => '単身', 2 => '夫婦のみ', 3 => '夫婦＋扶養'])
            <select class="form-select" name="household_composition">
              @foreach($householdOptions as $value => $label)
                <option value="{{ $value }}" @selected((string)old('household_composition', 1) === (string)$value)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">配偶者の有無（B4）</label>
            <select class="form-select" name="spouse_status">
              @foreach([0 => 'なし', 1 => 'あり'] as $value => $label)
                <option value="{{ $value }}" @selected((string)old('spouse_status', 0) === (string)$value)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">配偶者所得区分（B5）</label>
            @php($spouseIncomeOptions = [0 => '未設定', 1 => '103万円以下', 2 => '201万円以下', 3 => '201万円超'])
            <select class="form-select" name="spouse_income_class">
              @foreach($spouseIncomeOptions as $value => $label)
                <option value="{{ $value }}" @selected((string)old('spouse_income_class', 0) === (string)$value)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">納税者年齢区分（B6）</label>
            @php($ageOptions = [1 => '一般（〜64）', 2 => '65〜74歳', 3 => '75歳以上'])
            <select class="form-select" name="taxpayer_age_category">
              @foreach($ageOptions as $value => $label)
                <option value="{{ $value }}" @selected((string)old('taxpayer_age_category', 1) === (string)$value)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">配偶者年齢区分（B7）</label>
            @php($spouseAgeOptions = [0 => '配偶者なし', 1 => '一般', 2 => '65歳以上'])
            <select class="form-select" name="spouse_age_category">
              @foreach($spouseAgeOptions as $value => $label)
                <option value="{{ $value }}" @selected((string)old('spouse_age_category', 0) === (string)$value)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">扶養親族数（C4）</label>
            <input type="number" min="0" class="form-control" name="num_dependents" value="{{ old('num_dependents', 0) }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">16歳未満扶養数（C5）</label>
            <input type="number" min="0" class="form-control" name="num_minor_dependents" value="{{ old('num_minor_dependents', 0) }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">老人扶養数（C6）</label>
            <input type="number" min="0" class="form-control" name="num_elder_dependents" value="{{ old('num_elder_dependents', 0) }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">特定扶養数（C7）</label>
            <input type="number" min="0" class="form-control" name="num_special_dependents" value="{{ old('num_special_dependents', 0) }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">障害者扶養数（C8）</label>
            <input type="number" min="0" class="form-control" name="num_disabled_dependents" value="{{ old('num_disabled_dependents', 0) }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">居住都道府県コード（D4）</label>
            @php($prefectureOptions = [13 => '13：東京都', 14 => '14：神奈川県', 27 => '27：大阪府'])
            <select class="form-select" name="prefecture_code">
              @foreach($prefectureOptions as $value => $label)
                <option value="{{ $value }}" @selected((string)old('prefecture_code', 13) === (string)$value)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">市区町村コード（D5）</label>
            @php($municipalityOptions = [13101 => '13101：千代田区', 14100 => '14100：横浜市', 27100 => '27100：大阪市'])
            <select class="form-select" name="municipality_code">
              @foreach($municipalityOptions as $value => $label)
                <option value="{{ $value }}" @selected((string)old('municipality_code', 13101) === (string)$value)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">居住形態（D6）</label>
            @php($residenceOptions = [1 => '持家', 2 => '賃貸', 3 => '社宅'])
            <select class="form-select" name="residence_type">
              @foreach($residenceOptions as $value => $label)
                <option value="{{ $value }}" @selected((string)old('residence_type', 1) === (string)$value)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">特別徴収（E4）</label>
            <select class="form-select" name="special_collection_flag" required>
              @foreach([0 => 'いいえ', 1 => 'はい'] as $value => $label)
                <option value="{{ $value }}" @selected((string)old('special_collection_flag', 1) === (string)$value)>{{ $label }}</option>
              @endforeach
            </select>
            <div class="form-text">特別徴収：給与天引き設定</div>
          </div>
          <div class="col-md-3">
            <label class="form-label">青色申告（E5）</label>
            <select class="form-select" name="blue_return_flag" required>
              @foreach([0 => 'いいえ', 1 => 'はい'] as $value => $label)
                <option value="{{ $value }}" @selected((string)old('blue_return_flag', 0) === (string)$value)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">転入1年目（E6）</label>
            <select class="form-select" name="new_resident_flag" required>
              @foreach([0 => 'いいえ', 1 => 'はい'] as $value => $label)
                <option value="{{ $value }}" @selected((string)old('new_resident_flag', 0) === (string)$value)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card mb-4">
        <div class="card-header">収入・所得（インプット表 S列想定）</div>
        <div class="card-body row g-3">
          @php($incomeFields = [
            'salary_income' => '給与収入',
            'bonus_income' => '賞与収入',
            'business_income' => '事業所得',
            'real_estate_income' => '不動産所得',
            'pension_income' => '年金所得',
            'dividend_income' => '配当所得',
            'interest_income' => '利子所得',
            'capital_gain_income' => '譲渡所得',
            'temporary_income' => '一時所得',
            'other_income' => 'その他所得',
          ])
          @foreach($incomeFields as $name => $label)
            <div class="col-md-3">
              <label class="form-label">{{ $label }}</label>
              <input type="number" min="0" class="form-control" name="{{ $name }}" value="{{ old($name, 0) }}">
            </div>
          @endforeach
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card mb-4">
        <div class="card-header">所得控除（インプット表 T列想定）</div>
        <div class="card-body row g-3">
          @php($deductionFields = [
            'social_insurance_premium' => '社会保険料控除',
            'life_insurance_premium' => '生命保険料控除',
            'earthquake_insurance_premium' => '地震保険料控除',
            'medical_expense_deduction' => '医療費控除',
            'small_enterprise_mutual_aid' => '小規模企業共済控除',
            'spouse_deduction_amount' => '配偶者控除',
            'special_spouse_deduction_amount' => '配偶者特別控除',
            'dependent_deduction_amount' => '扶養控除',
            'disability_deduction_amount' => '障害者控除',
            'widow_widower_deduction_amount' => '寡婦（夫）控除',
            'single_parent_deduction_amount' => 'ひとり親控除',
            'working_student_deduction_amount' => '勤労学生控除',
            'basic_deduction_amount' => '基礎控除',
            'donation_deduction_amount' => '寄附金控除',
            'housing_loan_deduction_amount' => '住宅ローン控除',
          ])
          @foreach($deductionFields as $name => $label)
            <div class="col-md-3">
              <label class="form-label">{{ $label }}</label>
              <input type="number" min="0" class="form-control" name="{{ $name }}" value="{{ old($name, 0) }}">
            </div>
          @endforeach
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card mb-4">
        <div class="card-header">寄付関連設定（インプット表 Q列）</div>
        <div class="card-body row g-3">
          <div class="col-md-3">
            <label class="form-label">Q2（住民税基本分割合）</label>
            <input type="number" step="0.001" class="form-control" name="q2" value="{{ old('q2', $donation['rows'][0]['q'] ?? 0.30) }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">Q3（住民税特例分割合）</label>
            <input type="number" step="0.001" class="form-control" name="q3" value="{{ old('q3', $donation['rows'][1]['q'] ?? 0.25) }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">Q4（所得税控除割合）</label>
            <input type="number" step="0.001" class="form-control" name="q4" value="{{ old('q4', $donation['rows'][2]['q'] ?? 0.20) }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">Q5（その他控除割合）</label>
            <input type="number" step="0.001" class="form-control" name="q5" value="{{ old('q5', $donation['rows'][3]['q'] ?? 0.15) }}">
          </div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card mb-4">
        <div class="card-header">住民税設定（インプット表 V/X列）</div>
        <div class="card-body row g-3">
          <div class="col-md-3">
            <label class="form-label">課税方式（V5）</label>
            @php($taxationOptions = [0 => '標準', 1 => '普通徴収', 2 => '特別徴収変更'])
            <select class="form-select" name="taxation_method">
              @foreach($taxationOptions as $value => $label)
                <option value="{{ $value }}" @selected((string)old('taxation_method', 0) === (string)$value)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">都道府県均等割額</label>
            <input type="number" min="0" class="form-control" name="prefectural_equal_share" value="{{ old('prefectural_equal_share', 0) }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">市町村均等割額</label>
            <input type="number" min="0" class="form-control" name="municipal_equal_share" value="{{ old('municipal_equal_share', 0) }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">都道府県所得割率</label>
            <input type="number" step="0.001" class="form-control" name="prefectural_income_tax_rate" value="{{ old('prefectural_income_tax_rate', 0.04) }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">市町村所得割率</label>
            <input type="number" step="0.001" class="form-control" name="municipal_income_tax_rate" value="{{ old('municipal_income_tax_rate', 0.06) }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">V6（モードA）</label>
            <select class="form-select" name="v6">
              @foreach([0, 1, 2] as $option)
                <option value="{{ $option }}" @selected((string)old('v6', 0) === (string)$option)>{{ $option }}</option>
              @endforeach
            </select>
            <div class="form-text">Excel「計算結果!V6」の選択（0/1/2）。</div>
          </div>
          <div class="col-md-3">
            <label class="form-label">W6（モードB）</label>
            <select class="form-select" name="w6">
              @foreach([0, 1, 2] as $option)
                <option value="{{ $option }}" @selected((string)old('w6', 0) === (string)$option)>{{ $option }}</option>
              @endforeach
            </select>
            <div class="form-text">Excel「計算結果!W6」の選択（0/1/2）。</div>
          </div>
          <div class="col-md-3">
            <label class="form-label">X6（モードC）</label>
            <select class="form-select" name="x6">
              @foreach([0, 1, 2] as $option)
                <option value="{{ $option }}" @selected((string)old('x6', 0) === (string)$option)>{{ $option }}</option>
              @endforeach
            </select>
            <div class="form-text">Excel「計算結果!X6」の選択（0/1/2）。</div>
          </div>
        </div>
      </div>
    </div>
    
    <div class="col-12">
      <div class="card mb-4">
        <div class="card-header">備考・メモ</div>
        <div class="card-body">
          <label class="form-label" for="notes">将来追加用メモ欄</label>
          <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="自由記入">{{ old('notes') }}</textarea>
          <div class="form-text">インプット表の予備欄。将来 Translator で利用予定。</div>
        </div>
      </div>
    </div>

    <div class="col-12 text-end mb-2">
      <button class="btn btn-primary">計算する</button>
    </div>
  </form>

  @isset($out)
  <hr>
  <h6>結果（Excelセル対応・確認用）</h6>
  <table class="table table-sm table-bordered w-auto">
    <tbody>
      <tr><th>B8</th><td>{{ number_format($out['b8']) }}</td></tr>
      <tr><th>B9</th><td>{{ number_format($out['b9']) }}</td></tr>
      <tr><th>B12</th><td>{{ number_format($out['b12']) }}</td></tr>
      <tr><th>B13</th><td>{{ number_format($out['b13']) }}</td></tr>
      <tr><th>B16</th><td>{{ number_format($out['b16']) }}</td></tr>
      <tr><th>B17</th><td>{{ number_format($out['b17']) }}</td></tr>
    </tbody>
  </table>
  <h6>入力モード確認</h6>
  <table class="table table-sm table-bordered w-auto">
    <tbody>
      <tr><th>V6</th><td>{{ $out['flags']['v6'] }}</td></tr>
      <tr><th>W6</th><td>{{ $out['flags']['w6'] }}</td></tr>
      <tr><th>X6</th><td>{{ $out['flags']['x6'] }}</td></tr>
    </tbody>
  </table>  
  @endisset
  @isset($donation)
  <h6>寄付控除計算概要（行2〜5）</h6>
  <table class="table table-sm table-bordered w-auto">
    <thead>
      <tr>
        <th>行</th>
        <th>Q</th>
        <th>S</th>
        <th>U</th>
      </tr>
    </thead>
    <tbody>
      @foreach($donation['rows'] as $row)
        <tr>
          <th>{{ $row['row'] }}</th>
          <td>{{ number_format($row['q'], 4) }}</td>
          <td>{{ number_format($row['s'], 4) }}</td>
          <td>{{ number_format($row['u'], 4) }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
  @endisset
</div>
@endsection