<?php
$fontFile = __DIR__ . '/resources/fonts/Cairo-Regular.ttf';
echo "Checking font: $fontFile\n";
echo "Size: " . filesize($fontFile) . " bytes\n";

// Read the font file to check its tables
$fh = fopen($fontFile, 'rb');
if (!$fh) {
    echo "Cannot open font file\n";
    exit;
}

// Read the offset table
fseek($fh, 4);
$numTables = unpack('n', fread($fh, 2))[1];
echo "Number of tables: $numTables\n";

// Read table directory
fseek($fh, 12);
$tables = [];
for ($i = 0; $i < $numTables; $i++) {
    $tag = fread($fh, 4);
    $checksum = unpack('N', fread($fh, 4))[1];
    $offset = unpack('N', fread($fh, 4))[1];
    $length = unpack('N', fread($fh, 4))[1];
    $tables[trim($tag)] = ['offset' => $offset, 'length' => $length];
}
echo "Tables found: " . implode(', ', array_keys($tables)) . "\n";
echo "Has GSUB: " . (isset($tables['GSUB']) ? 'YES' : 'NO') . "\n";
echo "Has GPOS: " . (isset($tables['GPOS']) ? 'YES' : 'NO') . "\n";
echo "Has GDEF: " . (isset($tables['GDEF']) ? 'YES' : 'NO') . "\n";

// Read the name table to get font family
if (isset($tables['name'])) {
    fseek($fh, $tables['name']['offset']);
    $nameData = fread($fh, $tables['name']['length']);
    echo "name table size: " . $tables['name']['length'] . " bytes\n";
}

fclose($fh);

echo "\nFont appears valid.\n";
