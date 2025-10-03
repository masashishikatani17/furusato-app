<?php

return [
    'sentry'    => (bool) env('FEATURE_SENTRY', false),
    'health'    => (bool) env('FEATURE_HEALTH', false),
    'telescope' => (bool) env('FEATURE_TELESCOPE', false),
    'horizon'   => (bool) env('FEATURE_HORIZON', false),
    'csp'       => (bool) env('FEATURE_CSP', false),
    'activity'  => (bool) env('FEATURE_ACTIVITY', false),
    'settings'  => (bool) env('FEATURE_SETTINGS', false),
    'data_privacy' => (bool) env('FEATURE_DATA_PRIVACY', true),
];