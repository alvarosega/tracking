<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="theme-color" content="#FFFFFF">
    <meta name="format-detection" content="telephone=no">
    <title>Panel de Supervisión - Tracking Ventas</title>
    
    <!-- Fuentes Tipográficas Modernas -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    @routes
    <script>
        if (typeof Ziggy !== 'undefined') {
            window.Ziggy = Ziggy;
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="text-[#1D1D1F] antialiased selection:bg-[#0071E3] selection:text-white min-h-screen">
    @inertia
</body>
</html>