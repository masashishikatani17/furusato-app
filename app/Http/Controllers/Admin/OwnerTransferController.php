<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class OwnerTransferController extends Controller
{
    public function form(): View
    {
        return view('admin.owner_transfer.form');
    }
}