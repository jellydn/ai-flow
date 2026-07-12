<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#181714">
    <meta name="description" content="AI Flow turns GitHub URLs into structured AI workflows. Paste a repo, PR, or issue, pick a workflow, and get a polished, shareable report in under a minute.">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <meta property="og:type" content="website">
    <meta property="og:title" content="AI Flow — GitHub in. Answers out.">
    <meta property="og:description" content="Turn GitHub URLs into structured AI workflows. No prompt engineering.">
    <meta name="twitter:card" content="summary">

    <title>{{ config('app.name', 'AI Flow') }} — GitHub in. Answers out.</title>

    @viteReactRefresh
    @vite('resources/ts/app.tsx')
</head>
<body>
    <div id="root"></div>
</body>
</html>
