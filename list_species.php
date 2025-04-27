<?php

// Set the directory path you want to search in
$directoryPath = 'Data/#5 Dinos/';

// Define the pattern to match all .txt files
$pattern = '*.txt';

// Use glob to get an array of .txt files in the directory
$txtFiles = glob($directoryPath . $pattern);

// Initialize an array to store the data for JSON conversion
$resultArray = [];

// Check if any .txt files were found
if ($txtFiles) {
    foreach ($txtFiles as $file) {
        // Skip the index.txt file if present
        if (basename($file) === "index.txt") {
            continue;
        }

        // Open and read the file content
        $content = file_get_contents($file);

        // Use regular expressions to extract the name and rarity group number
        preg_match('/Name:\s*(.+)/', $content, $nameMatches);
        preg_match('/Rarity Group Number:\s*([\d.]+)/', $content, $rarityMatches);

        // Check if we've got both matches; continue to the next file otherwise
        if (isset($nameMatches[1]) && isset($rarityMatches[1])) {
            $name = trim($nameMatches[1]);
            $rarity = trim($rarityMatches[1]);

            // Add the data to the result array
            $resultArray[] = [
                'Name' => $name,
                'Rarity' => $rarity
            ];
        }
    }

    // Encode the result array to JSON
    echo json_encode($resultArray, JSON_PRETTY_PRINT);
} else {
    echo "No .txt files found in the directory.";
}
?>