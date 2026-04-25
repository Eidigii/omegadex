/* assets/js/modules/navigationCore.js (V6.23 - Handle Changelog Array Data, Complete) */
//console.log("JS: navigationCore.js (V6.23) loaded.");

if (!window.OMEGADEX_APP) window.OMEGADEX_APP = {};

OMEGADEX_APP.isChangelogPath = (path) => {
    const normalized = String(path || '').replace(/\\/g, '/').toLowerCase();
    return normalized.includes('#18 changelog/');
};

OMEGADEX_APP.getChangelogDateFromPath = (path) => {
    const normalized = String(path || '').replace(/\\/g, '/');
    const base = normalized.split('/').pop() || '';
    const dateToken = base.replace(/\.txt$/i, '');
    const match = /^(\d{2})-(\d{2})-(\d{2})$/.exec(dateToken);
    if (!match) return null;
    const year = 2000 + Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);
    const date = new Date(year, month - 1, day);
    return Number.isNaN(date.getTime()) ? null : date;
};

OMEGADEX_APP.formatDateForUserLocale = (dateObj) => {
    if (!(dateObj instanceof Date) || Number.isNaN(dateObj.getTime())) return '';
    const browserLocales = (Array.isArray(navigator.languages) && navigator.languages.length > 0)
        ? navigator.languages
        : [navigator.language || undefined];
    let preferredLocale = browserLocales.find(loc => typeof loc === 'string' && loc.includes('-')) || browserLocales[0];

    const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
    const localeIsGenericEnglish = typeof preferredLocale === 'string' && /^en(?:-|$)/i.test(preferredLocale);

    // Some environments default to en-US even when the user region is Europe.
    // In that case, use a day-first locale to align with regional system expectations.
    if (localeIsGenericEnglish && /^Europe\//i.test(timeZone)) {
        preferredLocale = 'en-GB';
    }

    return new Intl.DateTimeFormat(preferredLocale, {
        day: 'numeric',
        month: 'numeric',
        year: 'numeric'
    }).format(dateObj);
};

OMEGADEX_APP.parseFlexibleDateToken = (token) => {
    const raw = String(token || '').trim();
    const m = raw.match(/^(\d{1,4})[\/.\-](\d{1,2})[\/.\-](\d{1,4})$/);
    if (!m) return null;

    let a = Number(m[1]);
    let b = Number(m[2]);
    let c = Number(m[3]);
    let year;
    let month;
    let day;

    if (m[1].length === 4) {
        year = a;
        month = b;
        day = c;
    } else {
        year = m[3].length === 2 ? (2000 + c) : c;
        // Infer order: choose unambiguous first; fallback to day-first.
        if (a > 12 && b <= 12) {
            day = a;
            month = b;
        } else if (b > 12 && a <= 12) {
            month = a;
            day = b;
        } else {
            day = a;
            month = b;
        }
    }

    if (!Number.isInteger(year) || year < 1900 || year > 2999) return null;
    if (!Number.isInteger(month) || month < 1 || month > 12) return null;
    if (!Number.isInteger(day) || day < 1 || day > 31) return null;

    const date = new Date(year, month - 1, day);
    if (Number.isNaN(date.getTime())) return null;
    if (date.getFullYear() !== year || (date.getMonth() + 1) !== month || date.getDate() !== day) return null;
    return date;
};

