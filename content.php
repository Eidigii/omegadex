<?php
/* content.php (V6.5 - Complete, Corrected Image Path Generation) */
error_reporting(0); // As per your original setup

// YOUR ORIGINAL parseCustomFormat function
function parseCustomFormat($content) {
    //Convert single newlines to paragraph tags
    $content = preg_replace("/\n/", "</p><p>", $content);
    return "<p>" . $content . "</p>";
}

// Helper to check if content is likely already significantly HTML-structured
function isLikelyHtml($string) {
    if (empty($string)) return false;
    // Look for common block tags or multiple HTML tags.
    if (preg_match("/<(div|table|ul|ol|h[1-6]|section|article|aside|header|footer|nav|figure|blockquote|hr)[^>]*>/i", $string)) {
        return true;
    }
    if (substr_count($string, "<") > 3 && substr_count($string, ">") > 3) { // Heuristic: more than 3 tags
        return true;
    }
    return false;
}

function getContentFromFile($file_path_param) {
    // $file_path_param is expected to be like "Data/Folder/FileNoExt" from JS,
    // or could be a direct path if called by PHP itself.
    // We need to construct the full server path to the generated extensionless file.
    $decoded_path = urldecode($file_path_param);
    // Ensure path is treated as relative to this script's directory if not absolute
    if (strpos($decoded_path, __DIR__) !== 0 && !realpath($decoded_path)) {
        $full_server_path = realpath(__DIR__ . DIRECTORY_SEPARATOR . $decoded_path);
    } else {
        $full_server_path = realpath($decoded_path);
    }
    
    $mainDataDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'Data');

    if (!$full_server_path || 
        ($mainDataDir && strpos($full_server_path, $mainDataDir) !== 0) || 
        !file_exists($full_server_path) || 
        pathinfo($full_server_path, PATHINFO_EXTENSION) === 'txt') { // Should not load raw .txt
        return '<p class="error">Content file not found or invalid type: ' . htmlspecialchars($file_path_param) . '</p>';
    }

    $content_data = file_get_contents($full_server_path);
    return parseCustomFormat($content_data); 
}

function getDefaultContentForFolder($folder_param) {
    $folder_path_segment = urldecode($folder_param);
    // Construct path to the extensionless 'index' file, relative to Data/
    $path_inside_data = 'Data' . DIRECTORY_SEPARATOR . $folder_path_segment . DIRECTORY_SEPARATOR . 'index';
    $defaultFile_path = realpath(__DIR__ . DIRECTORY_SEPARATOR . $path_inside_data);
    
    $mainDataDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'Data');

    if (!$defaultFile_path || 
        ($mainDataDir && strpos($defaultFile_path, $mainDataDir) !== 0) || 
        !file_exists($defaultFile_path)) {
        
        if ($folder_path_segment !== '#1 Welcome') {
            return getDefaultContentForFolder('#1 Welcome'); 
        }
        return '<p class="error">Default content not found for: ' . htmlspecialchars($folder_path_segment) . '</p>';
    }

    $content_data = file_get_contents($defaultFile_path);
    
    // Specific case for Saddle Creator which might be raw HTML not needing parseCustomFormat
    if (strpos($defaultFile_path, DIRECTORY_SEPARATOR . '#14 Saddle Creator' . DIRECTORY_SEPARATOR) !== false) {
        return $content_data; 
    } else {
        return parseCustomFormat($content_data);
    }
}

