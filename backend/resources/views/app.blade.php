<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f7f5f0">
    <meta name="description" content="Turn GitHub URLs into polished AI reports.">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'AI Launcher') }} — GitHub in. Answers out.</title>

    @viteReactRefresh
    @vite('resources/ts/app.tsx')
</head>
<body>
    <div id="root"></div>
</body>
</html>
