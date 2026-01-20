@extends('layouts.app')

@section('content')
<style>
  .auth-wrap { min-height: calc(100vh - 120px); display:flex; align-items:center; }
  .wa-card { background: var(--wa-card); color: var(--wa-text); border:1px solid var(--wa-border);
    border-radius:20px; overflow:hidden; box-shadow: var(--wa-shadow); }
  .wa-head { background: linear-gradient(135deg, var(--wa-deep), var(--wa-green)); color:#fff; padding:26px 24px; }
  .wa-body { padding: 26px 24px; }
  .wa-badge { 
    background: rgba(255,255,255,.12); 
    border:1px solid rgba(255,255,255,.18);
    border-radius: 999px; 
    padding:6px 10px; 
    font-size:.875rem; 
    display:inline-flex; 
    align-items:center; 
    gap:8px; 
    width:100%; 
    justify-content:center; 
    cursor: pointer;
  }
  .brand-row { display:flex; align-items:center; gap:10px; }
  .brand-icon { width:26px; height:26px; display:inline-grid; place-items:center; border-radius:7px; background:rgba(255,255,255,.18) }
  .brand-title { font-weight:700; letter-spacing:.3px }
  .helper { color: var(--wa-muted); font-size:.9rem; }
  .divider { display:flex; align-items:center; gap:12px; color:#7d97a6; }
  .divider:before, .divider:after { content:""; flex:1; height:1px; background: var(--wa-border); }
  
  .country-dropdown {
    position: relative;
    width: 100%;
  }
  
  .country-options {
    position: absolute;
    top: 100%;
    left: 0;
    width: 100%;
    background: var(--wa-card);
    border: 1px solid var(--wa-border);
    border-radius: 12px;
    box-shadow: var(--wa-shadow);
    z-index: 1000;
    max-height: 350px;
    overflow-y: auto;
    display: none;
    margin-top: 5px;
  }
  
  .country-option {
    padding: 12px 15px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: background-color 0.2s;
  }
  
  .country-option:hover {
    background-color: color-mix(in srgb, var(--wa-green) 10%, transparent);
  }
  
  .country-flag {
    font-size: 1.5em;
    line-height: 1;
    min-width: 28px;
    text-align: center;
    flex-shrink: 0;
  }
  
  .country-code {
    font-weight: 500;
  }
  
  .country-name {
    color: var(--wa-muted);
    font-size: 0.85rem;
  }
  
  .unsupported-message {
    color: #dc3545;
    font-size: 0.85rem;
    margin-top: 5px;
    display: none;
  }
</style>

<div class="container auth-wrap">
  <div class="row justify-content-center w-100">
    <div class="col-12 col-md-8 col-lg-6 col-xxl-5">

      <div class="wa-card">
        <div class="wa-head">
          <div class="brand-row">
            <div class="brand-icon">
              <img src="{{ asset('icons/theme/white_no_text/32x32.png') }}" alt="GekyChat" width="16" height="16" style="object-fit: contain;">
            </div>
            <div>
              <div class="brand-title">GekyChat</div>
              <div class="helper">Sign in with your phone</div>
            </div>
          </div>
        </div>

        <div class="wa-body">
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

          <form method="POST" action="{{ route('send.otp') }}" id="phoneLoginForm" novalidate>
            @csrf
            @method('POST')

            <div class="mb-2 helper">Enter your phone number</div>

            <div class="row g-2 align-items-center mb-3">
              <div class="col-5 col-sm-4">
                <div class="country-dropdown">
                  <div class="wa-badge" id="countrySelector">
                    <span id="selectedCountry" style="font-size: 1.2em; display: inline-flex; align-items: center; gap: 6px;">
                      <span style="font-size: 1.3em;">🇬🇭</span> +233
                    </span>
                    <i class="fas fa-chevron-down ms-1" style="font-size: 0.8rem;"></i>
                  </div>
                  <div class="country-options" id="countryOptions">
                    <!-- Country options will be populated by JavaScript -->
                  </div>
                </div>
                <div class="unsupported-message" id="unsupportedMessage">
                  This country code is not supported yet
                </div>
              </div>
              <div class="col-7 col-sm-8">
                <input
                  type="text"
                  name="phone"
                  class="form-control form-control-lg"
                  placeholder="Phone Number"
                  inputmode="numeric"
                  pattern="0[0-9]{9}"
                  maxlength="10"
                  required
                  id="phoneInput"
                >
              </div>
            </div>

            <div class="helper mb-3">
              We'll send a 6-digit code. Standard SMS rates may apply.
            </div>

            <button type="submit" class="btn btn-wa w-100" id="sendBtn">
              <span class="me-2" aria-hidden="true">📲</span> Send OTP
            </button>

            <div class="my-3 divider">or</div>

            <button type="button" class="btn btn-outline-wa w-100" id="qrCodeBtn" data-bs-toggle="modal" data-bs-target="#qrCodeModal">
              <span class="me-2" aria-hidden="true">📷</span> Scan QR Code with Phone
            </button>

            <div class="my-3 divider">or</div>

            <div class="helper text-center">
              Having issues? <a href="https://wa.me/233205440495?text=Hi%20GekyChat%20support" target="_blank" rel="noopener">Contact support</a>
            </div>
          </form>
        </div>
      </div>

      <div class="text-center mt-3 helper">
        By continuing, you agree to our <a href="#" class="text-decoration-none">Terms</a> & <a href="#" class="text-decoration-none">Privacy</a>.
      </div>
    </div>
  </div>
</div>

<!-- QR Code Modal -->
<div class="modal fade" id="qrCodeModal" tabindex="-1" aria-labelledby="qrCodeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content wa-card">
      <div class="modal-header wa-head">
        <h5 class="modal-title" id="qrCodeModalLabel">Scan QR Code</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body wa-body text-center">
        <p class="helper mb-3">Open GekyChat on your phone and scan this QR code to log in</p>
        <div id="qrCodeContainer" class="mb-3" style="display: none;">
          <div id="qrCodeImage" class="d-inline-block p-3 bg-white rounded"></div>
        </div>
        <div id="qrCodeLoading" class="mb-3">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="helper mt-2">Generating QR code...</p>
        </div>
        <div id="qrCodeError" class="alert alert-danger" style="display: none;"></div>
        <div id="qrCodeExpired" class="alert alert-warning" style="display: none;">
          <p>QR code has expired. Please close this and generate a new one.</p>
          <button type="button" class="btn btn-wa btn-sm" onclick="generateQrCode()">Generate New QR Code</button>
        </div>
        <div id="qrCodeSuccess" class="alert alert-success" style="display: none;">
          <p><i class="fas fa-check-circle me-2"></i>QR code scanned successfully! Logging you in...</p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    // Country data - Expanded list with flags
    const countries = [
      // Africa
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
      
      // Americas
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
      
      // Asia
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
      
      // Europe
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
      
      // Oceania
      { code: 'AU', flag: '🇦🇺', dialCode: '+61', name: 'Australia', supported: false },
      { code: 'NZ', flag: '🇳🇿', dialCode: '+64', name: 'New Zealand', supported: false },
      { code: 'FJ', flag: '🇫🇯', dialCode: '+679', name: 'Fiji', supported: false },
      { code: 'PG', flag: '🇵🇬', dialCode: '+675', name: 'Papua New Guinea', supported: false },
      { code: 'NC', flag: '🇳🇨', dialCode: '+687', name: 'New Caledonia', supported: false },
      { code: 'PF', flag: '🇵🇫', dialCode: '+689', name: 'French Polynesia', supported: false }
    ];

    // DOM elements
    const form = document.getElementById('phoneLoginForm');
    const btn = document.getElementById('sendBtn');
    const phoneInput = document.getElementById('phoneInput');
    const countrySelector = document.getElementById('countrySelector');
    const countryOptions = document.getElementById('countryOptions');
    const selectedCountry = document.getElementById('selectedCountry');
    const unsupportedMessage = document.getElementById('unsupportedMessage');

    // Currently selected country
    let currentCountry = countries[0]; // Default to Ghana

    // Populate country options
    function populateCountryOptions() {
      countryOptions.innerHTML = '';
      
      // Sort countries: Ghana first (default/supported), then alphabetically
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

    // Select a country
    function selectCountry(country) {
      currentCountry = country;
      selectedCountry.innerHTML = `<span style="font-size: 1.3em;">${country.flag}</span> ${country.dialCode}`;
      
      // Show/hide unsupported message
      if (!country.supported) {
        unsupportedMessage.style.display = 'block';
      } else {
        unsupportedMessage.style.display = 'none';
      }
    }

    // Toggle country dropdown
    function toggleCountryDropdown() {
      if (countryOptions.style.display === 'block') {
        closeCountryDropdown();
      } else {
        openCountryDropdown();
      }
    }

    // Open country dropdown
    function openCountryDropdown() {
      countryOptions.style.display = 'block';
    }

    // Close country dropdown
    function closeCountryDropdown() {
      countryOptions.style.display = 'none';
    }

    // Form submission handler
    form?.addEventListener('submit', function (e) {
      // Check if country is supported
      if (!currentCountry.supported) {
        e.preventDefault();
        alert(`Phone numbers from ${currentCountry.name} are not supported yet`);
        return;
      }
      
      // Ensure CSRF token is present before submitting
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      const formToken = form.querySelector('input[name="_token"]')?.value;
      
      if (!csrfToken && !formToken) {
        e.preventDefault();
        alert('Security token missing. Please refresh the page and try again.');
        console.error('CSRF token missing!');
        return;
      }
      
      // Continue with original form submission (don't prevent default)
      btn.disabled = true;
      const original = btn.innerHTML;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Sending...';
      setTimeout(() => { btn.innerHTML = original; btn.disabled = false; }, 6000);
    });

    // Restrict to digits and enforce leading 0
    phoneInput?.addEventListener('input', (e) => {
      let v = e.target.value.replace(/\D/g,'');
      if (v && v[0] !== '0') v = '0' + v.slice(0,9);
      e.target.value = v.slice(0,10);
    });

    // Event listeners
    countrySelector.addEventListener('click', toggleCountryDropdown);
    
    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
      if (!countrySelector.contains(e.target) && !countryOptions.contains(e.target)) {
        closeCountryDropdown();
      }
    });

    // Initialize
    populateCountryOptions();
    selectCountry(currentCountry);

    // QR Code functionality
    let qrCodePollInterval = null;
    let currentSessionToken = null;

    // Generate QR code using an online service (simple approach)
    function generateQrCodeImage(qrUrl) {
      // Use QR Server API (free, no API key needed)
      const qrImageUrl = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(qrUrl)}`;
      const img = document.createElement('img');
      img.src = qrImageUrl;
      img.alt = 'QR Code';
      img.className = 'img-fluid';
      img.style.maxWidth = '300px';
      return img;
    }

    // Generate QR code
    async function generateQrCode() {
      const container = document.getElementById('qrCodeContainer');
      const loading = document.getElementById('qrCodeLoading');
      const error = document.getElementById('qrCodeError');
      const expired = document.getElementById('qrCodeExpired');
      const success = document.getElementById('qrCodeSuccess');
      const qrImage = document.getElementById('qrCodeImage');

      // Reset states
      container.style.display = 'none';
      loading.style.display = 'block';
      error.style.display = 'none';
      expired.style.display = 'none';
      success.style.display = 'none';
      qrImage.innerHTML = '';

      try {
        const response = await fetch('{{ route("qr.code") }}');
        const data = await response.json();

        if (!response.ok) {
          throw new Error(data.message || 'Failed to generate QR code');
        }

        currentSessionToken = data.session_token;
        const qrUrl = data.qr_url;

        // Generate and display QR code image
        const img = generateQrCodeImage(qrUrl);
        qrImage.appendChild(img);

        loading.style.display = 'none';
        container.style.display = 'block';

        // Start polling for authentication status
        startPolling(data.session_token);

        // Set expiration timeout
        setTimeout(() => {
          stopPolling();
          container.style.display = 'none';
          expired.style.display = 'block';
        }, data.expires_in * 1000);

      } catch (err) {
        loading.style.display = 'none';
        error.style.display = 'block';
        error.textContent = err.message || 'Failed to generate QR code. Please try again.';
        console.error('QR Code generation error:', err);
      }
    }

    // Poll for QR code authentication status
    function startPolling(sessionToken) {
      stopPolling(); // Clear any existing polling

      qrCodePollInterval = setInterval(async () => {
        try {
          const response = await fetch(`{{ url('/login/qr-status') }}/${sessionToken}`);
          const data = await response.json();

          if (data.status === 'authenticated') {
            stopPolling();
            document.getElementById('qrCodeContainer').style.display = 'none';
            document.getElementById('qrCodeSuccess').style.display = 'block';

            // Redirect after short delay
            setTimeout(() => {
              if (data.redirect) {
                window.location.href = data.redirect;
              } else {
                window.location.href = '{{ route("chat.index") }}';
              }
            }, 1000);
          } else if (data.status === 'expired') {
            stopPolling();
            document.getElementById('qrCodeContainer').style.display = 'none';
            document.getElementById('qrCodeExpired').style.display = 'block';
          }
        } catch (err) {
          console.error('QR status polling error:', err);
        }
      }, 2000); // Poll every 2 seconds
    }

    // Stop polling
    function stopPolling() {
      if (qrCodePollInterval) {
        clearInterval(qrCodePollInterval);
        qrCodePollInterval = null;
      }
    }

    // Generate QR code when modal is opened
    document.getElementById('qrCodeModal').addEventListener('show.bs.modal', function () {
      generateQrCode();
    });

    // Clean up when modal is closed
    document.getElementById('qrCodeModal').addEventListener('hide.bs.modal', function () {
      stopPolling();
      currentSessionToken = null;
    });
  })();
</script>
@endsection