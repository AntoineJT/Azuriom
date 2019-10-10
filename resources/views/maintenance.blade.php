@extends('layouts.app')

@section('title', 'Maintenance')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">Maintenance</div>

                    <div class="card-body">
                        <h1>{{ setting('maintenance-message', 'The website is under maintenance') }}</h1>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