function getImagesHtmlForFolder($folder_param) {
    $folder_path_segment = urldecode($folder_param); // e.g., "#8 Items/Basic Souls/Cosmic Soul"
    
    // Construct the absolute server path to the target folder within Data
    // __DIR__ is the directory of content.php (assumed to be in web root)
    $path_inside_data = 'Data' . DIRECTORY_SEPARATOR . $folder_path_segment;
    $folderPath_abs = realpath(__DIR__ . DIRECTORY_SEPARATOR . $path_inside_data); // Absolute server path to the image folder
    
    $imageTypes = ['png', 'jpg', 'jpeg', 'gif', 'webp']; 
    $mainDataDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'Data');

    if (!$folderPath_abs || ($mainDataDir && strpos($folderPath_abs, $mainDataDir) !== 0 && $folderPath_abs !== $mainDataDir) || !is_dir($folderPath_abs)) {
        // error_log("getImagesHtmlForFolder: Folder not found or invalid: " . $folder_path_segment . " (Resolved: " . ($folderPath_abs ? $folderPath_abs : 'false') . ")");
        return null; 
    }
    
    $files = scandir($folderPath_abs);
    if ($files === false) {
        // error_log("getImagesHtmlForFolder: scandir failed for: " . $folderPath_abs);
        return null;
    }

    $addontext_html = ''; 
    $image_tags_html = '';

    // Process additional.txt / additional (extensionless HTML)
    $prebuilt_additional_file = $folderPath_abs . DIRECTORY_SEPARATOR . 'additional';
    if (file_exists($prebuilt_additional_file)) { 
        $addontext_html = file_get_contents($prebuilt_additional_file);
    } elseif (file_exists($folderPath_abs . DIRECTORY_SEPARATOR . 'additional.txt')) { 
        $raw_addon_text = file_get_contents($folderPath_abs . DIRECTORY_SEPARATOR . 'additional.txt');
        if ($raw_addon_text !== false) {
             // Assuming isLikelyHtml is defined elsewhere in your content.php
             if (!function_exists('isLikelyHtml') || !isLikelyHtml($raw_addon_text)) { 
                $addontext_html = nl2br(htmlspecialchars($raw_addon_text), false); 
             } else { 
                $addontext_html = $raw_addon_text; 
             }
        }
    }
    
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT']); // Get document root once

    foreach ($files as $file_item) {
        $ext = strtolower(pathinfo($file_item, PATHINFO_EXTENSION));
        if (in_array($ext, $imageTypes)) {
            $fullImagePathOnServer = $folderPath_abs . DIRECTORY_SEPARATOR . $file_item;
            $image_src_for_web = '';

            if ($docRoot && strpos(realpath($fullImagePathOnServer), $docRoot) === 0) {
                // Make path relative to document root for web accessibility
                $image_src_for_web = str_replace($docRoot, '', realpath($fullImagePathOnServer));
                $image_src_for_web = str_replace(DIRECTORY_SEPARATOR, '/', $image_src_for_web); // Ensure forward slashes
                // Ensure it starts with a '/' if DOCUMENT_ROOT was directly replaced and path is not empty
                if (!empty($image_src_for_web) && strpos($image_src_for_web, '/') !== 0) {
                    $image_src_for_web = '/' . $image_src_for_web;
                }
            } else {
                // Fallback if not directly under document root (e.g. complex symlinks, different mount)
                // This constructs a path assuming Data is directly under where index.php is served from.
                $fallback_path = 'Data/' . $folder_path_segment . '/' . $file_item;
                $image_src_for_web = str_replace(DIRECTORY_SEPARATOR, '/', $fallback_path);
                $image_src_for_web = '/' . preg_replace('#/+#','/', $image_src_for_web); // Ensure leading slash and no double slashes
            }
            
            // URL encode parts of the path if they contain special characters, but keep '/'
            // This mimics your original $encodedPath = str_replace('%2F', '/', rawurlencode($relativePath));
            // A safer way is to encode each path segment individually if needed.
            // For simplicity and to match your original:
            $encoded_src = implode('/', array_map('rawurlencode', explode('/', ltrim($image_src_for_web, '/'))));
            if (strpos($image_src_for_web, '/') === 0) { // Re-add leading slash if it was there
                $encoded_src = '/' . $encoded_src;
            }


            $image_tags_html .= "<img src='" . htmlspecialchars($encoded_src, ENT_QUOTES, 'UTF-8') . "' alt='" . htmlspecialchars($file_item, ENT_QUOTES, 'UTF-8') . "' />";
        }
    }

    if (!empty($addontext_html) || !empty($image_tags_html)) {
        return (stripos($folder_path_segment, 'Items') !== false) ? $image_tags_html . $addontext_html : $addontext_html . $image_tags_html;
    } 
    return null;
}

