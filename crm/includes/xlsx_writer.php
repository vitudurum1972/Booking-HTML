<?php
/**
 * Minimaler XLSX-Writer ohne externe Abhängigkeiten.
 *
 * Funktioniert OHNE die ZipArchive-PHP-Erweiterung: das ZIP-Archiv wird
 * manuell mit pack() zusammengebaut, Komprimierung per gzdeflate() aus der
 * zlib-Erweiterung (ist auf jeder Synology-PHP-Installation verfügbar).
 * Falls ZipArchive doch vorhanden ist, wird es bevorzugt verwendet.
 *
 * Erzeugt eine valide .xlsx-Datei für Excel, LibreOffice, Numbers, Google Sheets.
 */

// ─── Hilfsfunktionen ─────────────────────────────────────────────────

/**
 * Wandelt eine 1-basierte Spaltenzahl in einen Excel-Spaltenbuchstaben um (1 → A, 27 → AA).
 */
function crm_xlsx_column_letter(int $col): string {
    $s = '';
    while ($col > 0) {
        $mod = ($col - 1) % 26;
        $s   = chr(65 + $mod) . $s;
        $col = (int)(($col - $mod) / 26);
    }
    return $s;
}

/**
 * Maskiert einen String für XML (inkl. Control-Characters entfernen).
 */
