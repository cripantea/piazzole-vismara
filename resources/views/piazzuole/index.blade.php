@extends('layout')

@section('content')

    Piazzuole

    @foreach($piazzuole as $piazzuola)
        <div>
            <h3>{{ $piazzuola->nome }}</h3>
        </div>
    @endforeach


@endsection