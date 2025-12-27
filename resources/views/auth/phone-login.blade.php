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
    max-height: 200px;
    overflow-y: auto;
    display: none;
    margin-top: 5px;
  }
  
  .country-option {
    padding: 10px 15px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  
  .country-option:hover {
    background-color: color-mix(in srgb, var(--wa-green) 10%, transparent);
  }
  
  .country-flag {
    width: 20px;
    text-align: center;
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
              <svg width="16" height="16" viewBox="0 0 24 24" fill="#fff" aria-hidden="true">
                <path d="M20.52 3.48A11.94 11.94 0 0012 0C5.37 0 0 5.37 0 12c0 2.11.55 4.09 1.52 5.81L0 24l6.36-1.67A11.94 11.94 0 0012 24c6.63 0 12-5.37 12-12 0-3.2-1.28-6.18-3.48-8.52zM12 21.6a9.56 9.56 0 01-4.87-1.34l-.35-.21-3.76.99 1.01-3.65-.23-.38A9.55 9.55 0 012.4 12c0-5.29 4.31-9.6 9.6-9.6s9.6 4.31 9.6 9.6-4.31 9.6-9.6 9.6zm5.47-6.88c-.3-.15-1.79-.89-2.07-.98-.28-.1-.48-.15-.68.15-.2.3-.78.98-.96 1.19-.18.2-.36.22-.66.07-.3-.15-1.26-.46-2.4-1.47-.88-.79-1.47-1.76-1.65-2.06-.17-.3-.02-.46.13-.61.13-.13.3-.36.45-.54.15-.18.2-.3.3-.5.1-.2.05-.38-.02-.53-.07-.15-.68-1.64-.93-2.25-.24-.58-.49-.5-.68-.51l-.58-.01c-.2 0-.53.08-.81.38-.28.3-1.07 1.05-1.07 2.56s1.1 2.96 1.26 3.17c.15.2 2.16 3.31 5.23 4.64.73.32 1.29.52 1.73.66.73.23 1.4.2 1.93.12.59-.09 1.79-.73 2.05-1.44.25-.71.25-1.33.17-1.46-.07-.13-.27-.2-.57-.35z"/>
              </svg>
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
                    <span id="selectedCountry">🇬🇭 +233</span>
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

<script>
  (function () {
    // Country data
    const countries = [
      { code: 'GH', flag: '🇬🇭', dialCode: '+233', name: 'Ghana', supported: true },
      { code: 'US', flag: '🇺🇸', dialCode: '+1', name: 'United States', supported: false },
      { code: 'GB', flag: '🇬🇧', dialCode: '+44', name: 'United Kingdom', supported: false },
      { code: 'NG', flag: '🇳🇬', dialCode: '+234', name: 'Nigeria', supported: false },
      { code: 'KE', flag: '🇰🇪', dialCode: '+254', name: 'Kenya', supported: false },
      { code: 'ZA', flag: '🇿🇦', dialCode: '+27', name: 'South Africa', supported: false },
      { code: 'IN', flag: '🇮🇳', dialCode: '+91', name: 'India', supported: false },
      { code: 'CA', flag: '🇨🇦', dialCode: '+1', name: 'Canada', supported: false }
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
      
      countries.forEach(country => {
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
      selectedCountry.innerHTML = `${country.flag} ${country.dialCode}`;
      
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
  })();
</script>
@endsection