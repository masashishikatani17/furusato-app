@extends('layouts.min')

@section('content')
<div class="container-grey mt-2" style="width: 500px;">
  <div class="card-header d-flex align-items-start">
    <img src="{{ asset('storage/images/kado_lefttop_m.jpg') }}" alt="…">
    <hb class="mb-0 mt-2">マスター一覧</hb>
  </div>
  <div class="card-body mt-3">
    <div class="wrapper">
      <table width="440" align="center">
                  <tr>
                      <td align="center" width="200">
                         <div>
                      <a href="{{ route('furusato.master.shotoku', ['data_id' => $dataId]) }}" class="btn btn-menu">所得税率</a>
                       </div>
                      </td>
          	        <td width="40">&nbsp;</td>
                  	<td width="200">
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
                      <td> <div>
                        </div>
                      </td>
                  </tr>
              </table>
        <hr>
        <div class="text-end me-2 mb-2">
          <a href="{{ route('furusato.input', ['data_id' => $dataId], false) }}" class="btn-base-blue">戻 る</a>
        </div>
      </div> 
  </div>
</div>
@endsection