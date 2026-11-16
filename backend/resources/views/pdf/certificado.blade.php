<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Certificado {{ $c['codigo_unico'] }}</title>
    <style>
        @page { margin: 0; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #1f2937; }
        .marco { position: absolute; top: 22px; left: 22px; right: 22px; bottom: 22px; border: 3px solid #0f4c81; }
        .marco-interno { position: absolute; top: 30px; left: 30px; right: 30px; bottom: 30px; border: 1px solid #c8a24a; }
        .contenido { position: absolute; top: 58px; left: 70px; right: 70px; text-align: center; }
        .universidad { font-size: 13px; letter-spacing: 2px; text-transform: uppercase; color: #4b5563; }
        .instituto { font-size: 21px; font-weight: bold; color: #0f4c81; margin-top: 6px; }
        .titulo { font-size: 40px; font-weight: bold; letter-spacing: 6px; color: #0f4c81; margin-top: 26px; }
        .linea { width: 180px; border-top: 2px solid #c8a24a; margin: 12px auto 0 auto; }
        .texto { font-size: 14px; margin-top: 20px; color: #374151; }
        .nombre { font-size: 30px; font-weight: bold; margin-top: 10px; color: #111827; }
        .programa { font-size: 20px; font-weight: bold; color: #0f4c81; margin-top: 8px; }
        .detalle { font-size: 12px; margin-top: 14px; color: #4b5563; line-height: 1.6; }
        .pie { position: absolute; left: 70px; right: 70px; bottom: 58px; }
        .pie table { width: 100%; border-collapse: collapse; }
        .pie td { vertical-align: bottom; font-size: 11px; color: #4b5563; }
        .firma { border-top: 1px solid #374151; padding-top: 5px; width: 230px; text-align: center; }
        .qr { text-align: right; }
        .qr img { width: 112px; height: 112px; }
        .codigo { font-size: 13px; font-weight: bold; color: #111827; letter-spacing: 1px; }
        .verificar { font-size: 9px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="marco"></div>
    <div class="marco-interno"></div>

    <div class="contenido">
        <div class="universidad">{{ $c['institucion']['universidad'] }}</div>
        <div class="instituto">{{ $c['institucion']['nombre'] }} · {{ $c['institucion']['sigla'] }}</div>

        <div class="titulo">CERTIFICADO</div>
        <div class="linea"></div>

        <div class="texto">Se otorga el presente certificado a:</div>
        <div class="nombre">{{ $c['estudiante'] }}</div>
        <div class="texto">con documento de identidad N.º {{ $c['documento'] }}, por haber aprobado satisfactoriamente el programa</div>
        <div class="programa">{{ $c['programa'] }}</div>

        <div class="detalle">
            Modalidad {{ ucfirst(strtolower($c['modalidad'])) }} · Carga horaria de {{ $c['horas_totales'] }} horas académicas · Gestión {{ $c['gestion'] }}
            @if ($c['promedio'] !== null)
                · Promedio final {{ number_format($c['promedio'], 2) }}
            @endif
            <br>
            {{ $c['institucion']['ciudad'] }}, {{ \Carbon\Carbon::parse($c['fecha_emision'])->locale('es')->translatedFormat('j \d\e F \d\e Y') }}
        </div>
    </div>

    <div class="pie">
        <table>
            <tr>
                <td style="width: 34%;">
                    <div class="firma">
                        {{ $c['autorizado_por'] ?? 'Dirección' }}<br>
                        <strong>Director del ICAP</strong>
                    </div>
                </td>
                <td style="width: 32%; text-align: center;">
                    <div class="codigo">{{ $c['codigo_unico'] }}</div>
                    <div class="verificar">Código único de verificación</div>
                </td>
                <td class="qr" style="width: 34%;">
                    <img src="{{ $qr }}" alt="QR de verificación"><br>
                    <span class="verificar">Verifique la autenticidad escaneando el QR<br>{{ $c['url_verificacion'] }}</span>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
