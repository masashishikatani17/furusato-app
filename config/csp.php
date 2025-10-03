<?php
return [
    'enabled'     => (bool) env('FEATURE_CSP', false),
    'report_only' => (bool) env('FEATURE_CSP_REPORT_ONLY', true),
];