<?php
declare(strict_types=1);

final class PdfScanner {
    public static function scan(string $path, string $prefix=''): array {
        $text=self::extractText($path); $number=self::assetNumber($text,$prefix); $date=self::testDate($text);
        return ['asset_number'=>$number,'test_date'=>$date,'text_found'=>trim($text)!==''];
    }
    private static function extractText(string $path): string {
        $binary=(string)file_get_contents($path); if(substr($binary,0,4)!=='%PDF') return '';
        $tool=function_exists('shell_exec')?trim((string)shell_exec('command -v pdftotext 2>/dev/null')):'';
        if($tool!=='') { $command=escapeshellarg($tool).' -layout -nopgbrk '.escapeshellarg($path).' - 2>/dev/null'; $text=(string)shell_exec($command); if(trim($text)!=='') return $text; }
        $chunks=[$binary];
        if(preg_match_all('/stream\R(.*?)\Rendstream/s',$binary,$streams)) foreach($streams[1] as $stream){$decoded=@gzuncompress($stream);if($decoded===false)$decoded=@gzinflate($stream);if($decoded===false)$decoded=@gzdecode($stream);if($decoded!==false)$chunks[]=$decoded;}
        $text=''; foreach($chunks as $chunk) if(preg_match_all('/\((?:\\.|[^\\)])*\)/s',$chunk,$matches)) foreach($matches[0] as $item) $text.=' '.stripcslashes(substr($item,1,-1));
        return preg_replace('/\s+/',' ',$text)??$text;
    }
    private static function assetNumber(string $text,string $prefix): string {
        $prefix=strtoupper(preg_replace('/[^A-Za-z]/','',$prefix)??'');
        $patterns=[]; if($prefix!=='')$patterns[]='/\b('.preg_quote($prefix,'/').'[\s._-]*\d(?:[\s._-]*\d){2,7})\b/i';
        if($prefix==='')$patterns[]='/Customer\s+Asset\s+(?:Number|No\.?|#)?\s*[:#-]?\s*([A-Z]{1,6}[- ]?\d{3,8})\b/i';
        foreach($patterns as $pattern)if(preg_match($pattern,$text,$match))return strtoupper(preg_replace('/[^A-Z0-9]/i','',$match[1]??$match[0])??''); return '';
    }
    private static function testDate(string $text): string {
        $dateValue='\d{1,2}\s*[\/.-]\s*\d{1,2}\s*[\/.-]\s*\d{2,4}|\d{4}\s*-\s*\d{1,2}\s*-\s*\d{1,2}|\d{1,2}\s+(?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:tember)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)\s+\d{4}';
        $patterns=[
            '/(?:Test\s+Date|Date\s+of\s+Test|Date\s+Tested|Inspection\s+Date|Tested\s+on)[^\d]{0,120}('.$dateValue.')/i',
            '/\bDate\b[^\d]{0,120}('.$dateValue.')/i',
            '/\b('.$dateValue.')\b/i',
        ];
        $today=date('Y-m-d');
        foreach($patterns as $pattern){$valid=[];if(preg_match_all($pattern,$text,$matches))foreach($matches[1] as $value){$date=self::normaliseDate($value);if($date!==''&&$date<=$today)$valid[]=$date;}if($valid){rsort($valid,SORT_STRING);return $valid[0];}}
        return '';
    }
    private static function normaliseDate(string $value): string {
        $value=preg_replace('/\s*([\/.-])\s*/','$1',trim($value))??trim($value);
        foreach(['!d/m/Y','!d.m.Y','!d-m-Y','!Y-m-d','!d/m/y','!d.m.y','!d-m-y','!j F Y','!j M Y'] as $format){$date=DateTimeImmutable::createFromFormat($format,$value);$errors=DateTimeImmutable::getLastErrors();if($date&&($errors===false||($errors['warning_count']===0&&$errors['error_count']===0)))return $date->format('Y-m-d');} return '';
    }
}
