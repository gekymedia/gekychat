@extends('layouts.app')

@section('title', 'Sign in')
@section('body_class', 'auth-landing-page')

@push('head')
<script>
  document.documentElement.dataset.theme = 'light';
  document.documentElement.setAttribute('data-theme', 'light');
</script>
@endpush

@section('content')
@php
  $downloadUrl = 'https://gekychat.com/download';
  $privacyUrl = 'https://gekychat.com/privacy-policy';
  $termsUrl = 'https://gekychat.com/terms-of-service';
  $supportUrl = 'https://gekychat.com/contact';
@endphp
<style>
  /* Force a bright auth shell regardless of app dark theme tokens */
  body.auth-landing-page,
  body.auth-landing-page .content-wrap,
  body.auth-landing-page #main-content,
  body.auth-landing-page #app {
    background: transparent !important;
    min-height: 100vh;
  }
  body.auth-landing-page {
    background:
      radial-gradient(ellipse 90% 55% at 0% 0%, rgba(15, 138, 95, 0.12), transparent 50%),
      radial-gradient(ellipse 70% 45% at 100% 10%, rgba(201, 146, 42, 0.10), transparent 45%),
      #F3F6F4 !important;
    color: #122018;
  }

  .auth-shell {
    --gek: #0F8A5F;
    --gek-dark: #0A6B49;
    --gek-soft: #E6F6EF;
    --gold: #C9922A;
    --ink: #122018;
    --muted: #5A6B62;
    --line: #D5E0DA;
    --card: #FFFFFF;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    padding: 1.25rem 1.25rem 2rem;
  }
  .auth-top {
    max-width: 920px;
    width: 100%;
    margin: 0 auto 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }
  .auth-logo {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    text-decoration: none;
    color: var(--ink);
    font-weight: 700;
    font-size: 1.2rem;
    letter-spacing: -0.02em;
  }
  .auth-logo img {
    width: 32px;
    height: 32px;
    object-fit: contain;
  }
  .auth-logo span { color: var(--gek); }

  .auth-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    width: 100%;
    max-width: 920px;
    margin: 0 auto;
  }

  .dl-banner {
    width: 100%;
    background: var(--card);
    border: 1px solid var(--line);
    border-radius: 16px;
    padding: 1rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    box-shadow: 0 8px 24px rgba(12, 26, 20, 0.04);
    animation: authRise 0.55s ease both;
  }
  .dl-banner-art {
    width: 56px;
    height: 44px;
    border-radius: 10px;
    background: linear-gradient(145deg, var(--gek-soft), #fff);
    border: 1px solid var(--line);
    display: grid;
    place-items: center;
    color: var(--gek-dark);
    font-size: 1.35rem;
    flex-shrink: 0;
  }
  .dl-banner-copy { flex: 1; min-width: 180px; }
  .dl-banner-copy strong {
    display: block;
    font-size: 0.98rem;
    color: var(--ink);
    margin-bottom: 0.15rem;
  }
  .dl-banner-copy span {
    font-size: 0.88rem;
    color: var(--muted);
  }
  .btn-auth-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    background: var(--gek);
    color: #fff !important;
    border: none;
    border-radius: 999px;
    padding: 0.7rem 1.35rem;
    font-weight: 600;
    font-size: 0.95rem;
    text-decoration: none;
    transition: background 0.2s ease, transform 0.2s ease;
    white-space: nowrap;
  }
  .btn-auth-primary:hover { background: var(--gek-dark); color: #fff !important; transform: translateY(-1px); }
  .btn-auth-outline {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    width: 100%;
    background: transparent;
    color: var(--gek-dark) !important;
    border: 1.5px solid var(--gek);
    border-radius: 999px;
    padding: 0.7rem 1.25rem;
    font-weight: 600;
    font-size: 0.95rem;
    transition: background 0.2s ease;
  }
  .btn-auth-outline:hover { background: var(--gek-soft); }

  .auth-card {
    width: 100%;
    background: var(--card);
    border: 1px solid var(--line);
    border-radius: 20px;
    padding: 2rem 2rem 1.75rem;
    box-shadow: 0 16px 40px rgba(12, 26, 20, 0.06);
    animation: authRise 0.7s ease both;
  }
  .auth-card h1 {
    font-size: clamp(1.45rem, 2.5vw, 1.75rem);
    font-weight: 700;
    letter-spacing: -0.02em;
    margin: 0 0 0.35rem;
    color: var(--ink);
  }
  .auth-lead {
    color: var(--muted);
    font-size: 0.98rem;
    margin: 0 0 1.5rem;
  }

  .auth-grid {
    display: grid;
    grid-template-columns: 1.15fr 0.85fr;
    gap: 2rem;
    align-items: start;
  }
  .field-label {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--ink);
    margin-bottom: 0.5rem;
  }
  .phone-row {
    display: flex;
    gap: 0.6rem;
    margin-bottom: 0.5rem;
  }
  .wa-badge {
    background: #F7FAF8;
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 0.75rem 0.85rem;
    font-size: 0.95rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    justify-content: space-between;
    cursor: pointer;
    color: var(--ink);
    min-height: 52px;
  }
  .wa-badge:hover { border-color: var(--gek); }
  .phone-row .form-control {
    border-radius: 12px !important;
    border: 1px solid var(--line) !important;
    background: #F7FAF8 !important;
    color: var(--ink) !important;
    min-height: 52px;
    font-size: 1.05rem;
  }
  .phone-row .form-control:focus {
    border-color: var(--gek) !important;
    box-shadow: 0 0 0 3px rgba(15, 138, 95, 0.15) !important;
  }
  .helper {
    color: var(--muted);
    font-size: 0.88rem;
  }
  .helper a { color: var(--gek-dark); font-weight: 600; text-decoration: none; }
  .helper a:hover { text-decoration: underline; }

  .divider {
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--muted);
    font-size: 0.85rem;
    margin: 1.15rem 0;
  }
  .divider:before, .divider:after {
    content: "";
    flex: 1;
    height: 1px;
    background: var(--line);
  }

  .qr-panel {
    background: #F7FAF8;
    border: 1px solid var(--line);
    border-radius: 16px;
    padding: 1.25rem;
    text-align: center;
  }
  .qr-panel h2 {
    font-size: 1rem;
    font-weight: 700;
    margin: 0 0 0.5rem;
    color: var(--ink);
  }
  .qr-panel p {
    font-size: 0.88rem;
    color: var(--muted);
    margin: 0 0 1rem;
  }
  .qr-steps {
    list-style: none;
    padding: 0;
    margin: 0 0 1rem;
    text-align: left;
    font-size: 0.88rem;
    color: var(--muted);
  }
  .qr-steps li {
    display: flex;
    gap: 0.55rem;
    margin-bottom: 0.45rem;
  }
  .qr-steps b {
    color: var(--gek-dark);
    min-width: 1.1rem;
  }

  .country-dropdown { position: relative; width: 100%; }
  .country-options {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    width: min(320px, 85vw);
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 12px;
    box-shadow: 0 16px 40px rgba(12, 26, 20, 0.12);
    z-index: 1000;
    max-height: 320px;
    overflow-y: auto;
    display: none;
  }
  .country-option {
    padding: 10px 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .country-option:hover { background: var(--gek-soft); }
  .country-flag { font-size: 1.35em; min-width: 28px; text-align: center; }
  .country-code { font-weight: 600; color: var(--ink); }
  .country-name { color: var(--muted); font-size: 0.85rem; }
  .unsupported-message {
    color: #B42318;
    font-size: 0.85rem;
    margin-top: 6px;
    display: none;
  }

  .auth-foot {
    text-align: center;
    margin-top: 1.25rem;
    font-size: 0.85rem;
    color: var(--muted);
  }
  .auth-foot a { color: var(--gek-dark); text-decoration: none; font-weight: 600; }
  .auth-foot a:hover { text-decoration: underline; }

  .btn-wa {
    background: var(--gek) !important;
    border-color: var(--gek) !important;
    color: #fff !important;
    border-radius: 999px !important;
    padding: 0.75rem 1.25rem !important;
    font-weight: 600 !important;
  }
  .btn-wa:hover { background: var(--gek-dark) !important; border-color: var(--gek-dark) !important; }
  .btn-outline-wa {
    background: transparent !important;
    border: 1.5px solid var(--gek) !important;
    color: var(--gek-dark) !important;
    border-radius: 999px !important;
    padding: 0.7rem 1.25rem !important;
    font-weight: 600 !important;
  }
  .btn-outline-wa:hover { background: var(--gek-soft) !important; }

  .modal-content.auth-modal {
    border: 1px solid var(--line);
    border-radius: 16px;
    overflow: hidden;
  }
  .modal-content.auth-modal .modal-header {
    background: var(--gek);
    color: #fff;
    border-bottom: none;
  }
  .modal-content.auth-modal .modal-body { background: #fff; color: var(--ink); }

  @keyframes authRise {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
  }

  @media (max-width: 768px) {
    .auth-grid { grid-template-columns: 1fr; }
    .qr-panel { order: 2; }
    .auth-card { padding: 1.5rem 1.15rem; }
  }
</style>

<div class="auth-shell">
  <div class="auth-top">
    <a class="auth-logo" href="https://gekychat.com">
      <img src="{{ asset('icons/icon-192x192.png') }}" alt="" onerror="this.src='{{ asset('icons/icon-512x512.png') }}'">
      Geky<span>Chat</span>
    </a>
  </div>

  <div class="auth-main">
    <div class="dl-banner">
      <div class="dl-banner-art" aria-hidden="true"><i class="bi bi-laptop"></i></div>
      <div class="dl-banner-copy">
        <strong>Download GekyChat for Windows</strong>
        <span>Desktop app with the same phone account — calls, Status, and World Feed.</span>
      </div>
      <a class="btn-auth-primary" href="{{ $downloadUrl }}">
        <i class="bi bi-download"></i> Download
      </a>
    </div>

    <div class="auth-card">
      <h1>Sign in to GekyChat</h1>
      <p class="auth-lead">Use your phone number for a one-time code, or scan a QR code from the mobile app.</p>

      @if (session('status'))
        <div class="alert alert-success mb-3">{{ session('status') }}</div>
      @endif

      @if ($errors->any())
        <div class="alert alert-danger mb-3">
          <ul class="mb-0">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <div class="auth-grid">
        <div>
          <form method="POST" action="{{ route('send.otp') }}" id="phoneLoginForm" novalidate>
            @csrf
            @method('POST')

            <div class="field-label">Enter your phone number</div>
            <div class="phone-row">
              <div style="flex: 0 0 38%; min-width: 120px;">
                <div class="country-dropdown">
                  <div class="wa-badge" id="countrySelector">
                    <span id="selectedCountry" style="display: inline-flex; align-items: center; gap: 6px;">
                      <span style="font-size: 1.3em;">🇬🇭</span> +233
                    </span>
                    <i class="bi bi-chevron-down" style="font-size: 0.85rem; opacity: 0.7;"></i>
                  </div>
                  <div class="country-options" id="countryOptions"></div>
                </div>
                <div class="unsupported-message" id="unsupportedMessage">
                  This country code is not supported yet
                </div>
              </div>
              <div style="flex: 1;">
                <input
                  type="text"
                  name="phone"
                  class="form-control form-control-lg"
                  placeholder="24 123 4567"
                  inputmode="numeric"
                  maxlength="10"
                  required
                  id="phoneInput"
                  autocomplete="tel-national"
                >
              </div>
            </div>

            <div class="helper mb-3">
              We’ll send a 6-digit code. Standard SMS rates may apply.
            </div>

            <button type="submit" class="btn btn-wa w-100" id="sendBtn">
              <i class="bi bi-phone me-1"></i> Send code
            </button>

            <div class="divider d-md-none">or</div>

            <button type="button" class="btn btn-outline-wa w-100 d-md-none mt-1" id="qrCodeBtnMobile" data-bs-toggle="modal" data-bs-target="#qrCodeModal">
              <i class="bi bi-qr-code-scan me-1"></i> Scan QR with phone
            </button>
          </form>

          <div class="helper text-center mt-3">
            Having issues? <a href="{{ $supportUrl }}" target="_blank" rel="noopener">Contact support</a>
          </div>
        </div>

        <aside class="qr-panel d-none d-md-block">
          <h2>Prefer QR?</h2>
          <p>Already signed in on your phone? Link this browser in a few seconds.</p>
          <ol class="qr-steps">
            <li><b>1</b> Open GekyChat on your phone</li>
            <li><b>2</b> Tap Linked devices / Scan QR</li>
            <li><b>3</b> Point your camera at the code</li>
          </ol>
          <button type="button" class="btn-auth-outline" id="qrCodeBtn" data-bs-toggle="modal" data-bs-target="#qrCodeModal">
            <i class="bi bi-qr-code-scan"></i> Show QR code
          </button>
        </aside>
      </div>
    </div>

    <div class="auth-foot">
      By continuing, you agree to our
      <a href="{{ $termsUrl }}" target="_blank" rel="noopener">Terms</a>
      &amp;
      <a href="{{ $privacyUrl }}" target="_blank" rel="noopener">Privacy</a>.
    </div>
  </div>
</div>

<!-- QR Code Modal -->
<div class="modal fade" id="qrCodeModal" tabindex="-1" aria-labelledby="qrCodeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content auth-modal">
      <div class="modal-header">
        <h5 class="modal-title" id="qrCodeModalLabel">Scan QR code</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <p class="helper mb-3">Open GekyChat on your phone and scan this code to sign in</p>
        <div id="qrCodeContainer" class="mb-3" style="display: none;">
          <div id="qrCodeImage" class="d-inline-block p-3 bg-white rounded border"></div>
        </div>
        <div id="qrCodeLoading" class="mb-3">
          <div class="spinner-border text-success" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="helper mt-2">Generating QR code...</p>
        </div>
        <div id="qrCodeError" class="alert alert-danger" style="display: none;"></div>
        <div id="qrCodeExpired" class="alert alert-warning" style="display: none;">
          <p>QR code has expired. Generate a new one to continue.</p>
          <button type="button" class="btn btn-wa btn-sm" onclick="generateQrCode()">Generate new QR</button>
        </div>
        <div id="qrCodeSuccess" class="alert alert-success" style="display: none;">
          <p><i class="bi bi-check-circle me-2"></i>QR scanned — signing you in…</p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    document.documentElement.dataset.theme = 'light';

    const countries = [
      { code: 'GH', flag: '🇬🇭', dialCode: '+233', name: 'Ghana', supported: true },
      { code: 'NG', flag: '🇳🇬', dialCode: '+234', name: 'Nigeria', supported: false },
      { code: 'KE', flag: '🇰🇪', dialCode: '+254', name: 'Kenya', supported: false },
      { code: 'ZA', flag: '🇿🇦', dialCode: '+27', name: 'South Africa', supported: false },
      { code: 'EG', flag: '🇪🇬', dialCode: '+20', name: 'Egypt', supported: false },
      { code: 'ET', flag: '🇪🇹', dialCode: '+251', name: 'Ethiopia', supported: false },
      { code: 'TZ', flag: '🇹🇿', dialCode: '+255', name: 'Tanzania', supported: false },
      { code: 'UG', flag: '🇺🇬', dialCode: '+256', name: 'Uganda', supported: false },
      { code: 'ZW', flag: '🇿🇼', dialCode: '+263', name: 'Zimbabwe', supported: false },
      { code: 'ZM', flag: '🇿🇲', dialCode: '+260', name: 'Zambia', supported: false },
      { code: 'AO', flag: '🇦🇴', dialCode: '+244', name: 'Angola', supported: false },
      { code: 'MA', flag: '🇲🇦', dialCode: '+212', name: 'Morocco', supported: false },
      { code: 'DZ', flag: '🇩🇿', dialCode: '+213', name: 'Algeria', supported: false },
      { code: 'TN', flag: '🇹🇳', dialCode: '+216', name: 'Tunisia', supported: false },
      { code: 'CM', flag: '🇨🇲', dialCode: '+237', name: 'Cameroon', supported: false },
      { code: 'CI', flag: '🇨🇮', dialCode: '+225', name: "Côte d'Ivoire", supported: false },
      { code: 'SN', flag: '🇸🇳', dialCode: '+221', name: 'Senegal', supported: false },
      { code: 'RW', flag: '🇷🇼', dialCode: '+250', name: 'Rwanda', supported: false },
      { code: 'BW', flag: '🇧🇼', dialCode: '+267', name: 'Botswana', supported: false },
      { code: 'MW', flag: '🇲🇼', dialCode: '+265', name: 'Malawi', supported: false },
      { code: 'US', flag: '🇺🇸', dialCode: '+1', name: 'United States', supported: false },
      { code: 'CA', flag: '🇨🇦', dialCode: '+1', name: 'Canada', supported: false },
      { code: 'MX', flag: '🇲🇽', dialCode: '+52', name: 'Mexico', supported: false },
      { code: 'BR', flag: '🇧🇷', dialCode: '+55', name: 'Brazil', supported: false },
      { code: 'AR', flag: '🇦🇷', dialCode: '+54', name: 'Argentina', supported: false },
      { code: 'CO', flag: '🇨🇴', dialCode: '+57', name: 'Colombia', supported: false },
      { code: 'PE', flag: '🇵🇪', dialCode: '+51', name: 'Peru', supported: false },
      { code: 'VE', flag: '🇻🇪', dialCode: '+58', name: 'Venezuela', supported: false },
      { code: 'CL', flag: '🇨🇱', dialCode: '+56', name: 'Chile', supported: false },
      { code: 'EC', flag: '🇪🇨', dialCode: '+593', name: 'Ecuador', supported: false },
      { code: 'GT', flag: '🇬🇹', dialCode: '+502', name: 'Guatemala', supported: false },
      { code: 'CU', flag: '🇨🇺', dialCode: '+53', name: 'Cuba', supported: false },
      { code: 'BO', flag: '🇧🇴', dialCode: '+591', name: 'Bolivia', supported: false },
      { code: 'DO', flag: '🇩🇴', dialCode: '+1', name: 'Dominican Republic', supported: false },
      { code: 'HN', flag: '🇭🇳', dialCode: '+504', name: 'Honduras', supported: false },
      { code: 'PY', flag: '🇵🇾', dialCode: '+595', name: 'Paraguay', supported: false },
      { code: 'SV', flag: '🇸🇻', dialCode: '+503', name: 'El Salvador', supported: false },
      { code: 'NI', flag: '🇳🇮', dialCode: '+505', name: 'Nicaragua', supported: false },
      { code: 'CR', flag: '🇨🇷', dialCode: '+506', name: 'Costa Rica', supported: false },
      { code: 'PA', flag: '🇵🇦', dialCode: '+507', name: 'Panama', supported: false },
      { code: 'IN', flag: '🇮🇳', dialCode: '+91', name: 'India', supported: false },
      { code: 'CN', flag: '🇨🇳', dialCode: '+86', name: 'China', supported: false },
      { code: 'JP', flag: '🇯🇵', dialCode: '+81', name: 'Japan', supported: false },
      { code: 'KR', flag: '🇰🇷', dialCode: '+82', name: 'South Korea', supported: false },
      { code: 'ID', flag: '🇮🇩', dialCode: '+62', name: 'Indonesia', supported: false },
      { code: 'PK', flag: '🇵🇰', dialCode: '+92', name: 'Pakistan', supported: false },
      { code: 'BD', flag: '🇧🇩', dialCode: '+880', name: 'Bangladesh', supported: false },
      { code: 'PH', flag: '🇵🇭', dialCode: '+63', name: 'Philippines', supported: false },
      { code: 'VN', flag: '🇻🇳', dialCode: '+84', name: 'Vietnam', supported: false },
      { code: 'TH', flag: '🇹🇭', dialCode: '+66', name: 'Thailand', supported: false },
      { code: 'MY', flag: '🇲🇾', dialCode: '+60', name: 'Malaysia', supported: false },
      { code: 'SG', flag: '🇸🇬', dialCode: '+65', name: 'Singapore', supported: false },
      { code: 'MM', flag: '🇲🇲', dialCode: '+95', name: 'Myanmar', supported: false },
      { code: 'KH', flag: '🇰🇭', dialCode: '+855', name: 'Cambodia', supported: false },
      { code: 'LA', flag: '🇱🇦', dialCode: '+856', name: 'Laos', supported: false },
      { code: 'TW', flag: '🇹🇼', dialCode: '+886', name: 'Taiwan', supported: false },
      { code: 'HK', flag: '🇭🇰', dialCode: '+852', name: 'Hong Kong', supported: false },
      { code: 'MO', flag: '🇲🇴', dialCode: '+853', name: 'Macau', supported: false },
      { code: 'MN', flag: '🇲🇳', dialCode: '+976', name: 'Mongolia', supported: false },
      { code: 'NP', flag: '🇳🇵', dialCode: '+977', name: 'Nepal', supported: false },
      { code: 'LK', flag: '🇱🇰', dialCode: '+94', name: 'Sri Lanka', supported: false },
      { code: 'AF', flag: '🇦🇫', dialCode: '+93', name: 'Afghanistan', supported: false },
      { code: 'IQ', flag: '🇮🇶', dialCode: '+964', name: 'Iraq', supported: false },
      { code: 'SA', flag: '🇸🇦', dialCode: '+966', name: 'Saudi Arabia', supported: false },
      { code: 'AE', flag: '🇦🇪', dialCode: '+971', name: 'United Arab Emirates', supported: false },
      { code: 'IL', flag: '🇮🇱', dialCode: '+972', name: 'Israel', supported: false },
      { code: 'TR', flag: '🇹🇷', dialCode: '+90', name: 'Turkey', supported: false },
      { code: 'IR', flag: '🇮🇷', dialCode: '+98', name: 'Iran', supported: false },
      { code: 'JO', flag: '🇯🇴', dialCode: '+962', name: 'Jordan', supported: false },
      { code: 'LB', flag: '🇱🇧', dialCode: '+961', name: 'Lebanon', supported: false },
      { code: 'KW', flag: '🇰🇼', dialCode: '+965', name: 'Kuwait', supported: false },
      { code: 'OM', flag: '🇴🇲', dialCode: '+968', name: 'Oman', supported: false },
      { code: 'QA', flag: '🇶🇦', dialCode: '+974', name: 'Qatar', supported: false },
      { code: 'BH', flag: '🇧🇭', dialCode: '+973', name: 'Bahrain', supported: false },
      { code: 'YE', flag: '🇾🇪', dialCode: '+967', name: 'Yemen', supported: false },
      { code: 'SY', flag: '🇸🇾', dialCode: '+963', name: 'Syria', supported: false },
      { code: 'PS', flag: '🇵🇸', dialCode: '+970', name: 'Palestine', supported: false },
      { code: 'GB', flag: '🇬🇧', dialCode: '+44', name: 'United Kingdom', supported: false },
      { code: 'FR', flag: '🇫🇷', dialCode: '+33', name: 'France', supported: false },
      { code: 'DE', flag: '🇩🇪', dialCode: '+49', name: 'Germany', supported: false },
      { code: 'IT', flag: '🇮🇹', dialCode: '+39', name: 'Italy', supported: false },
      { code: 'ES', flag: '🇪🇸', dialCode: '+34', name: 'Spain', supported: false },
      { code: 'NL', flag: '🇳🇱', dialCode: '+31', name: 'Netherlands', supported: false },
      { code: 'BE', flag: '🇧🇪', dialCode: '+32', name: 'Belgium', supported: false },
      { code: 'CH', flag: '🇨🇭', dialCode: '+41', name: 'Switzerland', supported: false },
      { code: 'AT', flag: '🇦🇹', dialCode: '+43', name: 'Austria', supported: false },
      { code: 'SE', flag: '🇸🇪', dialCode: '+46', name: 'Sweden', supported: false },
      { code: 'NO', flag: '🇳🇴', dialCode: '+47', name: 'Norway', supported: false },
      { code: 'DK', flag: '🇩🇰', dialCode: '+45', name: 'Denmark', supported: false },
      { code: 'FI', flag: '🇫🇮', dialCode: '+358', name: 'Finland', supported: false },
      { code: 'PL', flag: '🇵🇱', dialCode: '+48', name: 'Poland', supported: false },
      { code: 'PT', flag: '🇵🇹', dialCode: '+351', name: 'Portugal', supported: false },
      { code: 'GR', flag: '🇬🇷', dialCode: '+30', name: 'Greece', supported: false },
      { code: 'IE', flag: '🇮🇪', dialCode: '+353', name: 'Ireland', supported: false },
      { code: 'CZ', flag: '🇨🇿', dialCode: '+420', name: 'Czech Republic', supported: false },
      { code: 'HU', flag: '🇭🇺', dialCode: '+36', name: 'Hungary', supported: false },
      { code: 'RO', flag: '🇷🇴', dialCode: '+40', name: 'Romania', supported: false },
      { code: 'BG', flag: '🇧🇬', dialCode: '+359', name: 'Bulgaria', supported: false },
      { code: 'HR', flag: '🇭🇷', dialCode: '+385', name: 'Croatia', supported: false },
      { code: 'RS', flag: '🇷🇸', dialCode: '+381', name: 'Serbia', supported: false },
      { code: 'SK', flag: '🇸🇰', dialCode: '+421', name: 'Slovakia', supported: false },
      { code: 'SI', flag: '🇸🇮', dialCode: '+386', name: 'Slovenia', supported: false },
      { code: 'EE', flag: '🇪🇪', dialCode: '+372', name: 'Estonia', supported: false },
      { code: 'LV', flag: '🇱🇻', dialCode: '+371', name: 'Latvia', supported: false },
      { code: 'LT', flag: '🇱🇹', dialCode: '+370', name: 'Lithuania', supported: false },
      { code: 'UA', flag: '🇺🇦', dialCode: '+380', name: 'Ukraine', supported: false },
      { code: 'RU', flag: '🇷🇺', dialCode: '+7', name: 'Russia', supported: false },
      { code: 'BY', flag: '🇧🇾', dialCode: '+375', name: 'Belarus', supported: false },
      { code: 'MD', flag: '🇲🇩', dialCode: '+373', name: 'Moldova', supported: false },
      { code: 'IS', flag: '🇮🇸', dialCode: '+354', name: 'Iceland', supported: false },
      { code: 'LU', flag: '🇱🇺', dialCode: '+352', name: 'Luxembourg', supported: false },
      { code: 'MT', flag: '🇲🇹', dialCode: '+356', name: 'Malta', supported: false },
      { code: 'CY', flag: '🇨🇾', dialCode: '+357', name: 'Cyprus', supported: false },
      { code: 'AL', flag: '🇦🇱', dialCode: '+355', name: 'Albania', supported: false },
      { code: 'MK', flag: '🇲🇰', dialCode: '+389', name: 'North Macedonia', supported: false },
      { code: 'BA', flag: '🇧🇦', dialCode: '+387', name: 'Bosnia and Herzegovina', supported: false },
      { code: 'AU', flag: '🇦🇺', dialCode: '+61', name: 'Australia', supported: false },
      { code: 'NZ', flag: '🇳🇿', dialCode: '+64', name: 'New Zealand', supported: false },
      { code: 'FJ', flag: '🇫🇯', dialCode: '+679', name: 'Fiji', supported: false },
      { code: 'PG', flag: '🇵🇬', dialCode: '+675', name: 'Papua New Guinea', supported: false },
      { code: 'NC', flag: '🇳🇨', dialCode: '+687', name: 'New Caledonia', supported: false },
      { code: 'PF', flag: '🇵🇫', dialCode: '+689', name: 'French Polynesia', supported: false }
    ];

    const form = document.getElementById('phoneLoginForm');
    const btn = document.getElementById('sendBtn');
    const phoneInput = document.getElementById('phoneInput');
    const countrySelector = document.getElementById('countrySelector');
    const countryOptions = document.getElementById('countryOptions');
    const selectedCountry = document.getElementById('selectedCountry');
    const unsupportedMessage = document.getElementById('unsupportedMessage');

    let currentCountry = countries[0];

    function populateCountryOptions() {
      countryOptions.innerHTML = '';
      const sortedCountries = [...countries].sort((a, b) => {
        if (a.code === 'GH') return -1;
        if (b.code === 'GH') return 1;
        return a.name.localeCompare(b.name);
      });
      sortedCountries.forEach(country => {
        const option = document.createElement('div');
        option.className = 'country-option';
        option.innerHTML = `
          <span class="country-flag">${country.flag}</span>
          <span class="country-code">${country.dialCode}</span>
          <span class="country-name">${country.name}</span>
        `;
        option.addEventListener('click', () => {
          selectCountry(country);
          closeCountryDropdown();
        });
        countryOptions.appendChild(option);
      });
    }

    function selectCountry(country) {
      currentCountry = country;
      selectedCountry.innerHTML = `<span style="font-size: 1.3em;">${country.flag}</span> ${country.dialCode}`;
      unsupportedMessage.style.display = country.supported ? 'none' : 'block';
    }

    function toggleCountryDropdown() {
      countryOptions.style.display = countryOptions.style.display === 'block' ? 'none' : 'block';
    }
    function closeCountryDropdown() { countryOptions.style.display = 'none'; }

    function normalizeGhanaLoginPhone(raw) {
      let d = String(raw || '').replace(/\D/g, '');
      if (d.startsWith('233') && d.length >= 11) d = d.slice(3);
      if (d.startsWith('0')) d = d.slice(1);
      if (d.length > 9) d = d.slice(-9);
      if (d.length !== 9) return '';
      return '0' + d;
    }

    form?.addEventListener('submit', function (e) {
      if (!currentCountry.supported) {
        e.preventDefault();
        alert(`Phone numbers from ${currentCountry.name} are not supported yet`);
        return;
      }
      const normalized = normalizeGhanaLoginPhone(phoneInput?.value || '');
      if (!normalized) {
        e.preventDefault();
        alert('Please enter a valid mobile number');
        return;
      }
      if (phoneInput) phoneInput.value = normalized;

      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      const formToken = form.querySelector('input[name="_token"]')?.value;
      if (!csrfToken && !formToken) {
        e.preventDefault();
        alert('Security token missing. Please refresh the page and try again.');
        return;
      }

      btn.disabled = true;
      const original = btn.innerHTML;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Sending...';
      setTimeout(() => { btn.innerHTML = original; btn.disabled = false; }, 6000);
    });

    phoneInput?.addEventListener('input', (e) => {
      e.target.value = e.target.value.replace(/\D/g, '').slice(0, 10);
    });

    countrySelector.addEventListener('click', toggleCountryDropdown);
    document.addEventListener('click', (e) => {
      if (!countrySelector.contains(e.target) && !countryOptions.contains(e.target)) {
        closeCountryDropdown();
      }
    });

    populateCountryOptions();
    selectCountry(currentCountry);

    let qrCodePollInterval = null;
    let currentSessionToken = null;

    function generateQrCodeImage(qrUrl) {
      const qrImageUrl = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(qrUrl)}`;
      const img = document.createElement('img');
      img.src = qrImageUrl;
      img.alt = 'QR Code';
      img.className = 'img-fluid';
      img.style.maxWidth = '300px';
      return img;
    }

    window.generateQrCode = async function generateQrCode() {
      const container = document.getElementById('qrCodeContainer');
      const loading = document.getElementById('qrCodeLoading');
      const error = document.getElementById('qrCodeError');
      const expired = document.getElementById('qrCodeExpired');
      const success = document.getElementById('qrCodeSuccess');
      const qrImage = document.getElementById('qrCodeImage');

      container.style.display = 'none';
      loading.style.display = 'block';
      error.style.display = 'none';
      expired.style.display = 'none';
      success.style.display = 'none';
      qrImage.innerHTML = '';

      try {
        const response = await fetch('{{ route("qr.code") }}');
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Failed to generate QR code');

        currentSessionToken = data.session_token;
        const img = generateQrCodeImage(data.qr_url);
        qrImage.appendChild(img);
        loading.style.display = 'none';
        container.style.display = 'block';
        startPolling(data.session_token);
        setTimeout(() => {
          stopPolling();
          container.style.display = 'none';
          expired.style.display = 'block';
        }, data.expires_in * 1000);
      } catch (err) {
        loading.style.display = 'none';
        error.style.display = 'block';
        error.textContent = err.message || 'Failed to generate QR code. Please try again.';
      }
    };

    function startPolling(sessionToken) {
      stopPolling();
      qrCodePollInterval = setInterval(async () => {
        try {
          const response = await fetch(`{{ url('/login/qr-status') }}/${sessionToken}`);
          const data = await response.json();
          if (data.status === 'authenticated') {
            stopPolling();
            document.getElementById('qrCodeContainer').style.display = 'none';
            document.getElementById('qrCodeSuccess').style.display = 'block';
            setTimeout(() => {
              window.location.href = data.redirect || '{{ route("chat.index") }}';
            }, 1000);
          } else if (data.status === 'expired') {
            stopPolling();
            document.getElementById('qrCodeContainer').style.display = 'none';
            document.getElementById('qrCodeExpired').style.display = 'block';
          }
        } catch (err) {
          console.error('QR status polling error:', err);
        }
      }, 2000);
    }

    function stopPolling() {
      if (qrCodePollInterval) {
        clearInterval(qrCodePollInterval);
        qrCodePollInterval = null;
      }
    }

    document.getElementById('qrCodeModal').addEventListener('show.bs.modal', generateQrCode);
    document.getElementById('qrCodeModal').addEventListener('hide.bs.modal', function () {
      stopPolling();
      currentSessionToken = null;
    });
  })();
</script>
@endsection
