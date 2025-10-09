<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DataDownloadController extends Controller
{
    public function index(): View
    {
        return view('admin.data_download.index');
    }
}