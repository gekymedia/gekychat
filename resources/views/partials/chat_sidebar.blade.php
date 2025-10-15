{{-- resources/views/partials/chat_sidebar.blade.php --}}
@php
  use Illuminate\Support\Facades\Storage;

  // Prefer Contacts → registered users; else fall back to all other users
  $people = collect();
  try {
      if (\Schema::hasTable('contacts')) {
          $contacts = \App\Models\Contact::query()
              ->with('contactUser:id,name,phone,avatar_path')
              ->where('user_id', auth()->id())
              ->whereNotNull('contact_user_id')
              ->orderByRaw('COALESCE(NULLIF(display_name, ""), normalized_phone)')
              ->get();

          $people = $contacts->map(function($c){
              $u = $c->contactUser;
              return (object)[
                  'id'         => optional($u)->id,
                  'name'       => $c->display_name ?: optional($u)->name,
                  'phone'      => optional($u)->phone,
                  'avatar_path'=> optional($u)->avatar_path,
              ];
          })->filter(fn($x)=>!empty($x->id));
      }
  } catch (\Throwable $e) { /* fallback below */ }

  if (!$people->count()) {
      $people = \App\Models\User::where('id','!=',auth()->id())
          ->orderByRaw('COALESCE(NULLIF(name, ""), phone)')
          ->get(['id','name','phone','avatar_path']);
  }

  // Build base URLs for JS (so we can push to DM or Group from search)
  $convShowBase  = preg_replace('#/0/?$#','/', route('chat.show', ['id'=>0]));
  $groupShowBase = preg_replace('#/0/?$#','/', route('groups.show', ['group'=>0]));
@endphp

<div
  class="col-md-4 col-lg-3 d-flex flex-column border-end"
  id="conversation-sidebar"
  data-conv-show-base="{{ $convShowBase }}"
  data-group-show-base="{{ $groupShowBase }}"
