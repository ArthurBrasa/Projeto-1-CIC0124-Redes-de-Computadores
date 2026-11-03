<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PULSEMONITOR — @yield('title')</title>
    <link rel="stylesheet" href="{{ asset('pulse/pulse.css') }}">
    @stack('styles')
</head>
<body>
@yield('content')

@stack('modals')

<script src="{{ asset('pulse/pulse.js') }}"></script>
@stack('scripts')
</body>
</html>
