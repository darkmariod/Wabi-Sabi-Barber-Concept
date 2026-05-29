<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo — Sistema de Garantías Paraíso</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #333;
            padding: 20px;
        }
        .container { max-width: 900px; margin: 0 auto; }

        /* Header */
        .header {
            background: #8B0000;
            color: #fff;
            padding: 24px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 24px;
        }
        .header h1 { font-size: 22px; margin-bottom: 4px; }
        .header p  { font-size: 14px; opacity: 0.9; }

        /* QR */
        .qr-section {
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .qr-section img {
            width: 220px;
            height: 220px;
            image-rendering: pixelated;
        }
        .qr-section .label {
            font-size: 14px;
            color: #666;
            margin-top: 10px;
        }
        .qr-section .serial {
            font-size: 18px;
            font-weight: bold;
            color: #8B0000;
            margin-top: 4px;
        }

        /* Links rápidos */
        .quick-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        .quick-links a {
            flex: 1;
            min-width: 180px;
            padding: 14px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            transition: transform 0.1s;
        }
        .quick-links a:hover { transform: scale(1.02); }
        .btn-primary { background: #8B0000; color: #fff; }
        .btn-secondary { background: #e8e8e8; color: #333; }
        .btn-outline { background: #fff; color: #8B0000; border: 2px solid #8B0000; }

        /* Info servidor */
        .server-info {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 13px;
            margin-bottom: 20px;
        }
        .server-info strong { color: #856404; }

        /* Tablas */
        .card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .card h2 {
            font-size: 16px;
            color: #8B0000;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #8B0000;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        th {
            text-align: left;
            padding: 8px 6px;
            border-bottom: 2px solid #ddd;
            color: #666;
            font-weight: 600;
        }
        td {
            padding: 8px 6px;
            border-bottom: 1px solid #eee;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-green  { background: #d4edda; color: #155724; }
        .badge-blue   { background: #cce5ff; color: #004085; }
        .badge-gray   { background: #e2e3e5; color: #383d41; }
        .estado-disponible  { color: #28a745; font-weight: 600; }
        .estado-registrado  { color: #8B0000; font-weight: 600; }
        .estado-anulado     { color: #999; }
        .text-muted { color: #999; font-size: 12px; }

        /* Archivos */
        .files-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 10px;
        }
        .file-item {
            background: #f9f9f9;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 12px;
            font-size: 13px;
        }
        .file-item .name  { font-weight: 600; color: #333; word-break: break-all; }
        .file-item .size  { color: #666; font-size: 12px; margin-top: 2px; }
        .file-item .path  { color: #999; font-size: 11px; margin-top: 2px; word-break: break-all; }

        .empty {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        .empty p { margin-top: 8px; font-size: 14px; }
        .empty .big { font-size: 40px; }

        .footer {
            text-align: center;
            font-size: 12px;
            color: #999;
            padding: 20px;
        }
        .btn-sm {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            background: #8B0000;
            color: #fff;
        }
        .btn-sm-gray {
            background: #e8e8e8;
            color: #333;
        }

        @media (max-width: 600px) {
            body { padding: 10px; }
            .quick-links a { min-width: 100%; }
            .header h1 { font-size: 18px; }
        }
    </style>
</head>
<body>
<div class="container">

    {{-- HEADER --}}
    <div class="header">
        <h1>🏭 Sistema de Garantías Paraíso</h1>
        <p>Demo interactiva — mostrale al cliente cómo funciona</p>
    </div>

    {{-- INFO SERVIDOR --}}
    @php
        // Detectar IP local de la red para que el QR funcione desde el celular
        $lanIp = null;
        if (PHP_OS_FAMILY === 'Darwin') {
            $lanIp = @trim(shell_exec("/sbin/ifconfig 2>/dev/null | grep 'inet ' | grep -v '127.0.0.1' | awk '{print \$2}' | head -1"));
        } elseif (PHP_OS_FAMILY === 'Linux') {
            $lanIp = @trim(shell_exec("ip -4 addr show 2>/dev/null | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | grep -v '127.0.0.1' | head -1"));
        }
        if (!$lanIp || $lanIp === '127.0.0.1') {
            $lanIp = request()->getHost();
        }
        $port = request()->getPort();
        $lanUrl = "http://{$lanIp}" . ($port !== 80 ? ":{$port}" : "");
    @endphp
    <div class="server-info">
        <strong>📡 Servidor:</strong> {{ $lanUrl }}
    </div>

    {{-- PASOS RÁPIDOS --}}
    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; font-size:13px;">
        <span style="background:#8B0000; color:#fff; padding:6px 14px; border-radius:20px; font-weight:600;">1</span>
        <span style="padding:6px 0;">Escaneá el QR de abajo</span>
        <span style="background:#8B0000; color:#fff; padding:6px 14px; border-radius:20px; font-weight:600;">2</span>
        <span style="padding:6px 0;">Registrá la garantía</span>
        <span style="background:#8B0000; color:#fff; padding:6px 14px; border-radius:20px; font-weight:600;">3</span>
        <span style="padding:6px 0;">Todo guardado en el sistema ✅</span>
    </div>

    @php
        $firstLabel = $labels->first();
    @endphp

    {{-- QR --}}
    <div class="qr-section">
        @if($firstLabel)
            @php
                $qrUrl = $lanUrl . '/p/' . $firstLabel->serial;
                $qrSvg = QrCode::size(220)->generate($qrUrl);
            @endphp
            {!! $qrSvg !!}
            <div class="label">📱 Escaneá este QR con tu celular</div>
            <div class="serial">{{ $firstLabel->serial }}</div>
        @else
            <div class="empty">
                <div class="big">📦</div>
                <p>No hay etiquetas aún</p>
                <p style="margin-top:12px;">
                    <code style="background:#eee; padding:6px 12px; border-radius:4px;">
                        php artisan demo:full-flow --fresh
                    </code>
                </p>
            </div>
        @endif
    </div>

    @if($firstLabel)
    {{-- LINKS RÁPIDOS --}}
    <div class="quick-links">
        <a href="{{ route('public.product', $firstLabel->serial) }}" class="btn-primary" target="_blank">
            📄 Ver producto
        </a>
        @if($firstLabel->status === 'available')
        <a href="{{ route('public.warranty.form', $firstLabel->serial) }}" class="btn-secondary" target="_blank">
            📝 Registrar garantía
        </a>
        @else
        <a href="{{ route('public.warranty.certificate', $firstLabel->serial) }}" class="btn-secondary" target="_blank">
            📜 Ver certificado
        </a>
        @endif
        <a href="{{ route('public.warranty.certificate', [$firstLabel->serial, 'download' => 1]) }}" class="btn-outline" target="_blank">
            ⬇️ Descargar PDF
        </a>
    </div>
    @endif

    {{-- ETIQUETAS GENERADAS --}}
    <div class="card">
        <h2>📦 Etiquetas generadas ({{ $labels->count() }})</h2>

        @if($labels->isEmpty())
            <div class="empty">
                <div class="big">🏷️</div>
                <p>No hay etiquetas. Ejecutá <code>php artisan demo:full-flow --fresh</code></p>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Serial</th>
                        <th>Producto</th>
                        <th>Estado</th>
                        <th>Garantía</th>
                        <th>Links</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($labels as $label)
                    <tr>
                        <td><strong>{{ $label->serial }}</strong></td>
                        <td>{{ $label->product->name ?? $label->product->product_code ?? '-' }}</td>
                        <td>
                            @switch($label->status)
                                @case('available')
                                    <span class="estado-disponible">✅ Disponible</span>
                                    @break
                                @case('registered')
                                    <span class="estado-registrado">🔴 Registrada</span>
                                    @break
                                @case('anulled')
                                    <span class="estado-anulado">❌ Anulada</span>
                                    @break
                                @default
                                    <span class="text-muted">{{ $label->status }}</span>
                            @endswitch
                        </td>
                        <td>
                            @if($label->warranty)
                                <span class="badge badge-green">
                                    {{ $label->warranty->warranty_start_date?->format('d/m/Y') }} → {{ $label->warranty->warranty_end_date?->format('d/m/Y') }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('public.product', $label->serial) }}" class="btn-sm" target="_blank">👁️</a>
                            @if($label->status === 'available')
                                <a href="{{ route('public.warranty.form', $label->serial) }}" class="btn-sm btn-sm-gray" target="_blank">📝</a>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- LOTES --}}
    <div class="card">
        <h2>📋 Lotes generados ({{ $batches->count() }})</h2>

        @if($batches->isEmpty())
            <div class="empty">
                <div class="big">📋</div>
                <p>No hay lotes. Ejecutá <code>php artisan demo:full-flow --fresh</code></p>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Código interno</th>
                        <th>Producto</th>
                        <th>Cant.</th>
                        <th>Serial desde</th>
                        <th>Serial hasta</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($batches as $batch)
                    <tr>
                        <td><strong>{{ $batch->internal_batch_code }}</strong></td>
                        <td>{{ $batch->product?->name ?? '-' }}</td>
                        <td>{{ $batch->quantity }}</td>
                        <td>{{ $batch->serial_from ?? '-' }}</td>
                        <td>{{ $batch->serial_to ?? '-' }}</td>
                        <td>
                            @switch($batch->status)
                                @case('generated')
                                    <span class="badge badge-green">Generado</span>
                                    @break
                                @case('active')
                                    <span class="badge badge-blue">Activo</span>
                                    @break
                                @case('anulled')
                                    <span class="badge badge-gray">Anulado</span>
                                    @break
                                @default
                                    <span>{{ $batch->status }}</span>
                            @endswitch
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- ARCHIVOS GENERADOS --}}
    @php
        $demoFiles = [];
        $demoDir = storage_path('app/demo');
        if (is_dir($demoDir)) {
            $files = new \FilesystemIterator($demoDir);
            foreach ($files as $file) {
                $demoFiles[] = [
                    'name' => $file->getFilename(),
                    'size' => $file->getSize(),
                    'path' => $file->getPathname(),
                    'ext'  => $file->getExtension(),
                ];
            }
            usort($demoFiles, fn($a, $b) => strcmp($a['name'], $b['name']));
        }
    @endphp

    @if(!empty($demoFiles))
    <div class="card">
        <h2>📄 Archivos generados</h2>
        <div class="files-grid">
            @foreach($demoFiles as $file)
            <div class="file-item">
                <div class="name">
                    @if($file['ext'] === 'zpl') 🖨️
                    @elseif($file['ext'] === 'pdf') 📄
                    @else 📎
                    @endif
                    {{ $file['name'] }}
                </div>
                <div class="size">{{ number_format($file['size']) }} bytes</div>
                <div class="path">{{ $file['path'] }}</div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="footer">
        Sistema de Garantías — Productos Paraíso del Ecuador 🇪🇨
    </div>

</div>
</body>
</html>
