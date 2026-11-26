<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $c['es_titulo'] ? 'Título' : 'Certificado' }} {{ $c['codigo'] }}</title>
    {{--
        Formato oficial del certificado / título del ICAP (A4 vertical).
        Los textos, firmantes, firmas y escudos vienen de la plantilla editable
        (Certificación › Plantilla del certificado). El sello NO es digital: su
        espacio queda en blanco, entre las dos firmas, para estamparlo a mano.
    --}}
    <style>
        @font-face { font-family: 'Gotica'; font-style: normal; font-weight: normal; src: url('{{ $fuente }}') format('truetype'); }
        @page { margin: 0; }
        body { margin: 0; font-family: 'DejaVu Serif', serif; color: #1b1f3a; }

        /* Marco: doble filete azul, filete rojo y guarda punteada dorada */
        .marco-1 { position: absolute; top: 6mm; left: 6mm; right: 6mm; bottom: 6mm; border: 4px double #051874; }
        .marco-2 { position: absolute; top: 8.5mm; left: 8.5mm; right: 8.5mm; bottom: 8.5mm; border: 1.2px solid #d40414; }
        .marco-3 { position: absolute; top: 10.5mm; left: 10.5mm; right: 10.5mm; bottom: 10.5mm; border: 2px dotted #b38b2e; }
        .marco-4 { position: absolute; top: 12mm; left: 12mm; right: 12mm; bottom: 12mm; border: 0.6px solid #051874; }
        .esquina { position: absolute; font-family: 'DejaVu Sans', sans-serif; font-size: 15pt; color: #b38b2e; line-height: 1; }

        .agua { position: absolute; top: 120mm; left: 0; right: 0; text-align: center; font-family: 'DejaVu Sans', sans-serif; font-size: 64pt; font-weight: bold; color: #e7e9f2; transform: rotate(-35deg); }

        .numero { position: absolute; top: 14.5mm; right: 22mm; font-family: 'DejaVu Sans Mono', monospace; font-size: 13pt; font-weight: bold; color: #d40414; letter-spacing: 1px; }

        .contenido { position: absolute; top: 23mm; left: 20mm; right: 20mm; }
        table { border-collapse: collapse; width: 100%; }
        td { vertical-align: middle; }

        .cabecera td.logo { width: 30mm; text-align: center; }
        .cabecera img { max-width: 27mm; max-height: 30mm; }
        .gotica { font-family: 'Gotica', serif; }
        .cabecera .universidad { font-size: 15.5pt; color: #051874; text-align: center; line-height: 1.15; }
        .cabecera .instituto { font-size: 20pt; color: #051874; text-align: center; line-height: 1.2; margin-top: 1mm; }
        .filete { border-top: 1.2px solid #d40414; width: 70mm; margin: 2.5mm auto 0 auto; }

        .otorgamiento { font-size: 11pt; line-height: 1.6; text-align: justify; margin-top: 7mm; }

        .central td.lado { width: 34mm; text-align: center; }
        .qr { width: 29mm; height: 29mm; }
        .foto { width: 30mm; height: 38mm; border: 1px solid #051874; }
        .sin-foto { width: 30mm; height: 38mm; border: 1px dashed #8a90b0; font-family: 'DejaVu Sans', sans-serif; font-size: 7pt; color: #8a90b0; text-align: center; padding-top: 17mm; height: 21mm; margin: 0 auto; }
        .verificar { font-family: 'DejaVu Sans', sans-serif; font-size: 6.2pt; color: #4b5170; margin-top: 1mm; }
        .titulo { font-family: 'Gotica', serif; font-size: 40pt; color: #051874; text-align: center; line-height: 1.1; }
        .en { font-size: 13pt; text-align: center; margin-top: 4mm; }
        .en strong { font-family: 'DejaVu Sans', sans-serif; font-size: 13.5pt; color: #051874; letter-spacing: 0.5px; }

        .otorgado { margin-top: 9mm; font-size: 12pt; }
        .nombre { font-size: 19pt; font-weight: bold; font-style: italic; color: #051874; text-align: center; margin-top: 1.5mm; border-bottom: 0.6px solid #8a90b0; padding-bottom: 1mm; }
        .nacimiento { font-size: 10.5pt; text-align: center; margin-top: 3mm; }
        .cierre { font-size: 11pt; line-height: 1.6; text-align: justify; margin-top: 5mm; }
        .fecha { font-size: 11.5pt; font-style: italic; text-align: right; margin-top: 8mm; }

        .firmas { position: absolute; left: 20mm; right: 20mm; bottom: 37mm; }
        .firmas td { vertical-align: bottom; text-align: center; font-family: 'DejaVu Sans', sans-serif; }
        .firmas td.firma { width: 40%; }
        .firmas td.sello { width: 20%; height: 34mm; }
        .firmas img { max-width: 52mm; max-height: 20mm; }
        .linea { border-top: 0.8px solid #1b1f3a; margin: 0 3mm; padding-top: 1.2mm; }
        .firmante { font-size: 8.5pt; font-weight: bold; }
        .cargo { font-size: 8pt; color: #4b5170; }

        .pie { position: absolute; left: 22mm; right: 22mm; bottom: 16mm; text-align: center; }
        .pie .ley { font-size: 7.2pt; font-style: italic; color: #4b5170; line-height: 1.4; }
        .pie .lugar { font-family: 'DejaVu Sans', sans-serif; font-size: 8pt; font-weight: bold; color: #051874; margin-top: 1.5mm; letter-spacing: 1px; }
    </style>
</head>
<body>
    <div class="marco-1"></div>
    <div class="marco-2"></div>
    <div class="marco-3"></div>
    <div class="marco-4"></div>
    <div class="esquina" style="top: 12.5mm; left: 13mm;">&#10070;</div>
    <div class="esquina" style="top: 12.5mm; right: 13mm;">&#10070;</div>
    <div class="esquina" style="bottom: 12.5mm; left: 13mm;">&#10070;</div>
    <div class="esquina" style="bottom: 12.5mm; right: 13mm;">&#10070;</div>

    @if ($c['vista_previa'])
        <div class="agua">VISTA PREVIA</div>
    @endif

    <div class="numero">N° {{ $c['numero'] }}</div>

    <div class="contenido">
        <table class="cabecera">
            <tr>
                <td class="logo">@if ($logo_izquierdo)<img src="{{ $logo_izquierdo }}" alt="">@endif</td>
                <td>
                    <div class="gotica universidad">{{ $p->encabezado_1 }}</div>
                    <div class="gotica instituto">{{ $p->encabezado_2 }}</div>
                    <div class="filete"></div>
                </td>
                <td class="logo">@if ($logo_derecho)<img src="{{ $logo_derecho }}" alt="">@endif</td>
            </tr>
        </table>

        <div class="otorgamiento">{{ $c['otorgamiento'] }}</div>

        <table class="central" style="margin-top: 5mm;">
            <tr>
                <td class="lado">
                    <img class="qr" src="{{ $c['qr'] }}" alt="QR">
                    <div class="verificar">Verifique su autenticidad<br>{{ $c['codigo'] }}</div>
                </td>
                <td>
                    <div class="titulo" @unless ($c['es_titulo']) style="font-size: 30pt;" @endunless>{{ $c['titulo'] }}</div>
                    <div class="en">En: <strong>{{ $c['en'] }}</strong></div>
                </td>
                <td class="lado">
                    @if ($p->mostrar_foto)
                        @if ($c['foto'])
                            <img class="foto" src="{{ $c['foto'] }}" alt="Fotografía">
                        @else
                            <div class="sin-foto">Fotografía 4x4</div>
                        @endif
                    @endif
                </td>
            </tr>
        </table>

        <div class="otorgado">Otorgado a:</div>
        <div class="nombre">{{ $c['estudiante'] }}</div>
        <div class="nacimiento">{{ $c['nacimiento'] }}</div>

        <div class="cierre">{{ $c['cierre'] }}</div>

        <div class="fecha">{{ $c['fecha_lugar'] }}</div>
    </div>

    <div class="firmas">
    <table>
        <tr>
            <td class="firma">
                @if ($firma_1)<img src="{{ $firma_1 }}" alt="Firma">@endif
                <div class="linea">
                    @if ($p->firmante_1_nombre)<div class="firmante">{{ $p->firmante_1_nombre }}</div>@endif
                    <div class="cargo">{{ $p->firmante_1_cargo }}</div>
                </div>
            </td>
            {{-- Espacio del sello (se estampa a mano) --}}
            <td class="sello"></td>
            <td class="firma">
                @if ($firma_2)<img src="{{ $firma_2 }}" alt="Firma">@endif
                <div class="linea">
                    @if ($p->firmante_2_nombre)<div class="firmante">{{ $p->firmante_2_nombre }}</div>@endif
                    <div class="cargo">{{ $p->firmante_2_cargo }}</div>
                </div>
            </td>
        </tr>
    </table>
    </div>

    <div class="pie">
        <div class="ley">{{ $p->texto_pie }}</div>
        <div class="lugar">{{ $p->lugar_pie }}</div>
    </div>
</body>
</html>
