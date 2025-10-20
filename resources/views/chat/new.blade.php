@extends('layouts.app')

@section('content')
<style>
  /* Using variables from app.blade.php */
  :root {
    --bubble-sent-bg: #005c4b;
    --bubble-sent-text: #e6fffa;
    --bubble-recv-bg: #202c33;
    --bubble-recv-text: var(--text);
  }

  .new-chat-container {
    min-height: calc(100dvh - var(--nav-h));
    display: flex;
    align-items: center;
    background: radial-gradient(1000px 600px at 10% -10%, var(--bg-accent, #0f1a20) 0, var(--bg) 60%), var(--bg);
  }

  .wa-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 18px;
    box-shadow: var(--wa-shadow);
    overflow: hidden;
  }

  .wa-head {
    background: linear-gradient(135deg, var(--wa-deep), var(--wa-green));
    color: #fff;
    padding: 22px 20px;
    position: relative;
  }

  .wa-head::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 0;
    right: 0;
    height: 10px;
    background: linear-gradient(to bottom, rgba(0,0,0,0.1), transparent);
  }

  .wa-body {
    padding: 20px;
  }

  /* Buttons matching app.blade.php */
  .btn-wa {
    background: var(--wa-green);
    border: none;
    color: #062a1f;
    font-weight: 700;
    border-radius: 14px;
  }

  .btn-outline-wa {
    border-color: var(--wa-green);
    color: var(--wa-green);
    border-radius: 14px;
  }

  .btn-outline-wa:hover {
    background: var(--wa-green);
    color: #062a1f;
  }

  .btn-ghost {
    background: var(--card);
    border: 1px solid var(--border);
    color: var(--text);
    border-radius: 14px;
  }

  .btn-ghost:hover {
    background: rgba(255,255,255,.06);
  }

  [data-theme="light"] .btn-ghost:hover {
    background: rgba(0,0,0,.04);
  }

  /* Form elements matching app.blade.php */
  .form-control, .form-select {
    background: var(--input-bg);
    color: var(--text);
    border: 1px solid var(--input-border);
    border-radius: 14px;
  }

  .form-control:focus, .form-select:focus {
    border-color: var(--wa-green);
    box-shadow: none;
  }

  .helper {
    color: var(--wa-muted);
    font-size: var(--fs-sm);
  }

  /* Avatar */
  .avatar-img {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid var(--border);
    display: block;
  }

  .avatar {
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    font-weight: 700;
    flex-shrink: 0;
  }

  .avatar.fallback {
    background: #667085;
    color: #fff;
  }

  /* User list */
  .user-list {
    border: 1px solid var(--border);
    border-radius: 14px;
    max-height: 420px;
    overflow: auto;
    background: var(--card);
  }

  .user-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-bottom: 1px solid var(--border);
    cursor: pointer;
    transition: all 0.2s ease;
  }

  .user-row:last-child {
    border-bottom: none;
  }

  .user-row:hover {
    background: rgba(255,255,255,.04);
  }

  [data-theme="light"] .user-row:hover {
    background: rgba(0,0,0,.04);
  }

  .user-row.active {
    outline: 2px solid var(--wa-green);
    background: rgba(37, 211, 102, 0.08);
  }

  .user-main {
    flex: 1;
    min-width: 0;
  }

  .user-name {
    font-weight: 700;
    line-height: 1.1;
  }

  .user-meta {
    font-size: var(--fs-sm);
    color: var(--wa-muted);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
  }

  .no-users {
    padding: 30px;
    text-align: center;
    color: var(--wa-muted);
  }

  /* Responsive adjustments */
  @media (max-width: 768px) {
    .wa-head {
      padding: 18px 15px;
    }
    
    .wa-body {
      padding: 15px;
    }
    
    .user-row {
      padding: 10px 12px;
    }
  }
</style>

