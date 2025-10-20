@extends('layouts.app')

@section('content')
<style>
  :root {
    --bubble-sent-bg: #005c4b;
    --bubble-sent-text: #e6fffa;
    --bubble-recv-bg: #202c33;
    --bubble-recv-text: var(--text);
  }
  .wa-card { background: var(--card); border: 1px solid var(--border); border-radius: 18px; box-shadow: var(--wa-shadow); overflow: hidden; }
  .btn-wa { background: var(--wa-green); border: none; color: #062a1f; font-weight:700; border-radius:14px; transition: .2s; }
  .btn-outline-wa { border-color: var(--wa-green); color: var(--wa-green); border-radius:14px; transition: .2s; }
  .btn-outline-wa:hover { background: var(--wa-green); color: #062a1f; }
  .form-control,.form-select{ background:var(--input-bg); color:var(--text); border:1px solid var(--input-border); border-radius:14px; }
  .form-control:focus,.form-select:focus{ border-color:var(--wa-green); box-shadow:none; }
  .avatar-preview{ width:80px; height:80px; border-radius:50%; object-fit:cover; border:2px solid var(--border); transition:.2s; }
  .avatar-uploaded{ border-color: var(--wa-green); }
  .helper{ color: var(--wa-muted); font-size:.9rem; }

  .picker-wrap{ display:grid; gap:12px; }
  .picker-tools{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
  .search-input{ flex:1; min-width:250px; }
  .list-wrap{ border:1px solid var(--border); border-radius:14px; max-height:320px; overflow:auto; background:var(--card); }
  .list-item{ display:flex; align-items:center; gap:12px; padding:12px 15px; border-bottom:1px solid var(--border); transition:.2s; cursor:pointer; }
  .list-item:last-child{ border-bottom:none; }
  .list-item:hover{ background:rgba(255,255,255,.04); }
  [data-theme="light"] .list-item:hover{ background:rgba(0,0,0,.04); }
  .chip-wrap{ display:flex; gap:8px; flex-wrap:wrap; min-height:42px; align-items:center; }
  .chip{ display:inline-flex; align-items:center; gap:6px; background:var(--card); border:1px solid var(--border); border-radius:999px; padding:6px 12px; transition:.2s; }
  .chip button{ border:none; background:transparent; color:var(--wa-muted); line-height:0; cursor:pointer; }
  .count-badge{ background:var(--wa-green); color:#062a1f; border-radius:999px; padding:2px 10px; font-weight:700; }
  @media (max-width:768px){ .avatar-preview{ width:64px; height:64px; } .picker-tools{ flex-direction:column; align-items:stretch; } .search-input{ min-width:100%; } }
</style>

<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-9 col-xl-8">
      <div class="wa-card p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h1 class="h4 mb-1">Create New Group</h1>
            <p class="helper mb-0">Name your group, add a photo, and select participants</p>
          </div>
          <a class="btn btn-sm btn-outline-secondary" href="{{ route('chat.index') }}">
            <i class="bi bi-arrow-left me-1"></i> Back
          </a>
        </div>

        @if (session('status'))
          <div class="alert alert-success alert-dismissible fade show">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        @endif

        @if ($errors->any())
          <div class="alert alert-danger alert-dismissible fade show">
            <ul class="mb-0">
              @foreach ($errors->all() as $e)
                <li>{{ $e }}</li>
              @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        @endif

        <form action="{{ route('groups.store') }}" method="POST" enctype="multipart/form-data" id="groupForm">
          @csrf

          <div class="row g-3 mb-4">
            <div class="col-12 col-md-4 d-flex gap-3 align-items-start">
              <div class="position-relative">
                <img id="avatarPreview" class="avatar-preview" src="{{ asset('icons/icon-192x192.png') }}" alt="Group Avatar Preview">
                <div class="position-absolute bottom-0 end-0 bg-primary rounded-circle p-1">
                  <i class="bi bi-camera-fill text-white"></i>
                </div>
              </div>
              <div class="flex-grow-1">
                <label class="form-label mb-1">Group Photo</label>
                <input type="file" class="form-control" name="avatar" accept="image/*" onchange="previewAvatar(event)">
                <small class="helper d-block">Optional • JPG/PNG/WebP, up to 2MB</small>
              </div>
            </div>

            <div class="col-12 col-md-8">
              <div class="mb-3">
                <label class="form-label">Group Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name" maxlength="64" required
                       value="{{ old('name') }}"
                       placeholder="e.g. Family Chat, Project Team, Study Group">
                <small class="helper counter d-block"></small>
              </div>
              <div class="mb-3">
                <label class="form-label">Description <span class="helper">(optional)</span></label>
                <textarea class="form-control" name="description" maxlength="200" rows="2"
                          placeholder="What's this group about?">{{ old('description') }}</textarea>
                <small class="helper counter d-block"></small>
              </div>
              <div class="mb-0">
                <label class="form-label">Group Type</label>
                <div class="d-flex gap-3">
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="is_private" id="publicGroup" value="0" {{ old('is_private','0')=='0'?'checked':'' }}>
                    <label class="form-check-label" for="publicGroup">
                      <i class="bi bi-globe me-1"></i> Public
                    </label>
                    <small class="helper d-block">Anyone can join with invite link</small>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="is_private" id="privateGroup" value="1" {{ old('is_private')=='1'?'checked':'' }}>
                    <label class="form-check-label" for="privateGroup">
                      <i class="bi bi-lock me-1"></i> Private
                    </label>
                    <small class="helper d-block">Only admins can add members</small>
                  </div>
                </div>
              </div>

               {{--  Type --}}
          <div class="mb-3">
            <label class="form-label fw-semibold">Type</label>
            <div class="d-flex gap-4">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="type" 
                       id="sb-gp-public" value="channel" checked>
                <label class="form-check-label" for="sb-gp-public">
                  <i class="bi bi-globe me-1" aria-hidden="true"></i> channel
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="type" 
                       id="sb-gp-private" value="group">
                <label class="form-check-label" for="sb-gp-private">
                  <i class="bi bi-lock me-1" aria-hidden="true"></i> Group
                </label>
              </div>
            </div>
          </div>
            </div>
          </div>

          <div class="picker-wrap">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <label class="form-label mb-0">Add Participants <span class="text-danger">*</span></label>
              <div class="helper">
                Selected: <span id="selectedCount" class="count-badge">0</span>
              </div>
            </div>

            <div class="picker-tools mb-2">
              <div class="input-group search-input">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" id="userFilter" placeholder="Search contacts by name or phone..." autocomplete="off">
              </div>
              <button type="button" class="btn btn-outline-wa btn-sm" id="selectAll">
                <i class="bi bi-check-all me-1"></i> Select all (filtered)
              </button>
              <button type="button" class="btn btn-outline-secondary btn-sm" id="clearAll">
                <i class="bi bi-x-lg me-1"></i> Clear
              </button>
            </div>

            <div class="chip-wrap mb-2" id="selectedChips" aria-live="polite"></div>

            <div class="list-wrap mb-2" id="userList" role="listbox" aria-label="Contacts list">
              @foreach($users as $u)
                <label class="list-item" data-name="{{ strtolower($u->name ?? '') }}" data-phone="{{ strtolower($u->phone ?? '') }}">
                  <input class="form-check-input me-2 member-check" type="checkbox" name="members[]" value="{{ $u->id }}"
                         {{ collect(old('members',[]))->contains($u->id) ? 'checked' : '' }}>
                  @if($u->avatar_path)
                    <img src="{{ Storage::url($u->avatar_path) }}"
                         alt="{{ $u->name ?? $u->phone }} avatar"
                         class="rounded-circle" width="36" height="36"
                         onerror="this.src='{{ asset('icons/icon-192x192.png') }}'">
                  @else
                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                      <span class="text-white">{{ strtoupper(substr($u->name ?? $u->phone, 0, 1)) }}</span>
                    </div>
                  @endif
                  <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-center">
                      <strong>{{ $u->name ?? $u->phone }}</strong>
                      <small class="helper">{{ $u->phone }}</small>
                    </div>
                  </div>
                </label>
              @endforeach
            </div>
            <small class="helper"><i class="bi bi-lightbulb me-1"></i> Tip: Use search to filter, then “Select all (filtered)”.</small>
          </div>

          <div class="mt-4 d-flex align-items-center gap-2 flex-wrap">
            <button class="btn btn-wa px-4" type="submit" id="createBtn">
              <i class="bi bi-people-fill me-1"></i> Create Group
            </button>
            <a class="btn btn-outline-secondary" href="{{ route('chat.index') }}">
              <i class="bi bi-x-lg me-1"></i> Cancel
            </a>
            <div class="form-check ms-md-auto">
              <input class="form-check-input" type="checkbox" id="privacyCheck" required>
              <label class="form-check-label helper" for="privacyCheck">
                I understand this group will be visible to all members
              </label>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
  function previewAvatar(e) {
    const [file] = e.target.files || [];
    if (!file) return;
    const valid = ['image/jpeg','image/png','image/webp'];
    if (!valid.includes(file.type)) { alert('Please upload a JPG, PNG, or WebP image.'); e.target.value=''; return; }
    if (file.size > 2 * 1024 * 1024) { alert('Image size exceeds 2MB.'); e.target.value=''; return; }
    const reader = new FileReader();
    reader.onload = ev => {
      const img = document.getElementById('avatarPreview');
      img.src = ev.target.result; img.classList.add('avatar-uploaded');
    };
    reader.readAsDataURL(file);
  }

  (function(){
    const form = document.getElementById('groupForm');
    const list = document.getElementById('userList');
    const filter = document.getElementById('userFilter');
    const chips = document.getElementById('selectedChips');
    const countEl = document.getElementById('selectedCount');
    const selectAll = document.getElementById('selectAll');
    const clearAll = document.getElementById('clearAll');

    // Counters for name/description
    const nameInput = form.querySelector('input[name="name"]');
    const descInput = form.querySelector('textarea[name="description"]');
    const updateCounter = (el, max) => {
      const counter = el.parentElement.querySelector('.counter');
      if (!counter) return;
      const left = max - (el.value || '').length;
      counter.textContent = `${left} chars left`;
      counter.style.color = left < 10 ? 'var(--bs-danger)' : 'var(--wa-muted)';
    };
    nameInput?.addEventListener('input', () => updateCounter(nameInput, 64));
    descInput?.addEventListener('input', () => updateCounter(descInput, 200));
    updateCounter(nameInput, 64); updateCounter(descInput, 200);

    // Only listen to change events (avoid double toggles from <label>)
    list.addEventListener('change', e => {
      if (e.target.classList.contains('member-check')) {
        refreshChips(); updateCount();
      }
    });

    function updateCount() {
      const selected = list.querySelectorAll('.member-check:checked').length;
      countEl.textContent = selected;
    }

    function refreshChips() {
      chips.innerHTML = '';
      list.querySelectorAll('.member-check:checked').forEach(cb => {
        const row = cb.closest('.list-item');
        const name = row.querySelector('strong')?.textContent?.trim() || 'User';
        const phone = row.querySelector('.helper')?.textContent?.trim() || '';
        const chip = document.createElement('div');
        chip.className = 'chip';
        chip.innerHTML = `
          <span>${escapeHtml(name)} <small class="helper">${escapeHtml(phone)}</small></span>
          <button type="button" aria-label="Remove ${escapeHtml(name)}"><i class="bi bi-x"></i></button>
        `;
        chip.querySelector('button').addEventListener('click', (ev) => {
          ev.stopPropagation(); cb.checked = false; refreshChips(); updateCount();
        });
        chips.appendChild(chip);
      });
    }

    function filterList() {
      const q = (filter.value || '').toLowerCase().trim();
      let visible = 0;
      list.querySelectorAll('.list-item').forEach(row => {
        const name = row.dataset.name || '';
        const phone = row.dataset.phone || '';
        const show = !q || name.includes(q) || phone.includes(q);
        row.style.display = show ? '' : 'none';
        if (show) visible++;
      });
      selectAll.disabled = visible === 0;
    }

    selectAll.addEventListener('click', () => {
      list.querySelectorAll('.list-item').forEach(row => {
        if (row.style.display === 'none') return;
        const cb = row.querySelector('.member-check'); if (cb) cb.checked = true;
      });
      refreshChips(); updateCount();
    });
    clearAll.addEventListener('click', () => {
      list.querySelectorAll('.member-check:checked').forEach(cb => cb.checked = false);
      refreshChips(); updateCount();
    });

    let t; filter.addEventListener('input', () => { clearTimeout(t); t = setTimeout(filterList, 250); });
    filter.addEventListener('keydown', e => { if (e.key === 'Enter') e.preventDefault(); });

    form.addEventListener('submit', (e) => {
      const selected = list.querySelectorAll('.member-check:checked').length;
      if (selected === 0) { e.preventDefault(); alert('Please select at least one participant.'); return; }
      const privacy = document.getElementById('privacyCheck');
      if (!privacy.checked) { e.preventDefault(); alert('Please confirm visibility settings.'); return; }
    });

    function escapeHtml(s){ return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

    // init
    refreshChips(); updateCount(); filterList();
  })();
</script>
@endsection