OMEGADEX_APP.formatChangelogNavLabel = (rawName) => {
    const token = String(rawName || '').replace(/\.txt$/i, '');
    const match = /^(\d{2})-(\d{2})-(\d{2})$/.exec(token);
    if (!match) return token;
    const date = new Date(2000 + Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    if (Number.isNaN(date.getTime())) return token;
    return OMEGADEX_APP.formatDateForUserLocale(date);
};

OMEGADEX_APP.localizeChangelogContentDate = (html, path) => {
    if (!OMEGADEX_APP.isChangelogPath(path)) return html;
    const date = OMEGADEX_APP.getChangelogDateFromPath(path);
    if (!date) return html;
    const localized = OMEGADEX_APP.formatDateForUserLocale(date);
    const changelogHeadingPattern = /(Ark\s+Omega(?:\s+[A-Za-z0-9'-]+)*\s+)(\d{1,2}[\/.\-]\d{1,2}(?:[\/.\-]\d{2,4})?)(\s+(?:[A-Za-z0-9'-]+\s+)*Patch\s+Notes:?)/gi;
    // Normalize changelog headings like:
    // "Ark Omega 5/14 Patch Notes:" or "Ark Omega Ascended 5/14 Patch Notes:"
    return String(html).replace(changelogHeadingPattern, `$1${localized}$3`);
};

OMEGADEX_APP.localizeWelcomeUpdatedDate = (html, path, type = 'folder') => {
    if (type !== 'folder') return html;
    const normalizedPath = String(path || '').replace(/\\/g, '/').toLowerCase();
    const isWelcome = normalizedPath === '#1 welcome'
        || normalizedPath.endsWith('/#1 welcome')
        || normalizedPath.endsWith('/#1 welcome/index');
    if (!isWelcome) return html;

    return String(html).replace(
        /(updated\s+)(\d{1,4}[\/.\-]\d{1,2}[\/.\-]\d{1,4})/i,
        (full, prefix, dateToken) => {
            const parsed = OMEGADEX_APP.parseFlexibleDateToken(dateToken);
            if (!parsed) return full;
            return `${prefix}${OMEGADEX_APP.formatDateForUserLocale(parsed)}`;
        }
    );
};

OMEGADEX_APP.fetchContent = async (path, type = 'folder') => {
    const logPrefix = `JS:NavCore: fetchContent (Path: "${path}", Type: "${type}")`;
    if (!OMEGADEX_APP.contentElem) { 
        console.error(`${logPrefix} contentElem is null!`); 
        return; 
    }
    const requestId = ++OMEGADEX_APP.contentRequestId;
    const queryPath = path.replace(/\\/g, '/');
    const cacheKey = `${type}:${queryPath}`;
    try {
        let html = OMEGADEX_APP.getCachedValue(OMEGADEX_APP.contentCache, cacheKey);
        if (html === null) {
            const response = await OMEGADEX_APP.safeFetch(`content.php?${type}=${encodeURIComponent(queryPath)}`);
            html = await response.text();
            if (type === 'file') {
                html = OMEGADEX_APP.localizeChangelogContentDate(html, queryPath);
            }
            html = OMEGADEX_APP.localizeWelcomeUpdatedDate(html, queryPath, type);
            OMEGADEX_APP.setCachedValue(OMEGADEX_APP.contentCache, cacheKey, html, 100);
        }
        if (requestId !== OMEGADEX_APP.contentRequestId) return;
        OMEGADEX_APP.contentElem.innerHTML = html;
        const scripts = Array.from(OMEGADEX_APP.contentElem.getElementsByTagName('script'));
        scripts.forEach(oldScript => { 
            const newScript = document.createElement('script'); 
            if (oldScript.src) newScript.src = oldScript.src; 
            else newScript.textContent = oldScript.textContent; 
            ['type', 'async', 'defer'].forEach(attr => { 
                if (oldScript.hasAttribute(attr)) newScript.setAttribute(attr, oldScript.getAttribute(attr)); 
            }); 
            oldScript.parentNode.replaceChild(newScript, oldScript); 
        });
    } catch (error) { 
        console.error(`${logPrefix} CATCH BLOCK:`, error); 
        if (requestId !== OMEGADEX_APP.contentRequestId) return;
        OMEGADEX_APP.contentElem.innerHTML = `<p class="error">Error loading content for ${path}.</p>`; 
    }
};

OMEGADEX_APP.fetchSubMenu = async (folder, level, fullNavPathToActivate = [], parentLiElement = null) => {
    const currentCallId = ++OMEGADEX_APP.fetchSubMenuCallId; 
    const logPrefix = `JS:NavCore: fetchSubMenu (ID:${currentCallId}, L:${level}, F:"${folder ? folder.split('/').pop() : 'root'}"`;
    const isMobileView = window.innerWidth <= 900;

    // Optional: More verbose logging for debugging specific calls
    // if (folder && folder.toLowerCase().includes("changelog") || (fullNavPathToActivate && fullNavPathToActivate.join('/').toLowerCase().includes("changelog"))) {
    //    console.log(`${logPrefix}) CHANGELOG START. Path:`, JSON.stringify(fullNavPathToActivate), "ParentLI:", parentLiElement ? parentLiElement.textContent.trim() : "N/A");
    // }


    try {
        const folderForFetch = folder ? folder.replace(/\\/g, '/') : "";
        if (!folderForFetch && folder !== "" && folder !== "#1 Welcome") { 
            console.error(`${logPrefix}) folderForFetch is invalid. Original: "${folder}"`); 
            return null;
        }
        
        const cacheKey = `${folderForFetch}|${level}|${isMobileView ? 'm' : 'd'}`;
        let data = OMEGADEX_APP.getCachedValue(OMEGADEX_APP.subMenuCache, cacheKey);
        if (data === null) {
            const response = await OMEGADEX_APP.safeFetch(`navigation_sub.php?folder=${encodeURIComponent(folderForFetch)}`);
            data = await response.json(); 
            OMEGADEX_APP.setCachedValue(OMEGADEX_APP.subMenuCache, cacheKey, data, 180);
        }
        if (currentCallId !== OMEGADEX_APP.fetchSubMenuCallId) return null;
        if (data && typeof data === 'object' && !Array.isArray(data) && data.error) {
            console.error(`${logPrefix}) Server returned error: ${data.error}`);
            return null;
        }
        
        if (!isMobileView && OMEGADEX_APP.navContainer) { Array.from(OMEGADEX_APP.navContainer.children).slice(level).forEach(subMenu => subMenu.remove()); }
        if (isMobileView && parentLiElement) { const existingSubMenu = parentLiElement.nextElementSibling; if (existingSubMenu && existingSubMenu.classList.contains('mobile-submenu')) existingSubMenu.remove(); }

        const isDataArray = Array.isArray(data);
        const itemsToIterate = isDataArray ? data : Object.keys(data); 

        if (itemsToIterate.length > 0) {
            const ulElem = document.createElement('ul'); 
            ulElem.id = `sub-menu-l${level}-f-${folderForFetch.replace(/[^a-zA-Z0-9-_]/g, '')}`;
            if (isMobileView) ulElem.classList.add('mobile-submenu');

            let itemInPathToProcessFurther = null; 
            const targetSegmentForThisLevel = (fullNavPathToActivate && fullNavPathToActivate.length > level + 1) ? fullNavPathToActivate[level + 1] : null;

            itemsToIterate.forEach((itemOrKey, index_debug) => { 
                let key_as_filename; 
                let item_data_value;     
                let item_type_hint = 'folder'; 

                if (isDataArray) { 
                    key_as_filename = itemOrKey.name;
                    item_data_value = itemOrKey.path; 
                    item_type_hint = itemOrKey.type || 'file'; 
                    // if (folderForFetch.toLowerCase().includes("changelog")) {
                    //     console.log(`${logPrefix} Changelog Array Item [${index_debug}]: name="${key_as_filename}"`);
                    // }
                } else { 
                    key_as_filename = itemOrKey; 
                    item_data_value = data[key_as_filename]; 
                    if (typeof item_data_value === 'string') item_type_hint = 'file';
                }

                const rawDisplayKey = key_as_filename.replace(/^#\d+\s*/, '').replace(/\.txt$/, '');
                const displayKey = OMEGADEX_APP.isChangelogPath(`${folderForFetch}/${key_as_filename}`)
                    ? OMEGADEX_APP.formatChangelogNavLabel(rawDisplayKey)
                    : rawDisplayKey;
                const li = document.createElement('li'); 
                li.textContent = displayKey;
                const currentItemCumulativePath = `${folderForFetch}/${key_as_filename}`.replace(/\\/g, '/');
                const itemActualLevel = level + 1; 
                li.setAttribute('data-level', String(itemActualLevel)); 

                if (item_type_hint === 'folder') { 
                    li.className = 'folder'; 
                    li.setAttribute('data-folder', currentItemCumulativePath);
                    if (targetSegmentForThisLevel && key_as_filename === targetSegmentForThisLevel) itemInPathToProcessFurther = li;
                } else { 
                    li.className = 'file'; 
                    let filePathForDataAttr = item_data_value.replace(/\\/g, '/').replace(/\.txt$/i, '');
                    li.setAttribute('data-file', encodeURIComponent(filePathForDataAttr));
                    const fileKeyNoExt = key_as_filename.replace(/\.txt$/i, ''); 
                    if (targetSegmentForThisLevel && fileKeyNoExt === targetSegmentForThisLevel) itemInPathToProcessFurther = li;
                }
                if (isMobileView) { li.style.paddingLeft = `${15 + (itemActualLevel * 15)}px`; } 
                ulElem.appendChild(li);
            });
            
            if (isMobileView && parentLiElement) { parentLiElement.after(ulElem); } 
            else if (!isMobileView && OMEGADEX_APP.navContainer) { const subMenuContainer = document.createElement('div'); subMenuContainer.className = 'nav-menu'; subMenuContainer.appendChild(ulElem); OMEGADEX_APP.navContainer.appendChild(subMenuContainer); }
            
            if (!isMobileView) OMEGADEX_APP.adjustNavContainerWidth();

            if (itemInPathToProcessFurther) { 
                Array.from(itemInPathToProcessFurther.parentNode.children).forEach(sibling => { 
                    sibling.classList.remove('active');
                    if (isMobileView) sibling.classList.remove('ancestor-active'); 
                });
                
                const segmentOfItemInPath = itemInPathToProcessFurther.classList.contains('folder') ? 
                                            itemInPathToProcessFurther.dataset.folder.split('/').pop() :
                                            decodeURIComponent(itemInPathToProcessFurther.dataset.file).split('/').pop();
                const isFinalTargetInPath = fullNavPathToActivate[fullNavPathToActivate.length - 1] === segmentOfItemInPath;

                if (isMobileView) {
                    if (isFinalTargetInPath || itemInPathToProcessFurther.classList.contains('file')) {
                        itemInPathToProcessFurther.classList.add('active'); itemInPathToProcessFurther.classList.remove('ancestor-active');
                    } else {
                        itemInPathToProcessFurther.classList.add('ancestor-active'); itemInPathToProcessFurther.classList.remove('active');
                    }
                    if (parentLiElement && parentLiElement.tagName === 'LI') { 
                        parentLiElement.classList.add('ancestor-active'); parentLiElement.classList.remove('active'); 
                    }
                } else { 
                    itemInPathToProcessFurther.classList.add('active'); itemInPathToProcessFurther.classList.remove('ancestor-active'); 
                    let refNode = itemInPathToProcessFurther.closest('.nav-menu');
                    if (refNode && refNode.previousElementSibling) { 
                        let prevMenuElement = refNode.previousElementSibling;
                        while(prevMenuElement) {
                            if (prevMenuElement.id === 'main-menu-container' || prevMenuElement.classList.contains('nav-menu')) {
                                const prevActive = prevMenuElement.querySelector('ul > li.active, ul > li.ancestor-active');
                                if (prevActive) { prevActive.classList.add('active'); prevActive.classList.remove('ancestor-active'); }
                            }
                            if (prevMenuElement.id === 'main-menu-container') break;
                            prevMenuElement = prevMenuElement.previousElementSibling;
                        }
                    }
                }
                
                if (itemInPathToProcessFurther.classList.contains('folder') && !isFinalTargetInPath) {
                    const nextFolderToFetch = itemInPathToProcessFurther.getAttribute('data-folder');
                    const nextLevelForItem = parseInt(itemInPathToProcessFurther.getAttribute('data-level'), 10); 
                    await OMEGADEX_APP.fetchSubMenu(nextFolderToFetch, nextLevelForItem, fullNavPathToActivate, isMobileView ? itemInPathToProcessFurther : null);
                }
            }
            return ulElem; 
        } else { 
            if (!isMobileView) OMEGADEX_APP.adjustNavContainerWidth(); 
            return null; 
        }
    } catch (error) { 
        console.error(`${logPrefix} CATCH BLOCK:`, error); 
        if (!isMobileView && OMEGADEX_APP.navContainer && OMEGADEX_APP.navContainer.children.length > level) { Array.from(OMEGADEX_APP.navContainer.children).slice(level).forEach(subMenu => subMenu.remove()); } 
        if (!isMobileView) OMEGADEX_APP.adjustNavContainerWidth(); 
        return null; 
    }
};