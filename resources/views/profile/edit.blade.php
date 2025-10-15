@extends('layouts.app')

@section('content')
<style>
  .wa-card{background:var(--card);border:1px solid var(--border);border-radius:18px;box-shadow:var(--wa-shadow);}
  .btn-wa{background:var(--wa-green);border:none;color:#062a1f;font-weight:600;border-radius:14px;}
  .avatar-preview{width:96px;height:96px;border-radius:50%;object-fit:cover;border:1px solid var(--border);}
</style>

<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-7">
      <div class="wa-card p-3 p-md-4">
        <h5 class="mb-3">Profile</h5>

        @if (session('status')) <div class="alert alert-success">{{ session('status') }}</div> @endif
        @if ($errors->any())
          <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
          @csrf

          <div class="mb-3 d-flex align-items-center gap-3">
            <img id="avatarPreview" class="avatar-preview"
                 src="{{ $user->avatar_path ? asset('storage/'.$user->avatar_path) : asset('icons/icon-192x192.png') }}" alt="">
            <div>
              <label class="form-label mb-1">Photo</label>
              <input type="file" name="avatar" class="form-control" accept="image/*" onchange="previewAvatar(event)">
              <small class="text-muted">JPG/PNG up to 2MB</small>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Display Name</label>
            <input class="form-control" type="text" name="name" maxlength="60" value="{{ old('name', $user->name) }}" placeholder="Your name">
          </div>

          <div class="mb-3">
            <label class="form-label">About</label>
            <input class="form-control" type="text" name="about" maxlength="160" value="{{ old('about', $user->about) }}" placeholder="Hey there! I am using GekyChat.">
          </div>

          <div class="mb-3">
            <label class="form-label">Phone</label>
            <input class="form-control" type="text" value="{{ $user->phone }}" disabled>
            <small class="text-muted">Phone is your login. Email & password can be added later for 2FA.</small>
          </div>

          <button class="btn btn-wa px-4" type="submit">Save</button>
          <a href="{{ route('chat.index') }}" class="btn btn-outline-secondary ms-2">Back</a>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
  function previewAvatar(e){
    const [file] = e.target.files;
    if (file) document.getElementById('avatarPreview').src = URL.createObjectURL(file);
  }
</script>
@endsection
