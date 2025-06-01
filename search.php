<?php
// search.php (V6.7 - Header Matches Mobile index.php)
$site_title = "OMEGADEX"; 
$raw_query = isset($_GET['query']) ? trim($_GET['query']) : '';
$display_query = htmlspecialchars($raw_query);
$highlight_query_param = $raw_query; 
function generate_search_snippet($content, $term, $length = 200) { $text_content = strip_tags($content); $pos = -1; if (!empty($term)) { $pos = stripos($text_content, $term); } if ($pos !== false && $pos > -1) { $start = max(0, $pos - (int)($length / 3)); $snippet_text = mb_substr($text_content, $start, $length, 'UTF-8'); if ($start > 0) $snippet_text = "..." . $snippet_text; if (mb_strlen($text_content, 'UTF-8') > ($start + $length)) $snippet_text .= "..."; } else { $snippet_text = mb_substr($text_content, 0, $length, 'UTF-8'); if (mb_strlen($text_content, 'UTF-8') > $length) $snippet_text .= "..."; } return htmlspecialchars($snippet_text); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"> <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results for "<?php echo $display_query; ?>" - <?php echo htmlspecialchars($site_title); ?></title>
    <link rel="stylesheet" href="assets/styles.css?v=6.21.css"> 
    <link rel="icon" type="image/x-icon" href="favicon.ico">
</head>
<body>
    <header>
        <span class="site-title"><a href="index.php" style="text-decoration:none; color: inherit;"><?php echo htmlspecialchars($site_title); ?></a></span>
        <form id="site-search-form-header-mobile" action="search.php" method="GET" class="site-search mobile-only-search">
            <input type="search" id="search-page-query-input" name="query" placeholder="Search..." 
                   aria-label="Search query" value="<?php echo $display_query; ?>" required>
            <button type="submit">Search</button>
        </form>
    </header>
    <div class="wrapper">
        <div class="nav-wrapper" id="nav-wrapper-search" style="display: none;"> 
             <div class="nav-menu" id="main-menu-container-search"><ul id="main-menu-search"><li><a href="index.php" style="display:block; padding: 10px 15px; color:white; text-decoration:none;">« Back to Main Site</a></li></ul></div>
        </div>
        <div class="content search-results-page" id="search-content-area">
            <h2>Search Results</h2>
            <?php if (!empty($raw_query)): ?>
                <p class="search-query-display">Results for: <strong><?php echo $display_query; ?></strong></p>
                <?php
                $index_file_path = 'search_index.json'; $all_results = [];
                if (file_exists($index_file_path)) { $json_data = file_get_contents($index_file_path); $index_items = json_decode($json_data, true); if (is_array($index_items)) { foreach ($index_items as $item) { $title_match = isset($item['title']) && stripos($item['title'], $raw_query) !== false; $content_match = isset($item['content_source_text']) && stripos($item['content_source_text'], $raw_query) !== false; if ($title_match || $content_match) { $all_results[] = $item; } } } else { echo "<p class='error'>Error: Could not decode search index.</p>"; } } else { echo "<p class='error'>Error: Search index file (`{$index_file_path}`) not found.</p>"; }
                $changelog_results = []; $other_results = [];
                if (!empty($all_results)) { foreach ($all_results as $result) { $is_changelog = (isset($result['nav_path']) && stripos($result['nav_path'], 'Changelog') !== false) || (isset($result['title']) && stripos($result['title'], 'Changelog') !== false); if ($is_changelog) { $changelog_results[] = $result; } else { $other_results[] = $result; } } }
                $sorted_results = array_merge($other_results, $changelog_results);

                if (!empty($sorted_results)): ?>
                    <ul class="search-results-list">
                        <?php foreach ($sorted_results as $result): ?>
                            <?php
                                $page_param_val = $result['page_param'] ?? ''; $nav_path_val = $result['nav_path'] ?? ''; 
                                $item_type = $result['type'] ?? 'folder_index'; $url_query_params = [];
                                if ($item_type === 'single_file') { $url_query_params['file'] = $page_param_val; } 
                                else { $url_query_params['folder'] = $page_param_val; }
                                $url_query_params['navpath'] = $nav_path_val; 
                                $url_query_params['highlight'] = $highlight_query_param;
                                $url_query_params['search_query_display'] = $raw_query; 
                                $url = "index.php?" . http_build_query($url_query_params);
                                $breadcrumb_path_display = $nav_path_val; 
                                $breadcrumb_path_display = str_replace('/', ' » ', htmlspecialchars($breadcrumb_path_display));
                                if (empty(trim($result['nav_path'] ?? ''))) $breadcrumb_path_display = htmlspecialchars($result['title'] ?? 'Home');
                            ?>
                            <li>
                                <h3><a href="<?php echo htmlspecialchars($url); ?>"><?php echo htmlspecialchars($result['title'] ?? 'Untitled'); ?></a></h3>
                                <p class="result-breadcrumb">Location: <?php echo $breadcrumb_path_display; ?></p>
                                <?php if (isset($result['content_source_text']) && !empty($result['content_source_text'])): ?>
                                    <p class="snippet"><?php echo generate_search_snippet($result['content_source_text'], $raw_query); ?></p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: echo "<p>No results found for \"" . $display_query . "\".</p>"; endif; ?>
            <?php else: echo "<p>Please enter a search term.</p>"; endif; ?>
        </div>
    </div>
    <footer><span>© <?php echo date("Y"); ?> <?php echo htmlspecialchars($site_title); ?></span></footer>
    <script> 
        const menuToggleS = document.getElementById('menu-toggle-search-page');
        const navWrapperS = document.getElementById('nav-wrapper-search');
        if (menuToggleS && navWrapperS) { menuToggleS.addEventListener('click', () => navWrapperS.classList.toggle('open')); }
        const searchPageQueryInput = document.getElementById('search-page-query-input');
        if (searchPageQueryInput) { searchPageQueryInput.addEventListener('search', function() { if (!this.value) { /* Redirect or clear client-side if needed */ } });}
    </script>
</body>
</html>