<div class="new-chat-container">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-lg-8 col-xxl-6">
        <div class="wa-card" role="region" aria-label="Start a new chat">
          <!-- Header -->
          <div class="wa-head d-flex justify-content-between align-items-center">
            <div>
              <h1 class="h5 mb-1">Start a New Chat</h1>
              <div class="helper">Select a contact to begin messaging</div>
            </div>
            <a href="{{ route('chat.index') }}" class="btn btn-ghost btn-sm">
              <i class="bi bi-arrow-left me-1"></i> Back
            </a>
          </div>

          <!-- Body -->
          <div class="wa-body">
            @if($errors->any())
              <div class="alert alert-danger mb-3">
                <ul class="mb-0">
                  @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                  @endforeach
                </ul>
              </div>
            @endif

            <form action="{{ route('chat.start') }}" method="POST" id="newChatForm">
              @csrf
              <input type="hidden" name="user_id" id="user_id" required>

              <!-- Search -->
              <div class="mb-3">
                <label for="userSearch" class="form-label">Search contacts</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-search"></i></span>
                  <input type="text" class="form-control" id="userSearch" placeholder="Type a name or phone..." autocomplete="off">
                </div>
                <div class="helper mt-2" id="resultCount">
                  {{ count($users) }} contacts available
                </div>
              </div>

              <!-- List -->
              <div class="user-list" id="userList" role="listbox" aria-label="Users">
                @forelse($users as $u)
                  @php
                    $displayName = $u->name ?: $u->phone;
                    $initial = strtoupper(mb_substr($displayName, 0, 1));
                  @endphp
                  <div class="user-row" role="option"
                       tabindex="0"
                       data-id="{{ $u->id }}"
                       data-name="{{ strtolower($u->name ?? '') }}"
                       data-phone="{{ strtolower($u->phone ?? '') }}">
                    @if($u->avatar_path)
                      <img
                        src="{{ Storage::url($u->avatar_path) }}"
                        alt="{{ $displayName }} avatar"
                        class="avatar-img"
                        loading="lazy"
                        onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'avatar fallback',textContent:'{{ e($initial) }}'}))"
                      >
                    @else
                      <div class="avatar fallback">{{ $initial }}</div>
                    @endif

                    <div class="user-main">
                      <div class="user-name">{{ $displayName }}</div>
                      @if($u->name && $u->phone)
                        <div class="user-meta">{{ $u->phone }}</div>
                      @else
                        <div class="user-meta">ID #{{ $u->id }}</div>
                      @endif
                    </div>
                  </div>
                @empty
                  <div class="no-users">
                    <i class="bi bi-people"></i>
                    <p>No contacts available</p>
                    <a href="{{ route('profile.edit') }}" class="btn btn-ghost btn-sm mt-2">
                      <i class="bi bi-person-plus me-1"></i> Add Contacts
                    </a>
                  </div>
                @endforelse
              </div>

              <!-- Actions -->
              <div class="actions">
                <a href="{{ route('chat.index') }}" class="btn btn-outline-secondary flex-grow-1">
                  <i class="bi bi-x-lg me-1"></i> Cancel
                </a>
                <button type="submit" class="btn btn-wa flex-grow-1" id="startBtn" disabled>
                  <i class="bi bi-chat-dots me-1"></i> Start Chat
                </button>
              </div>

              <div class="helper mt-3 text-center">
                <i class="bi bi-shield-lock me-1"></i> Your messages are end-to-end encrypted
              </div>

              <!-- No-JS fallback -->
              <noscript>
                <div class="mt-4">
                  <label class="form-label">Choose a contact (fallback)</label>
                  <select name="user_id" class="form-select" required>
                    <option value="">-- Select Contact --</option>
                    @foreach($users as $u)
                      <option value="{{ $u->id }}">{{ $u->name ? ($u->name.' — '.$u->phone) : $u->phone }}</option>
                    @endforeach
                  </select>
                  <button type="submit" class="btn btn-wa mt-3 w-100">
                    <i class="bi bi-chat-dots me-1"></i> Start Chat
                  </button>
                </div>
              </noscript>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    const list   = document.getElementById('userList');
    const search = document.getElementById('userSearch');
    const hidden = document.getElementById('user_id');
    const start  = document.getElementById('startBtn');
    const count  = document.getElementById('resultCount');
    const form   = document.getElementById('newChatForm');

    function clearActive() {
      list.querySelectorAll('.user-row.active').forEach(r => r.classList.remove('active'));
    }

    function selectRow(row) {
      if (!row) return;
      clearActive();
      row.classList.add('active');
      hidden.value = row.dataset.id || '';
      start.disabled = !hidden.value;
    }

    // Click select
    list.addEventListener('click', (e) => {
      const row = e.target.closest('.user-row');
      if (!row) return;
      selectRow(row);
    });

    // Keyboard navigation
    list.addEventListener('keydown', (e) => {
      const rows = Array.from(list.querySelectorAll('.user-row:not([style*="display: none"])'));
      if (!rows.length) return;

      const activeIdx = rows.findIndex(r => r.classList.contains('active') || r === document.activeElement);

      if (e.key === 'ArrowDown') {
        e.preventDefault();
        const next = rows[Math.min((activeIdx + 1), rows.length - 1)] || rows[0];
        next.focus();
        selectRow(next);
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        const prev = rows[Math.max((activeIdx - 1), 0)] || rows[0];
        prev.focus();
        selectRow(prev);
      } else if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        const row = document.activeElement?.classList?.contains('user-row') ? document.activeElement : rows[0];
        if (row) selectRow(row);
      }
    });

    // Filter function
    function normalize(s) { return (s || '').toString().toLowerCase().trim(); }
    
    function filterUsers() {
      const q = normalize(search.value);
      let visible = 0;
      
      list.querySelectorAll('.user-row').forEach(row => {
        const name  = row.dataset.name || '';
        const phone = row.dataset.phone || '';
        const show = (!q) || name.includes(q) || phone.includes(q);
        row.style.display = show ? 'flex' : 'none';
        if (show) visible++;
      });
      
      if (count) {
        count.textContent = `${visible} ${visible === 1 ? 'contact' : 'contacts'} found`;
      }
      
      // Clear selection if filtered out
      if (hidden.value) {
        const selectedVisible = list.querySelector('.user-row.active[style*="display: flex"], .user-row.active:not([style])');
        if (!selectedVisible) {
          clearActive();
          hidden.value = '';
          start.disabled = true;
        }
      }
    }

    // Debounce search input
    let searchTimeout;
    search.addEventListener('input', () => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(filterUsers, 300);
    });

    // Form submission feedback
    form.addEventListener('submit', (e) => {
      if (!hidden.value) {
        e.preventDefault();
        return;
      }
      
      start.disabled = true;
      start.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Starting...';
    });

    // Focus search on page load
    search.focus();
  })();
</script>

@endsection