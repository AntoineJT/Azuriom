@extends('admin.layouts.admin')

@section('title', 'Create page')

@include('admin.elements.editor')

@section('content')
    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('admin.pages.store') }}" method="POST">
                @csrf

                <div class="form-group">
                    <label for="titleInput">Title</label>
                    <input type="text" class="form-control @error('title') is-invalid @enderror" id="titleInput" name="title" value="{{ old('title') }}" required>

                    @error('title')
                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="descriptionInput">Description</label>
                    <input type="text" class="form-control @error('description') is-invalid @enderror" id="descriptionInput" name="description" value="{{ old('description') }}" required>

                    @error('description')
                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="slugInput">Slug</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <div class="input-group-text">{{ url('/') }}/</div>
                        </div>
                        <input type="text" class="form-control @error('slug') is-invalid @enderror" id="slugInput" name="slug" value="{{ old('slug') }}" required>

                        @error('slug')
                        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>
                </div>

                <div class="form-group">
                    <label for="textArea">Content</label>
                    <textarea class="form-control html-editor @error('content') is-invalid @enderror" id="textArea" name="content" rows="5">{{ old('content') }}</textarea>

                    @error('content')
                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                    @enderror
                </div>

                <div class="form-group custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="enableSwitch" name="is_enabled" checked>
                    <label class="custom-control-label" for="enableSwitch">Enable the page</label>
                </div>

                <button type="submit" class="btn btn-primary">Create</button>
            </form>
        </div>
    </div>
@endsection
