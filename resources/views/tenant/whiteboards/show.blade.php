<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $whiteboard->title }} — {{ config('app.name', 'Lectura') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/whiteboard/main.jsx'])
    <style>
        html, body { margin: 0; padding: 0; height: 100%; overflow: hidden; }
        #whiteboard-root { height: 100vh; width: 100vw; }
    </style>
</head>
<body class="bg-slate-100">
    <div
        id="whiteboard-root"
        data-board-id="{{ $whiteboard->id }}"
        data-title="{{ $whiteboard->title }}"
        data-user-id="{{ auth()->id() }}"
        data-user-name="{{ auth()->user()->name }}"
        data-scene="{{ $whiteboard->scene_data ? json_encode($whiteboard->scene_data) : '' }}"
        data-scene-url="{{ route('tenant.whiteboards.scene', [app('current_tenant')->slug, $whiteboard]) }}"
        data-csrf="{{ csrf_token() }}"
        data-channel="whiteboard.{{ $whiteboard->id }}"
    ></div>
</body>
</html>
