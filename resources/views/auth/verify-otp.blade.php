@extends('layouts.app')

@section('content')
<style>
  .auth-wrap {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }

  .wa-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: var(--wa-shadow);
    width: 100%;
    max-width: 450px;
  }

  .wa-head {
    background: linear-gradient(135deg, var(--wa-deep), var(--wa-green));
    color: #fff;
    padding: 26px 24px;
  }

  .wa-body {
    padding: 26px 24px;
  }

  .otp-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 8px;
    max-width: 100%;
    width: 100%;
  }

  .otp-input {
    text-align: center;
    font-size: 1.3rem;
    font-weight: 700;
    height: 56px;
    width: 100%;
    max-width: 100%;
    border-radius: 12px;
    background: var(--input-bg);
    color: var(--text);
    border: 1px solid var(--input-border);
    outline: none;
    box-sizing: border-box;
    padding: 0;
    transition: all 0.2s ease;
  }

  /* Responsive adjustments */
  @media (max-width: 576px) {
    .otp-grid {
      gap: 6px;
    }
    
    .otp-input {
      height: 48px;
      font-size: 1.1rem;
    }
    
    .wa-head,
    .wa-body {
      padding: 20px 16px;
    }
  }

  .otp-input::placeholder {
    color: var(--wa-muted);
  }

  .otp-input:focus {
    border-color: var(--wa-green);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--wa-green) 30%, transparent);
  }

  .btn-wa {
    background: var(--wa-green);
    border: none;
    color: #062a1f;
    font-weight: 700;
    border-radius: 14px;
    padding: 12px 16px;
    transition: filter 0.2s ease;
  }

  .btn-wa:hover {
    filter: brightness(1.05);
  }

  .helper {
    color: var(--wa-muted);
    font-size: 0.9rem;
  }

  .link {
    color: var(--wa-green);
    text-decoration: none;
    font-weight: 600;
  }

  .link:hover {
    color: #1fc25a;
  }

  .auto-submit-indicator {
    text-align: center;
    color: var(--wa-green);
    font-size: 0.9rem;
    margin-top: 10px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
  }

  .alert {
    border-radius: 12px;
    border: none;
  }

  .alert-success {
    background: color-mix(in srgb, var(--wa-green) 15%, transparent);
    color: var(--text);
    border-left: 4px solid var(--wa-green);
  }

  .alert-danger {
    background: color-mix(in srgb, #dc3545 15%, transparent);
    color: var(--text);
    border-left: 4px solid #dc3545;
  }
</style>

@php
  $phone = session('phone');
  $resendTs = session('resend_time');
@endphp

<div class="auth-wrap">
  <div class="wa-card">
    <div class="wa-head">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <div class="fw-bold">Enter 6-digit code</div>
          <div class="helper">Sent to <span class="text-white">{{ $phone ?? '+233 XXXXXXXXX' }}</span></div>
        </div>
        <a href="{{ route('login') }}" class="link">Change</a>
      </div>
    </div>

    <div class="wa-body">
      @if (session('status'))
        <div class="alert alert-success mb-3">
          {{ session('status') }}
        </div>
      @endif

      @if ($errors->any())
        <div class="alert alert-danger mb-3">
          <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <div class="auto-submit-indicator">
        <i class="bi bi-check-circle-fill"></i>
        <span>Will auto-verify when complete</span>
      </div>

      {{-- Verify form --}}
      <form method="POST" action="{{ route('verify.otp') }}" id="otpForm">
        @csrf
        <input type="hidden" name="otp_code" id="otpHidden">

        <div class="otp-grid mb-4">
          @for ($i = 0; $i < 6; $i++)
            <input
              class="otp-input"
              type="text"
              inputmode="numeric"
              maxlength="1"
              @if($i === 0) autocomplete="one-time-code" @endif
              aria-label="OTP digit {{ $i + 1 }}"
            />
          @endfor
        </div>

        <button type="submit" class="btn btn-wa w-100 mb-3" id="verifyBtn" style="display: none;">
          ✅ Verify
        </button>
      </form>

      {{-- Resend section --}}
      <div class="d-flex justify-content-between align-items-center">
        <div class="helper">Didn't get it?</div>
        <div>
          <button type="button" class="btn btn-link link p-0" id="resendBtn">Resend code</button>
          <span class="helper" id="resendTimer" style="display:none;"></span>
        </div>
      </div>
    </div>
  </div>

  {{-- Hidden resend form --}}
  <form method="POST" action="{{ route('resend.otp') }}" id="resendForm" class="d-none">
    @csrf
    <input type="hidden" name="phone" value="{{ $phone }}"/>
  </form>
</div>

<script>
(function(){
  const boxes = Array.from(document.querySelectorAll('.otp-input'));
  const hidden = document.getElementById('otpHidden');
  const form = document.getElementById('otpForm');
  const resendBtn = document.getElementById('resendBtn');
  const resendTimer = document.getElementById('resendTimer');
  const resendForm = document.getElementById('resendForm');
  const resendTs = {{ $resendTs ?? 'null' }};
  const nowTs = Math.floor(Date.now() / 1000);

  // Focus first input on load
  boxes[0]?.focus();

  // Handle input for each OTP box
  boxes.forEach((box, idx) => {
    box.addEventListener('input', (e) => {
      // Allow only digits
      let val = e.target.value.replace(/\D/g, '');
      // If the user pasted or autofilled multiple digits into a single box (mobile paste),
      // distribute them across the inputs
      if (val.length > 1) {
        // Limit to maximum of 6 digits across the whole form
        val = val.slice(0, 6);
        boxes.forEach((b, i) => {
          b.value = val[i] ?? '';
        });
        // If all digits are filled, auto-submit
        if (val.length === 6) {
          setTimeout(() => form.submit(), 100);
        } else {
          boxes[Math.min(val.length, 5)].focus();
        }
        syncHidden();
        return;
      }
      // Otherwise just set the single character
      e.target.value = val.slice(0, 1);
      // Auto-submit when last digit is entered
      if (idx === 5 && e.target.value) {
        setTimeout(() => {
          form.submit();
        }, 100);
      } else if (e.target.value && boxes[idx + 1]) {
        boxes[idx + 1].focus();
      }
      syncHidden();
    });
    
    // Handle backspace navigation
    box.addEventListener('keydown', (e) => {
      if (e.key === 'Backspace' && !box.value && boxes[idx - 1]) {
        boxes[idx - 1].focus();
      }
    });
  });

  // Handle paste event on each input. When a user pastes a 6‑digit code, split
  // it across the inputs so they don't have to type manually. Attach to both
  // the document and each box for robustness.
  function handlePaste(e) {
    const clipboard = e.clipboardData || window.clipboardData;
    const text = (clipboard ? clipboard.getData('text') : '').replace(/\D/g,'').slice(0,6);
    if (text.length) {
      e.preventDefault();
      boxes.forEach((b, i) => b.value = text[i] ?? '');
      // Auto-submit if all 6 digits are pasted
      if (text.length === 6) {
        setTimeout(() => { form.submit(); }, 100);
      } else {
        boxes[Math.min(text.length, 5)].focus();
      }
      syncHidden();
    }
  }
  document.addEventListener('paste', handlePaste);
  boxes.forEach(b => b.addEventListener('paste', handlePaste));

  // Sync hidden input with OTP values
  function syncHidden() {
    hidden.value = boxes.map(b => b.value || '').join('');
  }

  // Resend countdown timer
  if (resendTs && resendTs > nowTs) {
    let remain = resendTs - nowTs;
    resendBtn.style.display = 'none';
    resendTimer.style.display = 'inline';
    resendTimer.textContent = `Resend in ${remain}s`;

    const timer = setInterval(() => {
      remain--;
      if (remain <= 0) {
        clearInterval(timer);
        resendTimer.style.display = 'none';
        resendBtn.style.display = 'inline';
      } else {
        resendTimer.textContent = `Resend in ${remain}s`;
      }
    }, 1000);
  }

  // Handle resend button click
  resendBtn.addEventListener('click', () => {
    resendBtn.disabled = true;
    resendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';
    resendForm.submit();
  });
})();
</script>
@endsection