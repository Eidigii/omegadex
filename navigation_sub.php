<?php
/* navigation_sub.php (V6.12 - Corrected Changelog Folder Name Condition) */
error_reporting(0); 

function readDirectorySubNav_V612($dir, $currentDirNameForSort = '') { // Renamed for version
    $result_map = []; 
    $result_list = []; 
    // CORRECTED FOLDER NAME CHECK:
    $is_changelog_dir_flag = (strtolower($currentDirNameForSort) == strtolower("#17 Changelog")); // Use #17

    if (!is_dir($dir)) { 
        // echo "<!-- DEBUG: V612 readDirectorySubNav - DIR NOT FOUND: $dir -->\n";
        return ($is_changelog_dir_flag ? [] : (object)[]); 
    }

    $raw_files = scandir($dir);
    if ($raw_files === false) { 
        // echo "<!-- DEBUG: V612 readDirectorySubNav - SCANDIR FAILED for $dir -->\n";
        return ($is_changelog_dir_flag ? [] : (object)[]); 
    }

    $files_to_process = [];
    foreach ($raw_files as $rf) {
        if ($rf == '.' || $rf == '..') continue;
        $files_to_process[] = $rf;
    }
    
    if ($is_changelog_dir_flag) {
        // echo "<!-- DEBUG V612: Changelog ('$currentDirNameForSort') - Files BEFORE sort: " . htmlspecialchars(json_encode($files_to_process)) . " -->\n";
        
        $dated_items = [];
        foreach ($files_to_process as $file_for_date_parse) {
            $filename_no_ext_dp = pathinfo($file_for_date_parse, PATHINFO_FILENAME);
            $dateTimeObj_dp = null;
            if (preg_match('/^(\d{2})-(\d{2})-(\d{2})$/', $filename_no_ext_dp, $matches_dp)) {
                $full_date_str_dp = "20" . $matches_dp[1] . "-" . $matches_dp[2] . "-" . $matches_dp[3];
                $dateTimeObj_dp = DateTime::createFromFormat('Y-m-d', $full_date_str_dp);
            }
            $dated_items[] = ['name' => $file_for_date_parse, 'date' => $dateTimeObj_dp]; // Removed original_path_debug for cleaner output
        }

        usort($dated_items, function($a, $b) {
            if ($a['date'] && $b['date']) { 
                if ($a['date'] == $b['date']) return strnatcasecmp($a['name'], $b['name']);
                return $b['date'] <=> $a['date']; 
            } elseif ($a['date']) { return -1; } 
            elseif ($b['date']) { return 1;  } 
            return strnatcasecmp($a['name'], $b['name']); 
        });

        $files_to_process = []; 
        foreach ($dated_items as $item_sorted) {
            $files_to_process[] = $item_sorted['name'];
        }
        // echo "<!-- DEBUG V612: Changelog ('$currentDirNameForSort') - Files AFTER sort (names only): " . htmlspecialchars(json_encode($files_to_process)) . " -->\n";
    } else { 
        natsort($files_to_process); 
        $files_to_process = array_values($files_to_process); 
    }

    foreach ($files_to_process as $file) {
        if ($file == 'additional.txt') continue;
        $filePath = $dir . DIRECTORY_SEPARATOR . $file;
        $fileExtension = strtolower(pathinfo($file, PATHINFO_EXTENSION)); 

        if ( $fileExtension === 'txt' || $file === 'index' || 
             preg_match('/\.(png|jpg|jpeg|gif)$/i', $file) || 
             (preg_match('/^c\d+_/', $file) && empty($fileExtension)) ||
             ($file === 'table' && empty($fileExtension)) ) {
            continue;
        }
        
        $projectRoot = realpath(__DIR__); 
        $projectRootWithSep = rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $relativePath = str_replace($projectRootWithSep, '', $filePath);
        $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

        if (is_dir($filePath)) {
            $result_map[$file] = readDirectorySubNav_V612($filePath, $file); 
        } else {
            if (empty($fileExtension)) { 
                if ($is_changelog_dir_flag) {
                    $result_list[] = ["name" => $file, "path" => $relativePath, "type" => "file"];
                } else {
                    $result_map[$file] = $relativePath; 
                }
            }
        }
    }
    
    if ($is_changelog_dir_flag) {
        // echo "<!-- DEBUG V612: Changelog ('$currentDirNameForSort') - Returning RESULT_LIST: " . htmlspecialchars(json_encode($result_list)) . " -->\n";
        return $result_list; 
    }
    return $result_map; 
}

// --- Main block (if isset($_GET['folder'])) ---
if (isset($_GET['folder'])) {
    $folder_param = urldecode($_GET['folder']);
    if (strpos($folder_param, '..') !== false) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(["error" => "Invalid folder path."]); exit; }

    $targetDirInData = 'Data' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $folder_param);
    $dataPath = realpath(__DIR__ . DIRECTORY_SEPARATOR . $targetDirInData);
    $mainDataDirForCheck = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'Data');

    if (!$dataPath || ($mainDataDirForCheck && strpos($dataPath, $mainDataDirForCheck) !== 0 && $dataPath !== $mainDataDirForCheck) || !is_dir($dataPath)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["error" => "Folder not found or invalid.", "requested" => $folder_param, "checked" => $targetDirInData, "resolved_path" => $dataPath ? $dataPath : 'false']);
        exit;
    }
    
    // Use the basename of the *requested and validated* folder segment for sort logic
    $currentDirNameForSortLogic = basename($folder_param_cleaned_if_used_or_folder_param_itself);
    // More robust:
    $path_parts_for_name = explode('/', str_replace('\\', '/', $folder_param));
    $currentDirNameForSortLogic = end($path_parts_for_name); // Get the last segment
    if (empty($currentDirNameForSortLogic) && ($folder_param === "" || $folder_param === ".")) {
        $currentDirNameForSortLogic = basename($mainDataDirForCheck); // Should be "Data"
    }


    // echo "<!-- DEBUG V612: Processing folder via GET: " . htmlspecialchars($folder_param) . ", Resolved to: " . htmlspecialchars($dataPath) . ", SortKey: " . htmlspecialchars($currentDirNameForSortLogic) . " -->\n";

    $subMenuData = readDirectorySubNav_V612($dataPath, $currentDirNameForSortLogic); 
    
    header('Content-Type: application/json; charset=utf-8');
    $jsonOutput = json_encode($subMenuData, JSON_UNESCAPED_UNICODE); 
    if ($jsonOutput === false) { 
        // echo "<!-- DEBUG V612: JSON Encoding Failed! Error: " . json_last_error_msg() . " -->\n";
        echo json_encode(["error" => "JSON encoding failed", "json_error_code" => json_last_error(), "json_error_msg" => json_last_error_msg()]); 
    } else { 
        echo $jsonOutput; 
    }
    exit;
} else { 
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["error" => "No folder parameter provided."]);
    exit;
}
?>