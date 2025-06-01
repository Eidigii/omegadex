<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OMEGADEX</title>
    <link rel="stylesheet" href="assets/styles.css?v=6.20.layout"> 
    <link rel="icon" type="image/x-icon" href="favicon.ico">
</head>
<body>
    <header>
        <div class="menu-toggle" id="menu-toggle">☰</div>
        <span class="site-title"><a href="index.php" style="text-decoration:none; color: inherit;"><?php echo htmlspecialchars("OMEGADEX"); ?></a></span>
        <?php $search_query_for_input_box = isset($_GET['search_query_display']) ? htmlspecialchars($_GET['search_query_display']) : ''; ?>
        <form id="site-search-form-header-mobile" action="search.php" method="GET" class="site-search mobile-only-search">
            <input type="search" id="main-query-input-mobile" name="query" placeholder="Search..." 
                   aria-label="Search query mobile" value="<?php echo $search_query_for_input_box; ?>" required>
            <button type="submit">Search</button>
        </form>
    </header>
    <div class="wrapper">
        <div class="nav-wrapper" id="nav-wrapper">
            <div class="nav-menu" id="main-menu-container">
                <ul id="main-menu">
                    <?php include 'navigation.php'; ?>
                </ul>
                <div class="nav-column-footer-search desktop-only-search">
                    <form id="site-search-form-desktop-navcolumn" action="search.php" method="GET" class="site-search">
                        <input type="search" id="desktop-footer-query-input" name="query" placeholder="Search OMEGADEX..." 
                               aria-label="Search query desktop" value="<?php echo $search_query_for_input_box; ?>" required>
                        <button type="submit">Search</button>
                    </form>
                </div>
            </div>
            <div id="nav-container"></div>
        </div>
        <div class="content" id="content"> 
            <?php include 'content.php'; ?>
        </div>
    </div>

    <script src="assets/js/app.js?v=6.20.layout"></script> 
    <script src="assets/js/modules/utils.js?v=6.20.layout"></script>
    <script src="assets/js/modules/highlighting.js?v=6.20.layout"></script>
    <script src="assets/js/modules/eggTable.js?v=6.20.layout"></script>
    <script src="assets/js/modules/navigationCore.js?v=6.20.layout"></script>
    <script src="assets/js/modules/eventListeners.js?v=6.20.layout"></script>
    <script src="assets/js/modules/init.js?v=6.20.layout"></script>
</body>
</html>