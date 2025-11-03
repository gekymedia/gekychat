{{--
    Basic group editing page. Allows a group admin to update the group
    name, description and privacy. This page is rendered by the
    GroupController@edit method and posts to groups.update.
--}}

@extends('layouts.app')

@section('title', 'Edit Group')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-card border-bottom border-border">
                    <h1 class="h5 mb-0">Edit Group</h1>
                </div>
                <div class="card-body bg-bg">
                    {{-- Display validation errors --}}
                    @if($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('groups.update', $group->id) }}" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label for="name" class="form-label">Group Name</label>
                            <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $group->name) }}" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="3">{{ old('description', $group->description) }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label for="avatar" class="form-label">Group Avatar</label>
                            <input type="file" id="avatar" name="avatar" class="form-control" accept="image/*">
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="is_public" name="is_public" value="1" {{ old('is_public', $group->is_public) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_public">Public Group</label>
                        </div>
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('groups.show', $group->id) }}" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-wa">Update Group</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection