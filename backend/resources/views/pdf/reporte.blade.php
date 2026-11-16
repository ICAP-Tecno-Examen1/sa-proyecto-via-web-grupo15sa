<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $reporte['titulo'] }}</title>
    <style>
        @page { margin: 28px 30px 44px 30px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 16px; margin: 0; color: #0f4c81; }
        .institucion { font-size: 10px; color: #4b5563; margin-bottom: 4px; }
        .meta { font-size: 9px; color: #6b7280; margin: 4px 0 10px 0; }
        .resumen { margin-bottom: 10px; }
        .resumen span { display: inline-block; margin-right: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #0f4c81; color: #fff; text-align: left; padding: 5px 6px; font-size: 9px; }
        td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; }
        tr:nth-child(even) td { background: #f3f4f6; }
        .num { text-align: right; }
        .vacio { text-align: center; padding: 20px; color: #6b7280; }
        .pie { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 8px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <div class="pie">{{ $institucion['nombre'] }} · {{ $institucion['sigla'] }} — Generado el {{ now()->format('d/m/Y H:i') }} por {{ $usuario }}</div>

    <div class="institucion">{{ $institucion['universidad'] }} · {{ $institucion['nombre'] }}</div>
    <h1>{{ $reporte['titulo'] }}</h1>
    <div class="meta">
        @forelse ($reporte['filtros_aplicados'] as $etiqueta => $valor)
            {{ $etiqueta }}: <strong>{{ $valor }}</strong>@if (! $loop->last) · @endif
        @empty
            Sin filtros aplicados
        @endforelse
    </div>

    @if (! empty($reporte['resumen']))
        <div class="resumen">
            @foreach ($reporte['resumen'] as $item)
                <span>{{ $item['etiqueta'] }}: <strong>{{ $item['valor'] }}</strong></span>
            @endforeach
        </div>
    @endif

    <table>
        <thead>
            <tr>
                @foreach ($reporte['columnas'] as $columna)
                    <th class="{{ in_array($columna['tipo'] ?? 'texto', ['numero', 'moneda', 'porcentaje']) ? 'num' : '' }}">{{ $columna['titulo'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($reporte['filas'] as $fila)
                <tr>
                    @foreach ($reporte['columnas'] as $columna)
                        @php
                            $valor = $fila[$columna['clave']] ?? null;
                            $tipo = $columna['tipo'] ?? 'texto';
                        @endphp
                        <td class="{{ in_array($tipo, ['numero', 'moneda', 'porcentaje']) ? 'num' : '' }}">
                            @if ($valor === null || $valor === '')
                                —
                            @elseif ($tipo === 'moneda')
                                {{ number_format((float) $valor, 2) }}
                            @elseif ($tipo === 'porcentaje')
                                {{ number_format((float) $valor, 1) }}%
                            @else
                                {{ $valor }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td class="vacio" colspan="{{ count($reporte['columnas']) }}">No hay datos para los filtros seleccionados.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