if (!function_exists('isLikelyHtml')) {
    function isLikelyHtml($string) {
        if (empty($string)) return false;
        if (preg_match("/<(div|table|ul|ol|h[1-6]|section|article|aside|header|footer|nav|figure|blockquote|hr)[^>]*>/i", $string)) return true;
        if (substr_count($string, "<") > 3 && substr_count($string, ">") > 3) return true;
        return false;
    }
}

function getTableHtmlForFolder($folder_param) {
    $folder_path_segment = urldecode($folder_param);
    $path_inside_data = 'Data' . DIRECTORY_SEPARATOR . $folder_path_segment;
    $folderPath_abs = realpath(__DIR__ . DIRECTORY_SEPARATOR . $path_inside_data);
    
    $mainDataDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'Data');

    if (!$folderPath_abs || ($folderPath_abs !== $mainDataDir && strpos($folderPath_abs, $mainDataDir) !== 0) || !is_dir($folderPath_abs) ) {
        return null; 
    }
    
    $prebuilt_table_file = $folderPath_abs . DIRECTORY_SEPARATOR . 'table';
    if (file_exists($prebuilt_table_file)) {
        return file_get_contents($prebuilt_table_file);
    }

    if (file_exists($folderPath_abs . DIRECTORY_SEPARATOR . 'table.txt')) {
        $columns = []; $max_rows = 0; $column_data_arrays = [];
        
        foreach (glob($folderPath_abs . DIRECTORY_SEPARATOR . 'c*_*.txt') as $file_item) {
            if (preg_match('/c(\d+)_(.*)\.txt$/', basename($file_item), $matches)) {
                $col_index = $matches[1];
                $columns[$col_index] = [ 
                    'header' => str_replace('_', ' ', $matches[2]), 
                    'file' => $file_item 
                ];
                $lines = file($file_item, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $column_data_arrays[$col_index] = $lines ? $lines : [];
                if (count($column_data_arrays[$col_index]) > $max_rows) {
                    $max_rows = count($column_data_arrays[$col_index]);
                }
            }
        }
        if (empty($columns)) return null; 
        ksort($columns); 
        
        $tableHtml = '<div class="table-container"><table border="1"><thead><tr>'; 
        foreach ($columns as $column) { 
            $tableHtml .= '<th>' . htmlspecialchars($column['header']) . '</th>'; 
        }
        $tableHtml .= '</tr></thead><tbody>';
        for ($r = 0; $r < $max_rows; $r++) {
            $tableHtml .= '<tr>'; $counter = 0;
            foreach ($columns as $index => $column_meta) { 
                $cell_content = isset($column_data_arrays[$index][$r]) ? $column_data_arrays[$index][$r] : '';
                $style = ($counter > 0) ? ' style="text-align:center"' : '';
                $tableHtml .= "<td{$style}>" . htmlspecialchars($cell_content) . '</td>';
                $counter++;  
            } $tableHtml .= '</tr>';
        } 
        $tableHtml .= '</tbody></table></div>'; 
        return $tableHtml;
    }
    return null;
}

// --- Main Content Dispatch Logic ---
$output = '';
if (isset($_GET['file'])) {
    $output = getContentFromFile($_GET['file']);
} elseif (isset($_GET['folder'])) {
    $output = getImagesHtmlForFolder($_GET['folder']);
    if ($output === null) { 
        $output = getTableHtmlForFolder($_GET['folder']);
        if ($output === null) { 
            $output = getDefaultContentForFolder($_GET['folder']);
        }
    }
} else {
    $output = getDefaultContentForFolder('#1 Welcome');
}

echo $output;
?>