@extends('layouts.app')

@section('content')
<style>
  .auth-wrap { min-height: calc(100vh - 120px); display:flex; align-items:center; }
  .wa-card { background: var(--wa-card); color: var(--wa-text); border:1px solid var(--wa-border);
    border-radius:20px; overflow:hidden; box-shadow: var(--wa-shadow); }
  .wa-head { background: linear-gradient(135deg, var(--wa-deep), var(--wa-green)); color:#fff; padding:26px 24px; }
  .wa-body { padding: 26px 24px; }
  .wa-badge { background: rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.18);
    border-radius: 999px; padding:6px 10px; font-size:.875rem; display:inline-flex; align-items:center; gap:8px; width:100%; justify-content:center; }
  .brand-row { display:flex; align-items:center; gap:10px; }
  .brand-icon { width:26px; height:26px; display:inline-grid; place-items:center; border-radius:7px; background:rgba(255,255,255,.18) }
  .brand-title { font-weight:700; letter-spacing:.3px }
  .helper { color: var(--wa-muted); font-size:.9rem; }
  .divider { display:flex; align-items:center; gap:12px; color:#7d97a6; }
  .divider:before, .divider:after { content:""; flex:1; height:1px; background: var(--wa-border); }
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

            <div class="mb-2 helper">Enter your Ghana number</div>

            <div class="row g-2 align-items-center mb-3">
              <div class="col-5 col-sm-4">
                <div class="wa-badge">
                  <span>🇬🇭 +233</span>
                </div>
              </div>
              <div class="col-7 col-sm-8">
                <input
                  type="text"
                  name="phone"
                  class="form-control form-control-lg"
                  placeholder="024 822 9540"
                  inputmode="numeric"
                  pattern="0[0-9]{9}"
                  maxlength="10"
                  required
                >
              </div>
            </div>

            <div class="helper mb-3">
              We’ll send a 6-digit code. Standard SMS rates may apply.
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
    const form = document.getElementById('phoneLoginForm');
    const btn  = document.getElementById('sendBtn');

    form?.addEventListener('submit', function () {
      btn.disabled = true;
      const original = btn.innerHTML;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Sending...';
      setTimeout(() => { btn.innerHTML = original; btn.disabled = false; }, 6000);
    });

    // Restrict to digits and enforce leading 0
    const input = form?.querySelector('input[name="phone"]');
    input?.addEventListener('input', (e) => {
      let v = e.target.value.replace(/\D/g,'');
      if (v && v[0] !== '0') v = '0' + v.slice(0,9);
      e.target.value = v.slice(0,10);
    });
  })();
</script>
@endsection
