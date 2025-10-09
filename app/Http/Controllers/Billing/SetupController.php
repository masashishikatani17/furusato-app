<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function index(): View
    {
        return view('billing.setup');
    }
}