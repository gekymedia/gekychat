@extends('layouts.marketing')

@section('title', 'Download GekyChat')
@section('meta_description', 'Download GekyChat for Windows, Android, and iOS. Same phone-number account on every device.')

@section('content')
<div class="page-shell">
    <div class="mb-4">
        <h1 class="h2 fw-bold mb-2" style="font-family: var(--display);">Download GekyChat</h1>
        <p class="text-muted mb-0">Install on desktop or join the mobile beta. Sign in with your phone number and OTP — the same account as the web app. Updates appear in Settings → About.</p>
    </div>

    <div class="row g-4">
        @php
            $cards = [
                'windows' => ['icon' => 'bi-windows', 'hint' => 'Windows installer — run Setup and sign in with OTP'],
                'android' => ['icon' => 'bi-android2', 'hint' => 'Google Play closed beta'],
                'ios' => ['icon' => 'bi-phone', 'hint' => 'Get GekyChat on the App Store'],
                'linux' => ['icon' => 'bi-ubuntu', 'hint' => 'Tar.gz package when published'],
                'macos' => ['icon' => 'bi-apple', 'hint' => 'macOS build coming soon'],
            ];
        @endphp

        @foreach ($cards as $key => $meta)
            @php
                $config = $platforms[$key] ?? [];
                $url = $config['download_url'] ?? null;
                $version = $config['latest_version'] ?? '';
                $label = $labels[$key] ?? ucfirst($key);
                $available = filled($url);
            @endphp
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column p-4">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="fs-2 text-gek"><i class="bi {{ $meta['icon'] }}"></i></span>
                            <div>
                                <h2 class="h5 mb-0">{{ $label }}</h2>
                                @if ($version)
                                    <small class="text-muted">Latest {{ $version }}</small>
                                @endif
                            </div>
                        </div>
                        <p class="text-muted small flex-grow-1">{{ $meta['hint'] }}</p>
                        @if ($available)
                            <a href="{{ $url }}" class="btn-gek mt-2 align-self-start" rel="noopener">
                                <i class="bi bi-download"></i> Download
                            </a>
                        @else
                            <span class="btn btn-outline-secondary disabled mt-2 align-self-start">Coming soon</span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <hr class="my-5">

    <h2 class="h5 fw-semibold">Windows install steps</h2>
    <ol class="text-muted">
        <li>Download <strong>GekyChat Setup</strong> and run the installer.</li>
        <li>Follow the wizard (a Start Menu shortcut is created automatically).</li>
        <li>Windows may show SmartScreen for unsigned builds — choose “Run anyway” until code signing is in place.</li>
        <li>Sign in with your phone number and OTP (same account as mobile and web).</li>
        <li>Later updates appear in-app under Settings → About and link back here.</li>
    </ol>

    <p class="mt-4 mb-0">
        Prefer the browser?
        <a href="https://web.gekychat.com">Open GekyChat on the web</a>.
    </p>
</div>
@endsection
