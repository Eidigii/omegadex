/* assets/js/app.js - Main Application Script Loader (V6.23) */

//console.log("APP.JS (V6.23): Script loaded. More defensive element finding.");

// Create a global application object
window.OMEGADEX_APP = {
    mainMenu: null, navContainer: null, contentElem: null, menuToggle: null, 
    navWrapper: null, 
    mainQueryInputMobile: null, desktopFooterQueryInput: null, 
    fetchSubMenuCallId: 0, isHighlighting: false, contentObserverInstance: null,
    contentRequestId: 0,
    networkTimeoutMs: 15000,
    maxFetchRetries: 1,
    contentCache: new Map(),
    subMenuCache: new Map(),
    adjustNavContainerWidth: () => console.warn("adjustNavContainerWidth not loaded"),
    escapeRegExp: (string) => { 
        if (typeof string !== 'string') return '';
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); 
    },
    highlightTextInNode: () => console.warn("highlightTextInNode not loaded"),
    clearSearchTermHighlighting: () => console.warn("clearSearchTermHighlighting not loaded"),
    applySearchTermHighlighting: () => console.warn("applySearchTermHighlighting not loaded"),
    initializeEggTable: () => console.warn("initializeEggTable not loaded"),
    fetchContent: async () => console.warn("fetchContent not loaded"),
    fetchSubMenu: async () => { console.warn("fetchSubMenu not loaded"); return null; }, 
    safeFetch: async () => { throw new Error("safeFetch not loaded"); },
    setCachedValue: () => console.warn("setCachedValue not loaded"),
    getCachedValue: () => null,
    handleSearchNavigation: async () => console.warn("handleSearchNavigation not loaded"),
    attachEventListeners: () => console.warn("attachEventListeners not loaded")
};

window.addEventListener('unhandledrejection', event => {
    console.error('APP.JS: Unhandled promise rejection:', event.reason, event);
});

OMEGADEX_APP.setCachedValue = (cacheMap, key, value, maxEntries = 120) => {
    if (!(cacheMap instanceof Map) || !key) return;
    if (cacheMap.has(key)) cacheMap.delete(key);
    cacheMap.set(key, value);
    if (cacheMap.size > maxEntries) {
        const oldestKey = cacheMap.keys().next().value;
        if (oldestKey !== undefined) cacheMap.delete(oldestKey);
    }
};

OMEGADEX_APP.getCachedValue = (cacheMap, key) => {
    if (!(cacheMap instanceof Map) || !key || !cacheMap.has(key)) return null;
    const value = cacheMap.get(key);
    cacheMap.delete(key);
    cacheMap.set(key, value);
    return value;
};

OMEGADEX_APP.safeFetch = async (url, options = {}, maxRetries = OMEGADEX_APP.maxFetchRetries) => {
    const finalRetries = Number.isFinite(maxRetries) ? Math.max(0, maxRetries) : 0;
    let attempt = 0;
    while (attempt <= finalRetries) {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), OMEGADEX_APP.networkTimeoutMs);
        try {
            const response = await fetch(url, { ...options, signal: controller.signal });
            clearTimeout(timeoutId);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            return response;
        } catch (error) {
            clearTimeout(timeoutId);
            if (attempt >= finalRetries) throw error;
            await new Promise(resolve => setTimeout(resolve, 350 * (attempt + 1)));
        }
        attempt += 1;
    }
    throw new Error('Unexpected fetch retry state');
};

