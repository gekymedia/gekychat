@extends('layouts.app')

@section('content')
<style>
  /* --- Alias OTP page vars to layout tokens so styles always resolve --- */
  :root{
    --wa-card: var(--card);
    --wa-text: var(--text);
    --wa-border: var(--border);
    --wa-muted: var(--wa-muted, #9BB0BD);
    --wa-green: var(--wa-green, #25D366);
    --wa-deep: var(--wa-deep, #128C7E);

    /* use the layout’s input tokens for perfect light/dark contrast */
    --otp-input-bg: var(--input-bg);
    --otp-input-border: var(--input-border);
    --otp-input-text: var(--text);
  }

  .auth-wrap {
    min-height: calc(100vh - var(--nav-h));
    display:flex; align-items:center;
  }
  .wa-card {
    background: var(--wa-card); color: var(--wa-text);
    border:1px solid var(--wa-border);
    border-radius:20px; overflow:hidden; box-shadow: var(--wa-shadow);
  }
  .wa-head {
    background: linear-gradient(135deg, var(--wa-deep), var(--wa-green));
    color:#fff; padding:26px 24px;
  }
  .wa-body { padding: 26px 24px; }

  .otp-grid {
    display:grid; grid-template-columns: repeat(6, 1fr); gap:10px;
  }
 .otp-grid {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 8px; /* Reduced gap */
  max-width: 100%; /* Ensure it doesn't overflow */
  width: 100%; /* Take full width of parent */
}

.otp-input {
  text-align: center;
  font-size: 1.3rem;
  font-weight: 700;
  height: 56px;
  width: 100%; /* Full width of grid cell */
  max-width: 100%; /* Prevent overflow */
  border-radius: 12px;
  background: var(--otp-input-bg);
  color: var(--otp-input-text);
  border: 1px solid var(--otp-input-border);
  outline: none;
  box-sizing: border-box; /* Include padding/border in width */
  padding: 0; /* Remove default padding */
}

/* Responsive adjustments */
@media (max-width: 576px) {
  .otp-grid {
    gap: 6px; /* Smaller gap on mobile */
  }
  
  .otp-input {
    height: 48px; /* Slightly smaller on mobile */
    font-size: 1.1rem;
  }
}
  .otp-input::placeholder { color: var(--wa-muted); }
  .otp-input:focus {
    border-color: var(--wa-green);
    box-shadow: 0 0 0 3px color-mix(in oklab, var(--wa-green) 20%, transparent);
  }

  .btn-wa {
    background: var(--wa-green); border: none; color:#062a1f;
    font-weight:700; border-radius:14px; padding:12px 16px;
  }
  .helper { color: var(--wa-muted); }
  .link { color: var(--wa-green); text-decoration: none; }
  .link:hover { color: #1fc25a; }
</style>

@php
  $phone = session('phone');
  $resendTs = session('resend_time'); // unix timestamp
@endphp

<div class="container auth-wrap">
  <div class="row justify-content-center w-100">
    <div class="col-12 col-md-8 col-lg-6 col-xxl-5">
      <div class="wa-card">
        <div class="wa-head">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="fw-bold">Enter 6-digit code</div>
              <div class="helper">Sent to <span class="text-white">{{ $phone }}</span></div>
            </div>
            <a href="{{ route('login') }}" class="link">Change</a>
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

          {{-- Verify form --}}
          <form method="POST" action="{{ url('/verify-otp') }}" id="otpForm">
            @csrf
            <input type="hidden" name="phone" value="{{ $phone }}"/>
            <input type="hidden" name="otp" id="otpHidden">

            <div class="otp-grid mb-3">
              @for ($i=0; $i<6; $i++)
                <input
                  class="otp-input"
                  type="text"
                  inputmode="numeric"
                  maxlength="1"
                  @if($i===0) autocomplete="one-time-code" @endif
                  aria-label="OTP digit {{ $i+1 }}"
                />
              @endfor
            </div>

            <button type="submit" class="btn btn-wa w-100 mb-2" id="verifyBtn">✅ Verify</button>
          </form>

          {{-- Resend section (separate form, no nesting) --}}
          <div class="d-flex justify-content-between align-items-center">
            <div class="helper">Didn’t get it?</div>
            <div>
              <button type="button" class="btn btn-link link p-0" id="resendBtn">Resend code</button>
              <span class="helper" id="resendTimer" style="display:none;"></span>
            </div>
          </div>
        </div>
      </div>

      {{-- Hidden resend form submitted by JS --}}
      <form method="POST" action="{{ route('resend.otp') }}" id="resendForm" class="d-none">
        @csrf
        <input type="hidden" name="phone" value="{{ $phone }}"/>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  const boxes = Array.from(document.querySelectorAll('.otp-input'));
  const hidden = document.getElementById('otpHidden');
  const form   = document.getElementById('otpForm');
  const resendBtn = document.getElementById('resendBtn');
  const resendTimer = document.getElementById('resendTimer');
  const resendForm = document.getElementById('resendForm');
  const resendTs = {{ $resendTs ?? 'null' }};
  const nowTs = Math.floor(Date.now() / 1000);

  boxes[0]?.focus();

  boxes.forEach((box, idx) => {
    box.addEventListener('input', (e) => {
      e.target.value = e.target.value.replace(/\D/g,'').slice(0,1);
      if (e.target.value && boxes[idx+1]) boxes[idx+1].focus();
      syncHidden();
    });
    box.addEventListener('keydown', (e) => {
      if (e.key === 'Backspace' && !box.value && boxes[idx-1]) boxes[idx-1].focus();
    });
  });

  document.addEventListener('paste', (e) => {
    const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6);
    if (text.length) {
      e.preventDefault();
      boxes.forEach((b,i) => b.value = text[i] ?? '');
      boxes[Math.min(text.length,5)].focus();
      syncHidden();
    }
  });

  function syncHidden(){ hidden.value = boxes.map(b => b.value || '').join(''); }

  // Resend countdown
  if (resendTs && resendTs > nowTs) {
    let remain = resendTs - nowTs;
    resendBtn.style.display = 'none';
    resendTimer.style.display = 'inline';
    resendTimer.textContent = `Resend in ${remain}s`;

    const t = setInterval(() => {
      remain--;
      if (remain <= 0) {
        clearInterval(t);
        resendTimer.style.display = 'none';
        resendBtn.style.display = 'inline';
      } else {
        resendTimer.textContent = `Resend in ${remain}s`;
      }
    }, 1000);
  }

  // Prevent submit with incomplete code
  form.addEventListener('submit', (e) => {
    if (hidden.value.length !== 6) {
      e.preventDefault();
      boxes[0].focus();
    }
  });

  // Resend click → submit hidden form
  resendBtn.addEventListener('click', () => {
    resendBtn.disabled = true;
    resendForm.submit();
  });
})();
</script>
@endsection
