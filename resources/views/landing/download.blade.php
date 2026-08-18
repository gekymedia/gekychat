@extends('layouts.public')

@section('title', 'Download GekyChat - ' . config('app.name', 'GekyChat'))

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="mb-4">
                <a href="{{ url('/') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i> Back to Home
                </a>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-card border-bottom py-4">
                    <div class="text-center">
                        <i class="bi bi-download display-4 text-wa mb-3"></i>
                        <h1 class="h2 fw-bold text-text mb-2">Download GekyChat</h1>
                        <p class="text-muted mb-0">Install the desktop app or join the mobile beta. Updates are announced in-app via Settings → About.</p>
                    </div>
                </div>

                <div class="card-body p-4 p-md-5">
                    <div class="row g-4">
                        @php
                            $cards = [
                                'windows' => ['icon' => 'bi-windows', 'hint' => 'Zip — extract and run gekychat_desktop.exe'],
                                'linux' => ['icon' => 'bi-ubuntu', 'hint' => 'Tar.gz — extract and run ./gekychat_desktop'],
                                'macos' => ['icon' => 'bi-apple', 'hint' => 'macOS build (coming soon)'],
                                'android' => ['icon' => 'bi-android2', 'hint' => 'Google Play closed beta'],
                                'ios' => ['icon' => 'bi-phone', 'hint' => 'TestFlight / App Store'],
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
                                <div class="card h-100 border {{ $available ? '' : 'opacity-75' }}">
                                    <div class="card-body d-flex flex-column">
                                        <div class="d-flex align-items-center gap-3 mb-3">
                                            <span class="fs-2 text-wa"><i class="bi {{ $meta['icon'] }}"></i></span>
                                            <div>
                                                <h2 class="h5 mb-0">{{ $label }}</h2>
                                                @if ($version)
                                                    <small class="text-muted">Latest {{ $version }}</small>
                                                @endif
                                            </div>
                                        </div>
                                        <p class="text-muted small flex-grow-1">{{ $meta['hint'] }}</p>
                                        @if ($available)
                                            <a href="{{ $url }}" class="btn btn-wa mt-2" rel="noopener">
                                                <i class="bi bi-download me-1"></i> Download
                                            </a>
                                        @else
                                            <span class="btn btn-outline-secondary disabled mt-2">Coming soon</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <hr class="my-5">

                    <h2 class="h5 fw-semibold">Windows install steps</h2>
                    <ol class="text-muted">
                        <li>Download <strong>GekyChat-Windows</strong> zip.</li>
                        <li>Extract to a folder (e.g. <code>%LocalAppData%\GekyChat</code>).</li>
                        <li>Run <code>gekychat_desktop.exe</code>. Windows may show SmartScreen for unsigned beta builds — choose “Run anyway”.</li>
                        <li>Sign in with your phone number and OTP (same account as mobile).</li>
                    </ol>

                    <p class="text-muted small mb-0">
                        Need help? <a href="{{ url('/help') }}">Help center</a> ·
                        <a href="{{ url('/contact') }}">Contact support</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
