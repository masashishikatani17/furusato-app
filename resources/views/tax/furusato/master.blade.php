@extends('layouts.min')

@section('content')
<div class="container-grey mt-2" style="width: 410px;">
  <div class="card-header d-flex align-items-start">
    <img src="{{ asset('storage/images/kado_lefttop_m.jpg') }}" alt="…">
    <hb class="mt-2">マスター 一覧</hb>
  </div>
  <div class="card-body m-3">
      <table width="360" align="center">
        <tr>
          <td align="center" width="170">
            <div>
              <a href="{{ route('furusato.master.shotoku', ['data_id' => $dataId]) }}" class="btn btn-menu">所得税率</a>
            </div>
          </td>
          <td width="20">&nbsp;</td>
          <td width="170">
            <div>
              <!-- 3) 特例控除 -->
              <a href="{{ route('furusato.master.jumin', ['data_id' => $dataId]) }}" class="btn btn-menu">住民税率</a>
            </div> 
          </td>
        </tr>
        <tr>
          <td height="8" colspan="3" style="font-size: 2px;">&nbsp;</td>
        </tr>
      	<tr>
          <td align="center">
            <div>
              <!-- 2) 各種税金 -->
              <a href="{{ route('furusato.master.tokurei', ['data_id' => $dataId]) }}" class="btn btn-menu">特例控除</a>
            </div>
          </td>
      	  <td> 
      	  </td>
          <td>
            <div>
              <a href="{{ route('furusato.master.shinkokutokurei', ['data_id' => $dataId]) }}" class="btn btn-menu">申告特例控除</a>
            </div>
          </td>
        </tr>
        <tr>
          <td height="8" colspan="3" style="font-size: 2px;">&nbsp;</td>
        </tr>
        <tr>
          <td align="center">
            <div>
            </div>
          </td>
          <td>
          </td>
          <td>
            <div>
            </div>
          </td>
        </tr>
      </table>
      <hr class="mb-2">
      <div class="text-end">
        <a href="{{ route('furusato.input', ['data_id' => $dataId], false) }}" class="btn-base-blue">戻 る</a>
      </div>
  </div>
</div>
@endsection