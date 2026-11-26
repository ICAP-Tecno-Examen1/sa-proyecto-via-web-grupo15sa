<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Libro de Excel (.xlsx) mínimo, sin dependencias: una hoja con título, encabezados
 * en negrita, filtros, encabezado fijo y una columna por dato. A diferencia del CSV,
 * Excel lo abre igual sin importar el separador de listas configurado en Windows.
 *
 * Tipos de columna: texto | fecha | numero | moneda | porcentaje.
 */
class LibroExcel
{
    /**
     * @param  array<int, array{clave: string, titulo: string, tipo?: string}>  $columnas
     * @param  iterable<array<string, mixed>>  $filas
     * @param  array<int, string>  $subtitulos  líneas bajo el título (filtros, fecha de generación…)
     */
    public static function generar(string $titulo, array $columnas, iterable $filas, array $subtitulos = []): string
    {
        $xml = fn ($texto) => htmlspecialchars((string) $texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $celdaTexto = fn (string $ref, $valor, int $estilo = 0) => '<c r="'.$ref.'" t="inlineStr"'.($estilo ? ' s="'.$estilo.'"' : '').'><is><t xml:space="preserve">'
            .$xml(self::limpiar($valor)).'</t></is></c>';

        $n = max(1, count($columnas));
        $ultima = self::columna($n - 1);
        $filasXml = [];
        $fila = 1;

        $filasXml[] = '<row r="'.$fila.'">'.$celdaTexto('A'.$fila, $titulo, 1).'</row>';
        foreach ($subtitulos as $linea) {
            $fila++;
            $filasXml[] = '<row r="'.$fila.'">'.$celdaTexto('A'.$fila, $linea, 2).'</row>';
        }

        $fila += 2;
        $filaEncabezado = $fila;
        $celdas = '';
        foreach (array_values($columnas) as $i => $columna) {
            $celdas .= $celdaTexto(self::columna($i).$fila, $columna['titulo'], 3);
        }
        $filasXml[] = '<row r="'.$fila.'">'.$celdas.'</row>';

        $anchos = array_map(fn ($c) => mb_strlen($c['titulo']), array_values($columnas));

        foreach ($filas as $datos) {
            $fila++;
            $celdas = '';

            foreach (array_values($columnas) as $i => $columna) {
                $ref = self::columna($i).$fila;
                $valor = $datos[$columna['clave']] ?? null;
                $tipo = $columna['tipo'] ?? 'texto';

                if ($valor === null || $valor === '') {
                    continue;
                }

                if (in_array($tipo, ['numero', 'moneda', 'porcentaje'], true) && is_numeric($valor)) {
                    $estilo = $tipo === 'numero' && floor((float) $valor) == $valor ? 5 : 4;
                    $celdas .= '<c r="'.$ref.'" s="'.$estilo.'"><v>'.(0 + $valor).'</v></c>';
                    $anchos[$i] = max($anchos[$i], strlen(number_format((float) $valor, 2)));
                } else {
                    $celdas .= $celdaTexto($ref, $valor, $tipo === 'fecha' ? 6 : 0);
                    $anchos[$i] = max($anchos[$i], mb_strlen(self::limpiar($valor)));
                }
            }

            $filasXml[] = '<row r="'.$fila.'">'.$celdas.'</row>';
        }

        $cols = '';
        foreach ($anchos as $i => $ancho) {
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.min(60, max(10, $ancho + 3)).'" customWidth="1"/>';
        }

        $hoja = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="'.$filaEncabezado.'" topLeftCell="A'.($filaEncabezado + 1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols>'.$cols.'</cols>'
            .'<sheetData>'.implode('', $filasXml).'</sheetData>'
            .'<autoFilter ref="A'.$filaEncabezado.':'.$ultima.max($filaEncabezado, $fila).'"/>'
            .'</worksheet>';

        return self::empaquetar($hoja, mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', '', $titulo) ?: 'Reporte', 0, 31), $filaEncabezado, $ultima, max($filaEncabezado, $fila));
    }

    /** Índice 0 → A, 25 → Z, 26 → AA… */
    private static function columna(int $indice): string
    {
        $letras = '';
        for ($i = $indice; $i >= 0; $i = intdiv($i, 26) - 1) {
            $letras = chr(65 + $i % 26).$letras;
        }

        return $letras;
    }

    /** Quita caracteres de control que invalidan el XML. */
    private static function limpiar(mixed $valor): string
    {
        $texto = is_bool($valor) ? ($valor ? 'Sí' : 'No') : (string) $valor;

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $texto) ?? '';
    }

    private static function empaquetar(string $hoja, string $nombreHoja, int $filaEncabezado, string $ultima, int $ultimaFila): string
    {
        $nombre = htmlspecialchars($nombreHoja, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $archivos = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                .'</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'</Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheets><sheet name="'.$nombre.'" sheetId="1" r:id="rId1"/></sheets>'
                .'<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\''.str_replace("'", "''", $nombre).'\'!$A$'.$filaEncabezado.':$'.$ultima.'$'.$ultimaFila.'</definedName></definedNames>'
                .'</workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                .'</Relationships>',
            // Estilos: 1 título · 2 subtítulo · 3 encabezado · 4 decimal · 5 entero · 6 fecha (texto centrado)
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                .'<fonts count="4"><font><sz val="11"/><name val="Calibri"/></font>'
                .'<font><b/><sz val="14"/><color rgb="FF051874"/><name val="Calibri"/></font>'
                .'<font><i/><sz val="10"/><color rgb="FF555555"/><name val="Calibri"/></font>'
                .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
                .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
                .'<fill><patternFill patternType="solid"><fgColor rgb="FF051874"/><bgColor indexed="64"/></patternFill></fill></fills>'
                .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
                .'<border><left style="thin"><color rgb="FFBFC5DD"/></left><right style="thin"><color rgb="FFBFC5DD"/></right><top style="thin"><color rgb="FFBFC5DD"/></top><bottom style="thin"><color rgb="FFBFC5DD"/></bottom><diagonal/></border></borders>'
                .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
                .'<cellXfs count="7">'
                .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
                .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
                .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
                .'<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
                .'<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
                .'<xf numFmtId="1" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
                .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center"/></xf>'
                .'</cellXfs>'
                .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
                .'</styleSheet>',
            'xl/worksheets/sheet1.xml' => $hoja,
        ];

        $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;

        if ($zip->open($ruta, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo generar el archivo de Excel.');
        }

        foreach ($archivos as $nombreArchivo => $contenido) {
            $zip->addFromString($nombreArchivo, $contenido);
        }

        $zip->close();
        $bytes = file_get_contents($ruta);
        @unlink($ruta);

        return $bytes;
    }
}