>
  <div class="sidebar-header p-3 border-bottom" style="background:var(--card);">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="m-0" style="font-weight:800;letter-spacing:.2px;">Chats</h5>

      <div class="d-flex gap-2">
        <button class="btn btn-wa btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#sb-new-chat" aria-expanded="false" aria-controls="sb-new-chat">
          <i class="bi bi-plus"></i> New
        </button>
        <button class="btn btn-outline-wa btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#sb-create-group" aria-expanded="false" aria-controls="sb-create-group">
          <i class="bi bi-people"></i> Group
        </button>
      </div>
    </div>

    {{-- Global search over existing chats & groups (hits /api/v1/search) --}}
    <div class="position-relative">
      <div class="input-group search-wrap">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" class="form-control" placeholder="Search chats & groups..." id="chat-search" autocomplete="off">
      </div>
      {{-- results popover --}}
      <div id="chat-search-results" class="list-group shadow-sm position-absolute w-100 d-none" style="z-index:1040; top: 110%; max-height: 320px; overflow:auto; background:var(--bg); border:1px solid var(--border);"></div>
    </div>
  </div>

  {{-- === Inline NEW CHAT panel === --}}
  <div id="sb-new-chat" class="collapse border-bottom" style="background:var(--bg);">
    <div class="p-3">
      <form id="sb-nc-form" action="{{ route('chat.start') }}" method="POST" class="d-none">
        @csrf
        <input type="hidden" name="user_id" id="sb-nc-user-id">
        <input type="hidden" name="phone"   id="sb-nc-phone"> {{-- optional: ChatController@start can accept phone --}}
      </form>

      <div class="d-flex align-items-center justify-content-between mb-2">
        <strong>Start a new chat</strong>
        <small class="muted" id="sb-nc-count">{{ $people->count() }} contacts</small>
      </div>

      <div class="input-group mb-2">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" id="sb-nc-search" class="form-control" placeholder="Search contacts by name or phone…" autocomplete="off">
      </div>

      {{-- Optional: start by phone (if not already a contact) --}}
      <div class="input-group mb-3">
        <span class="input-group-text"><i class="bi bi-telephone"></i></span>
        <input type="text" id="sb-nc-phone-input" class="form-control" placeholder="Start by phone (e.g. +233…)" autocomplete="tel">
        <button class="btn btn-outline-wa" id="sb-nc-start-phone" type="button">Start</button>
      </div>

      <div id="sb-nc-list" class="list-group" style="max-height: 280px; overflow:auto;">
        @foreach($people as $u)
          @php
            $display = $u->name ?: $u->phone ?: ('User #'.$u->id);
            $initial = strtoupper(mb_substr($display, 0, 1));
            $avatar  = $u->avatar_path ? Storage::url($u->avatar_path) : null;
          @endphp
          <button type="button"
                  class="list-group-item list-group-item-action d-flex align-items-center gap-2 sb-nc-row"
                  data-id="{{ $u->id }}"
                  data-name="{{ strtolower($u->name ?? '') }}"
                  data-phone="{{ strtolower($u->phone ?? '') }}">
            @if($avatar)
              <img src="{{ $avatar }}" class="rounded-circle" width="32" height="32" alt="avatar"
                   onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center',style:'width:32px;height:32px;',textContent:'{{ $initial }}'}));">
            @else
              <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" style="width:32px;height:32px;">{{ $initial }}</div>
            @endif
            <div class="flex-grow-1 text-start">
              <div class="fw-semibold text-truncate">{{ $display }}</div>
              <div class="small muted text-truncate">{{ $u->phone ?: 'ID #'.$u->id }}</div>
            </div>
            <i class="bi bi-chevron-right muted"></i>
          </button>
        @endforeach
      </div>

      <div class="d-flex gap-2 mt-2">
        <button type="button" class="btn btn-outline-secondary btn-sm flex-grow-1" data-bs-toggle="collapse" data-bs-target="#sb-new-chat">Close</button>
        <button type="button" id="sb-nc-start" class="btn btn-wa btn-sm flex-grow-1" disabled>
          <i class="bi bi-chat-dots me-1"></i> Start Chat
        </button>
      </div>
    </div>
  </div>

  {{-- === Inline CREATE GROUP panel === --}}
  <div id="sb-create-group" class="collapse border-bottom" style="background:var(--bg);">
    <div class="p-3">
      <form id="sb-gp-form" action="{{ route('groups.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="row g-3">
          <div class="col-12 col-sm-4">
            <label class="form-label">Group Photo</label>
            <div class="d-flex align-items-center gap-3">
              <img id="sb-gp-avatar-preview" src="{{ asset('icons/icon-192x192.png') }}" class="rounded" width="56" height="56" alt="preview">
              <input type="file" name="avatar" accept="image/*" class="form-control form-control-sm" id="sb-gp-avatar">
            </div>
            <small class="helper d-block mt-1">JPG/PNG/WebP • up to 2MB</small>
          </div>

          <div class="col-12 col-sm-8">
            <label class="form-label">Group Name <span class="text-danger">*</span></label>
            <input type="text" name="name" maxlength="64" class="form-control" placeholder="e.g. Family, Project, Study Group" required>
            <small class="helper d-block mt-1" id="sb-gp-name-left">64 chars left</small>

            <label class="form-label mt-3">Description</label>
            <textarea name="description" maxlength="200" class="form-control" rows="2" placeholder="What's this group about?"></textarea>
            <small class="helper d-block mt-1" id="sb-gp-desc-left">200 chars left</small>
          </div>
        </div>

        <div class="mt-3">
          <label class="form-label">Group Type</label>
          <div class="d-flex gap-4">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="is_private" id="sb-gp-public" value="0" checked>
              <label class="form-check-label" for="sb-gp-public"><i class="bi bi-globe me-1"></i> Public</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="is_private" id="sb-gp-private" value="1">
              <label class="form-check-label" for="sb-gp-private"><i class="bi bi-lock me-1"></i> Private</label>
            </div>
          </div>
        </div>

        {{-- Participants --}}
        <div class="mt-3">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <label class="form-label mb-0">Add participants <span class="text-danger">*</span></label>
            <small class="helper">Selected: <span id="sb-gp-count">0</span></small>
          </div>

          <div class="input-group mb-2">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" id="sb-gp-filter" class="form-control" placeholder="Search by name or phone…" autocomplete="off">
          </div>

          <div id="sb-gp-chips" class="d-flex flex-wrap gap-2 mb-2"></div>

          <div id="sb-gp-list" class="list-group" style="max-height:280px; overflow:auto;">
            @foreach($people as $u)
              @php
                $display = $u->name ?: $u->phone ?: ('User #'.$u->id);
                $initial = strtoupper(mb_substr($display, 0, 1));
                $avatar  = $u->avatar_path ? Storage::url($u->avatar_path) : null;
              @endphp
              <label class="list-group-item d-flex align-items-center gap-2 sb-gp-row"
                     data-name="{{ strtolower($u->name ?? '') }}"
                     data-phone="{{ strtolower($u->phone ?? '') }}">
                <input type="checkbox" class="form-check-input me-1 sb-gp-check" name="members[]" value="{{ $u->id }}">
                @if($avatar)
                  <img src="{{ $avatar }}" class="rounded-circle" width="32" height="32" alt="avatar"
                       onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center',style:'width:32px;height:32px;',textContent:'{{ $initial }}'}));">
                @else
                  <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" style="width:32px;height:32px;">{{ $initial }}</div>
                @endif
                <div class="flex-grow-1 text-start">
                  <div class="fw-semibold text-truncate">{{ $display }}</div>
                  <div class="small muted text-truncate">{{ $u->phone ?: 'ID #'.$u->id }}</div>
                </div>
              </label>
            @endforeach
          </div>

          <div class="d-flex gap-2 mt-2">
            <button class="btn btn-outline-secondary btn-sm" type="button" id="sb-gp-clear">Clear</button>
            <button class="btn btn-outline-wa btn-sm" type="button" id="sb-gp-select-all">Select all (filtered)</button>
          </div>
        </div>

        <div class="d-flex gap-2 mt-3">
          <button type="button" class="btn btn-outline-secondary flex-grow-1 btn-sm" data-bs-toggle="collapse" data-bs-target="#sb-create-group">Cancel</button>
          <button type="submit" class="btn btn-wa flex-grow-1 btn-sm" id="sb-gp-create">
            <i class="bi bi-people-fill me-1"></i> Create Group
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- === Existing conversations list === --}}
  <div class="conversation-list flex-grow-1 overflow-auto" id="conversation-list">
    @if(isset($botConversation))
      @php
        $botLastMsg  = optional($botConversation->latestMessage);
        $lastBot     = $botLastMsg?->display_body ?? 'Start chatting with GekyBot';
        $lastBotTime = $botLastMsg?->created_at?->diffForHumans() ?? 'No messages yet';
      @endphp
      <a href="{{ route('chat.show', $botConversation->id) }}"
         class="conversation-item d-flex align-items-center p-3 text-decoration-none"
         data-name="gekybot" data-phone="" data-last="{{ strtolower($lastBot) }}">
        <div class="avatar me-3 rounded-circle bg-brand text-white">🤖</div>
        <div class="flex-grow-1">
          <div class="d-flex justify-content-between">
            <strong>GekyBot</strong>
            <small class="muted">{{ $lastBotTime }}</small>
          </div>
          <p class="mb-0 text-truncate muted">{{ $lastBot }}</p>
        </div>
      </a>
    @endif

    {{-- 1:1 Conversations --}}
  {{-- 1:1 Conversations (pivot-based) --}}
