<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GekyChat - Open in App</title>
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="GekyChat - Modern Messaging App">
    <meta property="og:description" content="Connect with friends and family using GekyChat">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:title" content="GekyChat - Modern Messaging App">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #fff;
        }
        
        .container {
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        
        .logo {
            width: 120px;
            height: 120px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            font-size: 60px;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 15px;
            color: #fff;
        }
        
        .subtitle {
            font-size: 18px;
            opacity: 0.9;
            margin-bottom: 40px;
            line-height: 1.6;
        }
        
        .button-group {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 16px 32px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            gap: 10px;
        }
        
        .btn-primary {
            background: #fff;
            color: #059669;
        }
        
        .btn-primary:hover {
            background: #f0f0f0;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        
        .btn-icon {
            font-size: 20px;
        }
        
        .store-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 30px;
        }
        
        .store-btn {
            display: inline-block;
            height: 50px;
            transition: transform 0.2s;
        }
        
        .store-btn:hover {
            transform: scale(1.05);
        }
        
        .store-btn img {
            height: 100%;
        }
        
        .info-text {
            margin-top: 40px;
            font-size: 14px;
            opacity: 0.8;
            line-height: 1.6;
        }
        
        .countdown {
            margin-top: 20px;
            font-size: 14px;
            opacity: 0.7;
        }
        
        @media (max-width: 600px) {
            h1 {
                font-size: 26px;
            }
            
            .subtitle {
                font-size: 16px;
            }
            
            .store-buttons {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            💬
        </div>
        
        <h1>Open GekyChat</h1>
        <p class="subtitle">
            @if($isMobile)
                Tap the button below to open in the GekyChat app
            @elseif($isDesktop)
                Download GekyChat for your desktop or mobile device
            @else
                Download GekyChat for your device
            @endif
        </p>
        
        <div class="button-group">
            @if($isMobile)
                <a href="{{ $appDeepLink }}" id="openAppBtn" class="btn btn-primary">
                    <span class="btn-icon">📱</span>
                    <span>Open in GekyChat</span>
                </a>
                
                @if($isAndroid)
                    <a href="intent://open#Intent;scheme=gekychat;package=com.gekychat.app;end" class="btn btn-secondary">
                        <span class="btn-icon">🤖</span>
                        <span>Try Android Intent</span>
                    </a>
                @endif
            @endif
            
            @if($isDesktop)
                @if($isWindows)
                    <a href="{{ $windowsUrl }}" class="btn btn-primary" target="_blank">
                        <span class="btn-icon">🪟</span>
                        <span>Download for Windows</span>
                    </a>
                @elseif($isMacOS)
                    <a href="{{ $macOSUrl }}" class="btn btn-primary" target="_blank">
                        <span class="btn-icon">🍎</span>
                        <span>Download for macOS</span>
                    </a>
                @elseif($isLinux)
                    <a href="{{ $linuxUrl }}" class="btn btn-primary" target="_blank">
                        <span class="btn-icon">🐧</span>
                        <span>Download for Linux</span>
                    </a>
                @endif
            @endif
            
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.2);">
                <p style="font-size: 14px; opacity: 0.8; margin-bottom: 15px;">Mobile Apps</p>
                <a href="{{ $playStoreUrl }}" class="btn btn-secondary" target="_blank">
                    <span class="btn-icon">📱</span>
                    <span>Download for Android</span>
                </a>
                
                <a href="{{ $appStoreUrl }}" class="btn btn-secondary" target="_blank">
                    <span class="btn-icon">📱</span>
                    <span>Download for iOS</span>
                </a>
            </div>
            
            @if($isDesktop)
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.2);">
                    <p style="font-size: 14px; opacity: 0.8; margin-bottom: 15px;">Other Platforms</p>
                    @if(!$isWindows)
                        <a href="{{ $windowsUrl }}" class="btn btn-secondary" target="_blank">
                            <span class="btn-icon">🪟</span>
                            <span>Download for Windows</span>
                        </a>
                    @endif
                    @if(!$isMacOS)
                        <a href="{{ $macOSUrl }}" class="btn btn-secondary" target="_blank">
                            <span class="btn-icon">🍎</span>
                            <span>Download for macOS</span>
                        </a>
                    @endif
                    @if(!$isLinux)
                        <a href="{{ $linuxUrl }}" class="btn btn-secondary" target="_blank">
                            <span class="btn-icon">🐧</span>
                            <span>Download for Linux</span>
                        </a>
                    @endif
                </div>
            @endif
        </div>
        
        @if($isMobile)
            <div class="countdown" id="countdown" style="display: none;">
                Redirecting to app store in <span id="countdown-number">5</span> seconds...
            </div>
        @endif
        
        <div class="info-text">
            <p>GekyChat - Modern real-time messaging platform</p>
            <p style="margin-top: 10px; font-size: 12px;">
                API Documentation: <a href="/api/docs" style="color: rgba(255,255,255,0.8); text-decoration: underline;">api.gekychat.com/api/docs</a>
            </p>
        </div>
    </div>
    
    <script>
        @if($isMobile)
            // Auto-attempt to open app after a short delay
            let countdown = 5;
            const countdownEl = document.getElementById('countdown');
            const countdownNumber = document.getElementById('countdown-number');
            
            // Try to open app immediately
            setTimeout(() => {
                window.location.href = '{{ $appDeepLink }}';
                
                // Show countdown after trying deep link
                countdownEl.style.display = 'block';
                
                const timer = setInterval(() => {
                    countdown--;
                    countdownNumber.textContent = countdown;
                    
                    if (countdown <= 0) {
                        clearInterval(timer);
                        // Redirect to app store if app didn't open
                        @if($isAndroid)
                            window.location.href = '{{ $playStoreUrl }}';
                        @elseif($isIOS)
                            window.location.href = '{{ $appStoreUrl }}';
                        @endif
                    }
                }, 1000);
            }, 500);
            
            // Also handle manual button click
            document.getElementById('openAppBtn')?.addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = '{{ $appDeepLink }}';
                
                // Fallback to app store after 2 seconds if app doesn't open
                setTimeout(() => {
                    @if($isAndroid)
                        window.location.href = '{{ $playStoreUrl }}';
                    @elseif($isIOS)
                        window.location.href = '{{ $appStoreUrl }}';
                    @endif
                }, 2000);
            });
        @endif
        
        // Universal link fallback (for iOS)
        @if($isIOS)
            // Try universal link format
            const universalLink = 'https://api.gekychat.com/app/open';
            if (window.location.search.includes('fallback')) {
                // If we're in fallback, show download options
            } else {
                // Try universal link first
                setTimeout(() => {
                    window.location.href = universalLink;
                }, 100);
            }
        @endif
    </script>
</body>
</html>

