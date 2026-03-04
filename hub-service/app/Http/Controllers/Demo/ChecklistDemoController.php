<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ChecklistDemoController extends Controller
{
    public function __invoke(): View
    {
        $pusher = config('services.pusher', []);
        $options = $pusher['options'] ?? [];

        $clientHost = $options['client_host'] ?? null;

        $config = [
            'key' => $pusher['key'] ?? '',
            'cluster' => $pusher['cluster'] ?? null,
            'host' => $clientHost ?: ($options['host'] ?? request()->getHost()),
            'port' => $options['port'] ?? 6001,
            'scheme' => $options['scheme'] ?? 'http',
        ];

        return view('realtime-checklist', [
            'config' => $config,
        ]);
    }
}