@foreach(($conversations ?? collect()) as $conv)
  @php
    // Uses accessors from the new Conversation model
    $displayName = $conv->title;                 // group name or other participant’s name/phone
    $initial     = strtoupper(mb_substr($displayName, 0, 1));
    $avatarUrl   = $conv->avatar_url;            // already a Storage::url or null
    $lastMsg     = $conv->lastMessage;           // eager-loaded alias of latestMessage
    $lastBody    = $lastMsg?->display_body ?? $lastMsg?->body ?? 'No messages yet';
    $lastTime    = $lastMsg?->created_at?->diffForHumans() ?? 'No messages yet';
    $unreadCount = (int) ($conv->unread_count ?? 0);
    $other       = $conv->other_user;            // only for direct chats; null for groups
    $otherPhone  = $other?->phone ?? '';
  @endphp

  <a href="{{ route('chat.show', $conv->id) }}"
     class="conversation-item d-flex align-items-center p-3 text-decoration-none {{ $unreadCount > 0 ? 'unread' : '' }}"
     data-name="{{ strtolower($displayName) }}"
     data-phone="{{ strtolower($otherPhone) }}"
     data-last="{{ strtolower($lastBody) }}"
     data-unread="{{ $unreadCount }}">
    @if($avatarUrl)
      <img
        src="{{ $avatarUrl }}"
        alt="{{ $displayName }} avatar"
        class="avatar avatar-img me-3"
        loading="lazy"
        onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'avatar me-3 rounded-circle bg-avatar text-white',textContent:'{{ $initial }}'}));"
      >
    @else
      <div class="avatar me-3 rounded-circle bg-avatar text-white">{{ $initial }}</div>
    @endif

    <div class="flex-grow-1">
      <div class="d-flex justify-content-between">
        <strong>{{ $displayName }}</strong>
        <small class="muted">{{ $lastTime }}</small>
      </div>
      <p class="mb-0 text-truncate muted">{{ $lastBody }}</p>
    </div>

    @if($unreadCount > 0)
      <span class="badge rounded-pill ms-2 unread-badge">{{ $unreadCount }}</span>
    @endif
  </a>
