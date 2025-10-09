<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class BillingReceiptsController extends Controller
{
    public function index(): View
    {
        return view('admin.billing.receipts.index');
    }
}