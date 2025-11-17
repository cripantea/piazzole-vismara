<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Piazzole Gianmario</title>
</head>
<body>

<div class="flex mx-auto mx-6">
    <a href="{{ route('piazzuole.index') }}">Piazzuole</a> |
    <a href="{{ route('clienti.index') }}">Clienti</a> |
    <a href="{{ route('contratti.index') }}">Contratti</a> |
    <a href="{{ route('scadenze.index') }}">Scadenze</a>

</div>

@yield('content')



</body>
</html>