document.addEventListener('DOMContentLoaded', () => {
    // console.log("APP.JS: DOMContentLoaded event fired.");

    // Populate global DOM element references - these might be null if not on the page
    OMEGADEX_APP.mainMenu = document.getElementById('main-menu');
    OMEGADEX_APP.navContainer = document.getElementById('nav-container'); 
    OMEGADEX_APP.contentElem = document.getElementById('content');
    OMEGADEX_APP.menuToggle = document.getElementById('menu-toggle'); 
    OMEGADEX_APP.navWrapper = document.getElementById('nav-wrapper');
    
    OMEGADEX_APP.mainQueryInputMobile = document.getElementById('main-query-input-mobile'); 
    OMEGADEX_APP.desktopFooterQueryInput = document.getElementById('desktop-footer-query-input'); 

    // Log if critical elements are missing for general navigation
    if (!OMEGADEX_APP.mainMenu) console.warn("APP.JS: mainMenu (ul#main-menu) NOT FOUND! Navigation might be impaired.");
    if (!OMEGADEX_APP.navWrapper) console.warn("APP.JS: navWrapper (div#nav-wrapper) NOT FOUND! Mobile navigation might be impaired.");
    if (!OMEGADEX_APP.contentElem) console.error("APP.JS: contentElem (div#content) NOT FOUND! Content display will fail.");


    // Mutation Observer Setup
    if (typeof OMEGADEX_APP.applySearchTermHighlighting === 'function' && 
        typeof OMEGADEX_APP.initializeEggTable === 'function' && 
        OMEGADEX_APP.contentElem) { // Check contentElem again
        OMEGADEX_APP.contentObserverInstance = new MutationObserver((mutationsList) => { 
            if (OMEGADEX_APP.isHighlighting) return; 
            for(const mutation of mutationsList) { 
                if (mutation.type === 'childList') { 
                    OMEGADEX_APP.applySearchTermHighlighting(); 
                    OMEGADEX_APP.initializeEggTable(); 
                    break; 
                } 
            } 
        });
        OMEGADEX_APP.contentObserverInstance.observe(OMEGADEX_APP.contentElem, { childList: true, subtree: true });
    } else {
        // console.warn("APP.JS: Could not attach ContentObserver. Some functions or contentElem missing.");
    }
    
    // Attach Main UI Event Listeners 
    if (typeof OMEGADEX_APP.attachEventListeners === 'function') {
        OMEGADEX_APP.attachEventListeners(); 
    } else {
        console.error("APP.JS: attachEventListeners function not found from modules.");
    }
    
    // Initial Page UI Setup Calls
    if (typeof OMEGADEX_APP.adjustNavContainerWidth === 'function') OMEGADEX_APP.adjustNavContainerWidth();
    
    if (OMEGADEX_APP.menuToggle && OMEGADEX_APP.navWrapper) {
        OMEGADEX_APP.menuToggle.addEventListener('click', () => OMEGADEX_APP.navWrapper.classList.toggle('open'));
    } // else: menuToggle might not be on all pages (e.g. search.php simplified header)

    if(OMEGADEX_APP.contentElem && OMEGADEX_APP.navWrapper) { 
        OMEGADEX_APP.contentElem.addEventListener('click', (e) => { 
            if (e.target.tagName !== 'A' && window.innerWidth <= 900 && OMEGADEX_APP.navWrapper.classList.contains('open')) { 
                OMEGADEX_APP.navWrapper.classList.remove('open'); 
            } 
        }); 
    }
    window.addEventListener('resize', () => { 
        if (window.innerWidth > 900 && OMEGADEX_APP.navWrapper && OMEGADEX_APP.navWrapper.classList.contains('open')) { 
            OMEGADEX_APP.navWrapper.classList.remove('open'); 
        } 
        if (typeof OMEGADEX_APP.adjustNavContainerWidth === 'function') OMEGADEX_APP.adjustNavContainerWidth(); 
    });
    
    if (typeof OMEGADEX_APP.initializeEggTable === 'function') OMEGADEX_APP.initializeEggTable(); 
    
    if (typeof OMEGADEX_APP.handleSearchNavigation === 'function') {
        OMEGADEX_APP.handleSearchNavigation(); 
    } else {
         console.error("APP.JS: handleSearchNavigation function not found for initial page logic.");
    }
    
    // console.log("APP.JS: DOMContentLoaded fully processed.");
});

window.addEventListener('pageshow', (event) => {
    // iOS Safari sometimes restores stale interaction state from bfcache.
    if (!event.persisted) return;
    setTimeout(() => {
        const hasCriticalUi = !!(OMEGADEX_APP.mainMenu && OMEGADEX_APP.contentElem);
        if (!hasCriticalUi) {
            window.location.reload();
            return;
        }
        if (typeof OMEGADEX_APP.handleSearchNavigation === 'function') {
            OMEGADEX_APP.handleSearchNavigation();
        }
    }, 0);
});