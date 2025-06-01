/* assets/js/modules/navigationCore.js (V6.23 - Handle Changelog Array Data, Complete) */
//console.log("JS: navigationCore.js (V6.23) loaded.");

if (!window.OMEGADEX_APP) window.OMEGADEX_APP = {};

OMEGADEX_APP.fetchContent = async (path, type = 'folder') => {
    const logPrefix = `JS:NavCore: fetchContent (Path: "${path}", Type: "${type}")`;
    if (!OMEGADEX_APP.contentElem) { 
        console.error(`${logPrefix} contentElem is null!`); 
        return; 
    }
    try {
        const queryPath = path.replace(/\\/g, '/');
        const response = await fetch(`content.php?${type}=${encodeURIComponent(queryPath)}`);
        if (!response.ok) throw new Error(`HTTP error ${response.status} for ${path}`);
        const html = await response.text();
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
        
        const response = await fetch(`navigation_sub.php?folder=${encodeURIComponent(folderForFetch)}`);
        if (!response.ok) { 
            console.error(`${logPrefix}) HTTP error: ${response.status} for folder "${folderForFetch}"`); 
            if (!isMobileView && OMEGADEX_APP.navContainer) Array.from(OMEGADEX_APP.navContainer.children).slice(level).forEach(subMenu => subMenu.remove()); 
            OMEGADEX_APP.adjustNavContainerWidth(); 
            return null; 
        }
        
        const data = await response.json(); 
        
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

                const displayKey = key_as_filename.replace(/^#\d+\s*/, '').replace(/\.txt$/, '');
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