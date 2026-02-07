<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="manifest" href="{{ url('/manifest.webmanifest') }}">
        <meta name="theme-color" content="#06b6d4">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Breakster') }}">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" type="image/png" sizes="192x192" href="/pwa/icon-192.png">
        <link rel="icon" type="image/png" sizes="512x512" href="/pwa/icon-512.png">
        <link rel="apple-touch-icon" href="/pwa/apple-touch-icon.png">

        <title inertia>{{ config('app.name', 'Breakster') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="min-h-full bg-gray-950 text-gray-50">
        @inertia
    </body>
</html>