@endforeach


    {{-- Groups --}}
    @if(($groups ?? collect())->count())
      <div class="px-3 pt-3 pb-2 text-uppercase small muted">Groups</div>
      @foreach($groups as $g)
        @php
          $latest     = optional($g->messages->first());
          $gLastBody  = $latest?->body ?? 'No messages yet';
          $gLastTime  = $latest?->created_at?->diffForHumans() ?? 'No messages yet';
          $gInitial   = strtoupper(mb_substr($g->name ?? 'Group', 0, 1));
          $gAvatarUrl = $g->avatar_path ? asset(Storage::url($g->avatar_path)) : null;
        @endphp

        <a href="{{ route('groups.show', $g->id) }}"
           class="conversation-item d-flex align-items-center p-3 text-decoration-none"
           data-name="{{ strtolower($g->name ?? '') }}"
           data-phone=""
           data-last="{{ strtolower($gLastBody) }}">
          @if($gAvatarUrl)
            <img
              src="{{ $gAvatarUrl }}"
              alt="{{ $g->name }} avatar"
              class="avatar avatar-img me-3"
              loading="lazy"
              onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'avatar me-3 rounded-circle bg-avatar text-white',textContent:'{{ $gInitial }}'}));"
            >
          @else
            <div class="avatar me-3 rounded-circle bg-avatar text-white">{{ $gInitial }}</div>
          @endif

          <div class="flex-grow-1">
            <div class="d-flex justify-content-between">
              <strong>{{ $g->name }}</strong>
              <small class="muted">{{ $gLastTime }}</small>
            </div>
            <p class="mb-0 text-truncate muted">{{ $gLastBody }}</p>
          </div>

          <span class="badge rounded-pill ms-2" style="background:var(--border);color:var(--text);">Group</span>
        </a>
      @endforeach
    @endif
  </div>
</div>

