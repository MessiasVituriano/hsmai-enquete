<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#4b2866">
    <title>Enquete ao vivo · <?php echo e(config('enquete.event_name')); ?></title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body class="antialiased">
    
    <div id="app">
        <div style="min-height:100dvh;display:grid;place-items:center;background:#ece7f3;font-family:'Instrument Sans',ui-sans-serif,system-ui,sans-serif">
            <div style="text-align:center">
                <p style="margin:0;font-size:24px;font-weight:700;color:#4b2866">Enquete ao vivo</p>
                <p style="margin:8px 0 0;font-size:14px;color:#6b6178">Carregando…</p>
                <p style="margin:40px 0 0;display:flex;align-items:center;justify-content:center;gap:8px;font-size:12px;color:#8a8095">
                    desenvolvido por:
                    <img src="/brand/logo-io.svg" alt="" width="13" height="17">
                    <strong style="font-size:14px;font-weight:900;color:#0b4c93">Sistema IO</strong>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
<?php /**PATH /Users/messiasvituriano/projects/enquete-hsmai/resources/views/app.blade.php ENDPATH**/ ?>