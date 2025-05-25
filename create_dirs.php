<?php

$dirs = [
    'src/Models',
    'src/Views',
    'src/Controllers',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "Successfully created directory: $dir\n";
        } else {
            echo "Failed to create directory: $dir\n";
        }
    } else {
        echo "Directory already exists: $dir\n";
    }
}

?>