{{-- Sidebar JS (scoped) --}}
<script>
(function(){
  // ===== Utils =====
  const $ = sel => document.querySelector(sel);
  const $$ = (sel,root=document)=> Array.from(root.querySelectorAll(sel));
  const debounce = (fn,ms)=>{ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a),ms); }; };
  const escapeHtml = s => String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

  const root = document.getElementById('conversation-sidebar');
  const convBase  = root?.dataset?.convShowBase || '/chat/';
  const groupBase = root?.dataset?.groupShowBase || '/groups/';

  // ===== Global search → /api/v1/search =====
  const searchInput = document.getElementById('chat-search');
  const resultsBox  = document.getElementById('chat-search-results');

  function hideResults(){ resultsBox.classList.add('d-none'); resultsBox.innerHTML=''; }
  function showResults(){ resultsBox.classList.remove('d-none'); }

  async function doSearch() {
    const q = (searchInput.value || '').trim();
    if (!q) { hideResults(); return; }
    try {
      const res = await fetch(`/api/v1/search?q=${encodeURIComponent(q)}`);
      if (!res.ok) throw new Error('Search failed');
      const payload = await res.json();
      const list = Array.isArray(payload?.data) ? payload.data : [];
      if (!list.length) { resultsBox.innerHTML =
        `<div class="list-group-item text-center muted">No results</div>`; showResults(); return; }

      resultsBox.innerHTML = list.map(x => {
        const id = x.id; const type = x.type; const name = escapeHtml(x.name || (type==='group'?'Group':'Chat'));
        const last = escapeHtml(x.last_message || '');
        const chip = type==='group' ? '<span class="badge rounded-pill ms-2">Group</span>' : '';
        return `
          <a href="#" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between"
             data-type="${type}" data-id="${id}">
            <div class="text-truncate">
              <div class="fw-semibold text-truncate">${name} ${chip}</div>
              ${last ? `<div class="small muted text-truncate">${last}</div>` : ``}
            </div>
            <i class="bi bi-chevron-right muted"></i>
          </a>`;
      }).join('');
      showResults();
    } catch (e) {
      resultsBox.innerHTML = `<div class="list-group-item text-danger">Search error</div>`;
      showResults();
    }
  }
  searchInput?.addEventListener('input', debounce(doSearch, 200));
  resultsBox?.addEventListener('click', (e)=>{
    const a = e.target.closest('a[data-type]');
    if (!a) return;
    e.preventDefault();
    const id = a.dataset.id;
    const type = a.dataset.type;
    window.location.href = (type==='group' ? groupBase : convBase) + id;
  });
  document.addEventListener('click', (e)=> {
    if (!resultsBox.contains(e.target) && e.target !== searchInput) hideResults();
  });

  // ===== New Chat panel (select contact) =====
  const ncPanel  = document.getElementById('sb-new-chat');
  const ncForm   = document.getElementById('sb-nc-form');
  const ncHidden = document.getElementById('sb-nc-user-id');
  const ncList   = document.getElementById('sb-nc-list');
  const ncSearch = document.getElementById('sb-nc-search');
  const ncCount  = document.getElementById('sb-nc-count');
  const ncStart  = document.getElementById('sb-nc-start');

  function ncFilter(){
    const q = (ncSearch.value || '').toLowerCase().trim();
    let vis=0;
    $$('.sb-nc-row', ncList).forEach(row=>{
      const name = row.dataset.name || '';
      const phone= row.dataset.phone|| '';
      const show = !q || name.includes(q) || phone.includes(q);
      row.style.display = show ? 'flex' : 'none';
      if (show) vis++;
    });
    if (ncCount) ncCount.textContent = `${vis} ${vis===1?'contact':'contacts'}`;
  }
  function ncReset(){
    if (!ncList) return;
    ncHidden.value = '';
    if (ncStart) ncStart.disabled = true;
    $$('.sb-nc-row.active', ncList).forEach(r => r.classList.remove('active'));
    if (ncSearch) { ncSearch.value=''; ncFilter(); }
  }
  if (ncPanel) {
    ncList?.addEventListener('click', (e)=>{
      const row = e.target.closest('.sb-nc-row'); if (!row) return;
      $$('.sb-nc-row.active', ncList).forEach(r=>r.classList.remove('active'));
      row.classList.add('active');
      ncHidden.value = row.dataset.id || '';
      if (ncStart) ncStart.disabled = !ncHidden.value;
    });
    ncSearch?.addEventListener('input', debounce(ncFilter, 150));
    ncStart?.addEventListener('click', ()=> { if (ncHidden.value) ncForm.submit(); });
    ncPanel.addEventListener('hidden.bs.collapse', ncReset);
    ncFilter();
  }

  // Start chat by phone (optional)
  const phoneInput = document.getElementById('sb-nc-phone-input');
  const phoneBtn   = document.getElementById('sb-nc-start-phone');
  const phoneHidden= document.getElementById('sb-nc-phone');
  phoneBtn?.addEventListener('click', ()=>{
    const raw = (phoneInput.value || '').trim();
    if (!raw) return;
    // normalize very lightly: keep + and digits
    const plus = raw.startsWith('+') ? '+' : '';
    const digits = raw.replace(/\D+/g,'');
    phoneHidden.value = plus + digits;
    ncHidden.value = ''; // ensure user_id not set
    ncForm.submit();
  });

  // ===== Create Group panel =====
  const gpPanel     = document.getElementById('sb-create-group');
  const gpForm      = document.getElementById('sb-gp-form');
  const gpAvatarI   = document.getElementById('sb-gp-avatar');
  const gpAvatarP   = document.getElementById('sb-gp-avatar-preview');
  const gpName      = gpForm?.querySelector('input[name="name"]');
  const gpDesc      = gpForm?.querySelector('textarea[name="description"]');
  const gpNameL     = document.getElementById('sb-gp-name-left');
  const gpDescL     = document.getElementById('sb-gp-desc-left');
  const gpList      = document.getElementById('sb-gp-list');
  const gpFilter    = document.getElementById('sb-gp-filter');
  const gpChips     = document.getElementById('sb-gp-chips');
  const gpCount     = document.getElementById('sb-gp-count');
  const gpSelectAll = document.getElementById('sb-gp-select-all');
  const gpClear     = document.getElementById('sb-gp-clear');

  function updateCounter(el,max,out){
    if (!el || !out) return;
    const left = max - (el.value||'').length;
    out.textContent = `${left} chars left`;
    out.style.color = left < 10 ? 'var(--bs-danger)' : 'var(--wa-muted)';
  }
  function gpRefreshChips(){
    gpChips.innerHTML='';
    $$('.sb-gp-check:checked', gpList).forEach(cb=>{
      const row = cb.closest('.sb-gp-row');
      const name = row.querySelector('.fw-semibold')?.textContent?.trim() || 'User';
      const phone= row.querySelector('.muted')?.textContent?.trim() || '';
      const chip = document.createElement('div');
      chip.className='d-inline-flex align-items-center gap-2 px-2 py-1 rounded-pill';
      chip.style.cssText='background:var(--card);border:1px solid var(--border);';
      chip.innerHTML = `<span>${escapeHtml(name)} <small class="muted">${escapeHtml(phone)}</small></span><button type="button" class="btn btn-sm p-0" title="Remove"><i class="bi bi-x"></i></button>`;
      chip.querySelector('button').addEventListener('click', (ev)=>{ ev.stopPropagation(); cb.checked=false; gpRefreshChips(); gpUpdateCount(); });
      gpChips.appendChild(chip);
    });
  }
  function gpUpdateCount(){ gpCount.textContent = String($$('.sb-gp-check:checked', gpList).length); }
  function gpFilterRows(){
    const q = (gpFilter.value||'').toLowerCase().trim();
    let visible = 0;
    $$('.sb-gp-row', gpList).forEach(row=>{
      const name = row.dataset.name || '';
      const phone= row.dataset.phone|| '';
      const show = !q || name.includes(q) || phone.includes(q);
      row.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    gpSelectAll.disabled = visible===0;
  }

  if (gpPanel) {
    gpAvatarI?.addEventListener('change',(e)=>{
      const [file] = e.target.files || [];
      if (!file) return;
      const okTypes = ['image/jpeg','image/png','image/webp'];
      if (!okTypes.includes(file.type)) { alert('Please upload a JPG, PNG, or WebP image.'); e.target.value=''; return; }
      if (file.size > 2*1024*1024) { alert('Image size exceeds 2MB.'); e.target.value=''; return; }
      const r = new FileReader();
      r.onload = ev => gpAvatarP.src = ev.target.result;
      r.readAsDataURL(file);
    });

    gpName?.addEventListener('input', ()=>updateCounter(gpName,64,gpNameL));
    gpDesc?.addEventListener('input', ()=>updateCounter(gpDesc,200,gpDescL));
    updateCounter(gpName,64,gpNameL); updateCounter(gpDesc,200,gpDescL);

    gpFilter?.addEventListener('input', debounce(gpFilterRows, 150));
    gpList?.addEventListener('change',(e)=>{
      if (e.target.classList.contains('sb-gp-check')) { gpRefreshChips(); gpUpdateCount(); }
    });
    gpSelectAll?.addEventListener('click', ()=>{
      $$('.sb-gp-row', gpList).forEach(row=>{
        if (row.style.display==='none') return;
        const cb=row.querySelector('.sb-gp-check'); if (cb) cb.checked = true;
      });
      gpRefreshChips(); gpUpdateCount();
    });
    gpClear?.addEventListener('click', ()=>{
      $$('.sb-gp-check:checked', gpList).forEach(cb=>cb.checked=false);
      gpRefreshChips(); gpUpdateCount();
    });
    gpForm?.addEventListener('submit', (e)=>{
      if ($$('.sb-gp-check:checked', gpList).length === 0) { e.preventDefault(); alert('Please select at least one participant.'); }
    });

    gpPanel.addEventListener('hidden.bs.collapse', ()=>{
      gpForm?.reset();
      gpAvatarP.src = "{{ asset('icons/icon-192x192.png') }}";
      gpFilter.value=''; gpFilterRows(); gpRefreshChips(); gpUpdateCount();
      updateCounter(gpName,64,gpNameL); updateCounter(gpDesc,200,gpDescL);
    });

    gpFilterRows(); gpRefreshChips(); gpUpdateCount();
  }
})();
</script>
