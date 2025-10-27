<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline - GekyChat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --wa-green: #25D366;
            --wa-deep: #128C7E;
            --bg: #0B141A;
            --text: #E9EDF0;
            --card: #111B21;
            --border: #22303A;
        }

        [data-theme="light"] {
            --bg: #FFFFFF;
            --text: #0B141A;
            --card: #F8FAFC;
            --border: #E2E8F0;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Nunito', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
            transition: background-color 0.25s ease, color 0.25s ease;
        }

        .offline-container {
            max-width: 400px;
            width: 100%;
            text-align: center;
        }

        .offline-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 40px 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
        }

        .offline-icon {
            font-size: 4rem;
            color: var(--wa-green);
            margin-bottom: 20px;
        }

        .offline-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--text);
        }

        .offline-message {
            color: var(--text);
            opacity: 0.8;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .btn-retry {
            background: var(--wa-green);
            border: none;
            color: #062a1f;
            font-weight: 700;
            border-radius: 14px;
            padding: 12px 24px;
            transition: filter 0.2s ease;
        }

        .btn-retry:hover {
            filter: brightness(1.05);
        }

        .connection-tips {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .connection-tips h6 {
            font-size: 0.9rem;
            margin-bottom: 10px;
            color: var(--text);
            opacity: 0.8;
        }

        .connection-tips ul {
            list-style: none;
            padding: 0;
            margin: 0;
            font-size: 0.85rem;
            opacity: 0.7;
        }

        .connection-tips li {
            margin-bottom: 5px;
        }

        .connection-tips li:before {
            content: "•";
            color: var(--wa-green);
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="offline-container">
        <div class="offline-card">
            <div class="offline-icon">
                <i class="bi bi-wifi-off"></i>
            </div>
            
            <h1 class="offline-title">You're Offline</h1>
            
            <p class="offline-message">
                GekyChat requires an internet connection to send and receive messages. 
                Please check your connection and try again.
            </p>

            <button class="btn btn-retry" onclick="window.location.reload()">
                <i class="bi bi-arrow-clockwise me-2"></i>
                Try Again
            </button>

            <div class="connection-tips">
                <h6>Connection Tips:</h6>
                <ul>
                    <li>Check your Wi-Fi or mobile data</li>
                    <li>Restart your router</li>
                    <li>Disable VPN if active</li>
                    <li>Check airplane mode settings</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // Auto-retry when connection is restored
        window.addEventListener('online', function() {
            window.location.reload();
        });

        // Check connection status periodically
        setInterval(function() {
            if (navigator.onLine) {
                window.location.reload();
            }
        }, 5000);

        // Theme detection (matches your app's theme system)
        (function() {
            try {
                const saved = localStorage.getItem('theme');
                const system = matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
                document.documentElement.dataset.theme = saved || system || 'dark';
            } catch {
                document.documentElement.dataset.theme = 'dark';
            }
        })();
    </script>
</body>
</html>