function crm_xlsx_escape(string $str): string {
    // Steuerzeichen entfernen, die Excel nicht akzeptiert
    $str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $str);
    return htmlspecialchars($str, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/**
 * Baut das Sheet-XML aus Header und Zeilen.
 * Style 1 = fetter, farbig hinterlegter Header.
 */
function crm_xlsx_build_sheet(array $headers, array $rows): string {
    $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

    $xml .= '<cols>';
    for ($i = 1, $n = count($headers); $i <= $n; $i++) {
        $xml .= '<col min="' . $i . '" max="' . $i . '" width="22" customWidth="1"/>';
    }
    $xml .= '</cols>';

    $xml .= '<sheetData>';

    // Header-Zeile
    $xml .= '<row r="1">';
    foreach ($headers as $idx => $h) {
        $col = crm_xlsx_column_letter($idx + 1);
        $xml .= '<c r="' . $col . '1" t="inlineStr" s="1"><is><t xml:space="preserve">' . crm_xlsx_escape((string)$h) . '</t></is></c>';
    }
    $xml .= '</row>';

    $rowNum = 2;
    foreach ($rows as $row) {
        $xml .= '<row r="' . $rowNum . '">';
        $cellIdx = 0;
        foreach ($row as $cell) {
            $col = crm_xlsx_column_letter(++$cellIdx);
            $val = $cell === null ? '' : (string)$cell;
            $xml .= '<c r="' . $col . $rowNum . '" t="inlineStr"><is><t xml:space="preserve">' . crm_xlsx_escape($val) . '</t></is></c>';
        }
        $xml .= '</row>';
        $rowNum++;
    }

    $xml .= '</sheetData>';
    if ($rows) {
        $lastCol = crm_xlsx_column_letter(count($headers));
        $lastRow = count($rows) + 1;
        $xml .= '<autoFilter ref="A1:' . $lastCol . $lastRow . '"/>';
    }
    $xml .= '</worksheet>';
    return $xml;
}

/**
 * Baut ein ZIP-Archiv manuell mit pack() zusammen, ohne ZipArchive-Erweiterung.
 * Nutzt DEFLATE-Komprimierung (gzdeflate), bei Problemen Fallback auf "stored".
 *
 * @param array<string,string> $files name => inhalt
 * @return string Binärinhalt des ZIP-Archivs
 */
function crm_xlsx_build_zip(array $files): string {
    $local   = '';
    $central = '';
    $offset  = 0;
    // DOS-Zeit: 1980-01-01 00:00:00 (vereinfachend, egal für Excel)
    $dosTime = 0x0000;
    $dosDate = 0x0021; // 1980-01-01

    foreach ($files as $name => $content) {
        $uncompressedSize = strlen($content);
        $crc = crc32($content);
        // PHPs crc32() kann auf 32-Bit-Systemen negative Werte liefern.
        // Für pack('V', ...) brauchen wir einen unsigned 32-Bit-Wert.
        if ($crc < 0) $crc += 0x100000000;

        // DEFLATE (raw, ohne zlib-Header)
        $compressed = function_exists('gzdeflate') ? @gzdeflate($content, 6) : false;
        if ($compressed === false) {
            // Fallback: unkomprimiert ("stored")
            $compressed     = $content;
            $compressedSize = $uncompressedSize;
            $method         = 0;
        } else {
            $compressedSize = strlen($compressed);
            $method         = 8; // DEFLATE
        }

        // Local File Header (30 Byte + Dateiname)
        $lfh  = pack('V', 0x04034b50); // Signatur
        $lfh .= pack('v', 20);          // version needed
        $lfh .= pack('v', 0);           // flags
        $lfh .= pack('v', $method);     // compression method
        $lfh .= pack('v', $dosTime);
        $lfh .= pack('v', $dosDate);
        $lfh .= pack('V', $crc);
        $lfh .= pack('V', $compressedSize);
        $lfh .= pack('V', $uncompressedSize);
        $lfh .= pack('v', strlen($name));
        $lfh .= pack('v', 0);           // extra field length
        $lfh .= $name;

        $local .= $lfh . $compressed;

        // Central Directory File Header (46 Byte + Dateiname)
        $cdh  = pack('V', 0x02014b50);
        $cdh .= pack('v', 20);          // version made by
        $cdh .= pack('v', 20);          // version needed
        $cdh .= pack('v', 0);           // flags
        $cdh .= pack('v', $method);
        $cdh .= pack('v', $dosTime);
        $cdh .= pack('v', $dosDate);
        $cdh .= pack('V', $crc);
        $cdh .= pack('V', $compressedSize);
        $cdh .= pack('V', $uncompressedSize);
        $cdh .= pack('v', strlen($name));
        $cdh .= pack('v', 0);           // extra field length
        $cdh .= pack('v', 0);           // file comment length
        $cdh .= pack('v', 0);           // disk number start
        $cdh .= pack('v', 0);           // internal file attributes
        $cdh .= pack('V', 0);           // external file attributes
        $cdh .= pack('V', $offset);     // relative offset of local header
        $cdh .= $name;

        $central .= $cdh;

        $offset += strlen($lfh) + $compressedSize;
    }

    $cdSize   = strlen($central);
    $cdOffset = $offset;

    // End Of Central Directory Record (22 Byte)
    $eocd  = pack('V', 0x06054b50);
    $eocd .= pack('v', 0);               // disk number
    $eocd .= pack('v', 0);               // disk with CD
    $eocd .= pack('v', count($files));   // CD records on this disk
    $eocd .= pack('v', count($files));   // total CD records
    $eocd .= pack('V', $cdSize);
    $eocd .= pack('V', $cdOffset);
    $eocd .= pack('v', 0);               // comment length

    return $local . $central . $eocd;
}

/**
 * Liefert das assoziative Array mit allen XLSX-Bestandteilen.
 */
function crm_xlsx_build_files(string $sheetName, array $headers, array $rows): array {
    return [
        '[Content_Types].xml' =>
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
                '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
                '<Default Extension="xml" ContentType="application/xml"/>' .
                '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
                '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
                '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
            '</Types>',

        '_rels/.rels' =>
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
                '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>',

        'xl/workbook.xml' =>
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
                '<sheets>' .
                    '<sheet name="' . crm_xlsx_escape($sheetName) . '" sheetId="1" r:id="rId1"/>' .
                '</sheets>' .
            '</workbook>',

        'xl/_rels/workbook.xml.rels' =>
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
                '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
                '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
            '</Relationships>',

        'xl/styles.xml' =>
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
                '<fonts count="2">' .
                    '<font><sz val="11"/><name val="Calibri"/></font>' .
                    '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>' .
                '</fonts>' .
                '<fills count="3">' .
                    '<fill><patternFill patternType="none"/></fill>' .
                    '<fill><patternFill patternType="gray125"/></fill>' .
                    '<fill><patternFill patternType="solid"><fgColor rgb="FF3A4BB0"/><bgColor indexed="64"/></patternFill></fill>' .
                '</fills>' .
                '<borders count="1"><border/></borders>' .
                '<cellStyleXfs count="1"><xf/></cellStyleXfs>' .
                '<cellXfs count="2">' .
                    '<xf fontId="0" fillId="0" borderId="0"/>' .
                    '<xf fontId="1" fillId="2" borderId="0" applyFont="1" applyFill="1"/>' .
                '</cellXfs>' .
            '</styleSheet>',

        'xl/worksheets/sheet1.xml' => crm_xlsx_build_sheet($headers, $rows),
    ];
}

/**
 * Liefert den XLSX-Binärinhalt zurück.
 * Nutzt ZipArchive falls verfügbar, sonst den manuellen ZIP-Writer.
 */
function crm_xlsx_binary(string $sheetName, array $headers, array $rows): string {
    $files = crm_xlsx_build_files($sheetName, $headers, $rows);

    // Bevorzugt ZipArchive, falls vorhanden
    if (class_exists('ZipArchive')) {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        @unlink($tmp);
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE) === true) {
            foreach ($files as $name => $content) {
                $zip->addFromString($name, $content);
            }
            $zip->close();
            $bin = file_get_contents($tmp);
            @unlink($tmp);
            if ($bin !== false) return $bin;
        }
        // falls ZipArchive fehlschlägt, durchreichen auf manuellen Writer
    }

    return crm_xlsx_build_zip($files);
}

/**
 * Erzeugt die XLSX-Datei und streamt sie direkt als Download zum Browser.
 * Ruft exit() auf.
 */
function crm_stream_xlsx(string $downloadName, string $sheetName, array $headers, array $rows): void {
    $binary = crm_xlsx_binary($sheetName, $headers, $rows);

    // Output-Buffering komplett beenden
    while (ob_get_level() > 0) { ob_end_clean(); }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . rawurlencode($downloadName) . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
    header('Content-Length: ' . strlen($binary));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    echo $binary;
    exit;
}
