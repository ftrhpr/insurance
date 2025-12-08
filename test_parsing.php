<?php
$text = "სახ. ნომ AA123BC 507.40
MERCEDES-BENZ (BB456DE) 11,381.10";

$lines = explode("\n", $text);
$regexes = [
    '/Transfer from ([\w\s]+), Plate: ([\w\d]+), Amt: (\d+)/i',
    '/INSURANCE PAY \| ([\w\d]+) \| ([\w\s]+) \| (\d+)/i',
    '/User: ([\w\s]+) Car: ([\w\d]+) Sum: ([\w\d\.]+)/i',
    '/მანქანის ნომერი:\s*([A-Za-z0-9]+)\s*დამზღვევი:\s*([^,]+),\s*([\d\.]+)/i',
    '/სახ\.?\s*ნომ\s*([A-Za-z0-9]+)\s*([\d\.,]+)/i',
    '/([A-Z\s\-]+)\s*\(([A-Za-z0-9]+)\)\s*([\d\.,]+)/i'
];

foreach($lines as $line) {
    foreach($regexes as $r) {
        if(preg_match($r, $line, $m)) {
            if(strpos($r, 'Transfer from') !== false) { $name=$m[1]; $plate=$m[2]; $amount=$m[3]; }
            elseif(strpos($r, 'INSURANCE') !== false) { $plate=$m[1]; $name=$m[2]; $amount=$m[3]; }
            elseif(strpos($r, 'User:') !== false) { $name=$m[1]; $plate=$m[2]; $amount=$m[3]; }
            elseif(strpos($r, 'სახ') !== false) { $plate=$m[1]; $amount=$m[2]; $name='Ardi Customer'; }
            elseif(strpos($r, '(') !== false && strpos($r, ')') !== false) { $plate=$m[2]; $amount=$m[3]; $name='imedi L Customer'; }
            else { $plate=$m[1]; $name=$m[2]; $amount=$m[3]; }

            echo "Line: $line\nPlate: $plate, Name: $name, Amount: $amount\n\n";
            break;
        }
    }
}
?>