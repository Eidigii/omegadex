<?php
/* navigation.php (V6 - Sets active class based on GET params) */

function readDirectoryForMainMenu_V6($dir_path_param) {
    $result = [];
    // Ensure dir_path_param is valid and a directory before scandir
    if (!is_dir($dir_path_param)) {
        // Optionally log an error or return empty if Data path is incorrect
        // error_log("Data path for main menu is invalid: " . $dir_path_param);
        return $result;
    }
    $files = scandir($dir_path_param);
    natsort($files); 

    foreach ($files as $file_item) {
        if ($file_item == '.' || $file_item == '..') continue;
        $filePath_item = $dir_path_param . DIRECTORY_SEPARATOR . $file_item;
        if (is_dir($filePath_item)) {
            // Key is the folder name, value is just a marker (empty array)
            $result[$file_item] = []; 
        } 
    }
    return $result;
}

// Path to the Data directory from the location of navigation.php (usually web root)
$dataPathForMainMenu_V6 = realpath(__DIR__ . '/Data'); 
$dataStructureForMainMenu_V6 = [];

if ($dataPathForMainMenu_V6) {
    $dataStructureForMainMenu_V6 = readDirectoryForMainMenu_V6($dataPathForMainMenu_V6);
}


// Determine the current active top-level folder based on GET parameters
$currentActiveTopLevelFolder_V6 = '';

// Prioritize 'folder' GET parameter
if (isset($_GET['folder'])) {
    $folderPath_V6 = trim($_GET['folder']);
    // The top-level folder is the first segment before any '/'
    // Ensure path uses forward slashes for explode, as URL params use /
    $pathSegments_V6 = explode('/', str_replace('\\', '/', $folderPath_V6));
    if (count($pathSegments_V6) > 0 && !empty($pathSegments_V6[0])) {
        $currentActiveTopLevelFolder_V6 = $pathSegments_V6[0];
    }
} 
// If 'folder' not set, check 'file' GET parameter
elseif (isset($_GET['file'])) {
    $filePath_V6 = trim($_GET['file']);
    // Expecting file path like "Data/TopLevelFolder/SubFolder/ActualFile"
    // Remove "Data/" prefix if it exists
    if (strpos($filePath_V6, 'Data/') === 0) {
        $filePath_V6 = substr($filePath_V6, 5); // Length of "Data/"
    }
    $pathSegments_V6 = explode('/', str_replace('\\', '/', $filePath_V6));
    // The top-level folder is the first segment
    if (count($pathSegments_V6) > 0 && !empty($pathSegments_V6[0])) {
        $currentActiveTopLevelFolder_V6 = $pathSegments_V6[0];
    }
}

// If after checking GET params, no specific folder is active, default to "#1 Welcome"
// This also covers the case where index.php is loaded with no parameters.
if (empty($currentActiveTopLevelFolder_V6)) {
    // Check if #1 Welcome exists as a directory to be safe
    // (This check relies on $dataStructureForMainMenu_V6 being populated)
    if (isset($dataStructureForMainMenu_V6['#1 Welcome'])) {
        $currentActiveTopLevelFolder_V6 = '#1 Welcome';
    } else if (!empty($dataStructureForMainMenu_V6)) {
        // Fallback to the very first item in the natsorted list if #1 Welcome isn't there
        // but other items exist. This is a more robust default.
        reset($dataStructureForMainMenu_V6); // Point to the first element
        $currentActiveTopLevelFolder_V6 = key($dataStructureForMainMenu_V6); // Get its key
    }
}


function generateMainMenu_V6($data_param, $activeFolderKey_param) {
    if (empty($data_param)) {
        echo "<li>No navigation items found.</li>";
        return;
    }

    foreach ($data_param as $key_item => $value_item) { 
        $displayKey_item = preg_replace('/^#\d+\s*/', '', $key_item); 
        
        if (is_array($value_item)) { // Confirms it's a directory from readDirectoryForMainMenu_V6
            $isActive_item = ($key_item == $activeFolderKey_param);
            $activeClass_item = $isActive_item ? ' active' : '';
            
            echo "<li class='main-folder{$activeClass_item}' data-folder='" . htmlspecialchars($key_item) . "'>" . htmlspecialchars($displayKey_item) . "</li>";
        }
    }
}

// Generate the main menu HTML
if ($dataPathForMainMenu_V6 && !empty($dataStructureForMainMenu_V6)) { // Check if path was valid and structure not empty
    generateMainMenu_V6($dataStructureForMainMenu_V6, $currentActiveTopLevelFolder_V6);
} else {
    // This message will appear if the Data directory is missing or unreadable at the expected path.
    echo "<li>Error: Main navigation data could not be loaded. Check Data directory.</li>";
}

?>