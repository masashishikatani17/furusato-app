<table class="table table-bordered align-middle text-center">
    <tbody>
        <tr>
            <th class="align-middle" colspan="2">項目</th>
            <th class="align-middle">{{ $warekiPrev }}</th>
            <th class="align-middle">{{ $warekiCurr }}</th>
        </tr>
        <tr>
            <th class="align-middle" colspan="2">収入金額</th>
            <td>
                @php($name = 'fudosan_shunyu_prev')
                <input type="number" min="0" step="1" class="form-control form-control-sm text-end" value="{{ old($name, $out['inputs'][$name] ?? null) }}" name="{{ $name }}">
            </td>
            <td>
                @php($name = 'fudosan_shunyu_curr')
                <input type="number" min="0" step="1" class="form-control form-control-sm text-end" value="{{ old($name, $out['inputs'][$name] ?? null) }}" name="{{ $name }}">
            </td>
        </tr>
        @php($fields = [
            ['label' => '', 'name' => 'fudosan_keihi_1'],
            ['label' => '', 'name' => 'fudosan_keihi_2'],
            ['label' => '', 'name' => 'fudosan_keihi_3'],
            ['label' => '', 'name' => 'fudosan_keihi_4'],
            ['label' => '', 'name' => 'fudosan_keihi_5'],
            ['label' => '', 'name' => 'fudosan_keihi_6'],
            ['label' => '', 'name' => 'fudosan_keihi_7'],
            ['label' => 'その他', 'name' => 'fudosan_keihi_sonota'],
            ['label' => '合計', 'name' => 'fudosan_keihi_gokei'],
        ])
        <tr>
            <th class="align-middle" rowspan="9">必要経費</th>
            @php($field = array_shift($fields))
            <td class="align-middle">{{ $field['label'] }}</td>
            <td>
                @php($name = $field['name'] . '_prev')
                <input type="number" min="0" step="1" class="form-control form-control-sm text-end" value="{{ old($name, $out['inputs'][$name] ?? null) }}" name="{{ $name }}">
            </td>
            <td>
                @php($name = $field['name'] . '_curr')
                <input type="number" min="0" step="1" class="form-control form-control-sm text-end" value="{{ old($name, $out['inputs'][$name] ?? null) }}" name="{{ $name }}">
            </td>
        </tr>
        @foreach ($fields as $field)
            <tr>
                <td class="align-middle">{{ $field['label'] }}</td>
                <td>
                    @php($name = $field['name'] . '_prev')
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end" value="{{ old($name, $out['inputs'][$name] ?? null) }}" name="{{ $name }}">
                </td>
                <td>
                    @php($name = $field['name'] . '_curr')
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end" value="{{ old($name, $out['inputs'][$name] ?? null) }}" name="{{ $name }}">
                </td>
            </tr>
        @endforeach
        @php($footerFields = [
            'fudosan_sashihiki' => '差引金額',
            'fudosan_senjuusha_kyuyo' => '専従者給与',
            'fudosan_aoi_tokubetsu_kojo_mae' => '青色申告特別控除前の所得金額',
            'fudosan_aoi_tokubetsu_kojo_gaku' => '青色申告特別控除額',
            'fudosan_shotoku' => '所得金額',
            'fudosan_fusairishi' => '土地等を取得するための負債利子',
        ])
        @foreach ($footerFields as $namePrefix => $label)
            <tr>
                <th class="align-middle" colspan="2">{{ $label }}</th>
                <td>
                    @php($name = $namePrefix . '_prev')
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end" value="{{ old($name, $out['inputs'][$name] ?? null) }}" name="{{ $name }}">
                </td>
                <td>
                    @php($name = $namePrefix . '_curr')
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end" value="{{ old($name, $out['inputs'][$name] ?? null) }}" name="{{ $name }}">
                </td>
            </tr>
        @endforeach
    </tbody>
</table>