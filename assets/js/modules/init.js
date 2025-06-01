/* assets/js/modules/init.js (V6.21 - Target correct search input) */
//console.log("JS: init.js (V6.21) loaded.");

if (!window.OMEGADEX_APP) window.OMEGADEX_APP = {};

OMEGADEX_APP.handleSearchNavigation = async () => {
    const logPrefix = "JS:Init: handleSearchNavigation";
    // console.log(`${logPrefix} START.`); // Less verbose

    const isMobileView = window.innerWidth <= 900;
    const urlParams = new URLSearchParams(window.location.search);
    const navPathFromUrl = urlParams.get('navpath'); 
    const highlightTerm = urlParams.get('highlight'); 
    const searchQueryForBox = urlParams.get('search_query_display'); 
    
    // Pre-fill the correct search input based on view
    const currentSearchInput = isMobileView ? OMEGADEX_APP.mainQueryInputMobile : OMEGADEX_APP.desktopFooterQueryInput;
    if (currentSearchInput && searchQueryForBox) { 
        currentSearchInput.value = searchQueryForBox; 
    }
    
    if (navPathFromUrl && OMEGADEX_APP.mainMenu) { 
        // console.log(`${logPrefix} - Processing navpath: "${navPathFromUrl}"`); // Less verbose
        const normalizedNavPath = navPathFromUrl.replace(/\\/g, '/');
        const navPathSegments = normalizedNavPath.split('/').filter(segment => segment && segment.trim() !== '');
        // console.log(`${logPrefix} - NavPathSegments:`, JSON.stringify(navPathSegments)); // Less verbose
        
        if (navPathSegments.length > 0) {
            let currentIterationLi = Array.from(OMEGADEX_APP.mainMenu.querySelectorAll('li.main-folder'))
                .find(li => li.getAttribute('data-folder')?.replace(/\\/g, '/') === navPathSegments[0]);
            
            if (currentIterationLi) {
                // console.log(`${logPrefix} - Found initial main menu LI for segment: "${navPathSegments[0]}"`); // Less verbose
                Array.from(OMEGADEX_APP.mainMenu.children).forEach(child => child.classList.remove('active', 'ancestor-active'));
                
                let currentPathForSubMenuFetch = navPathSegments[0];
                let currentLevelForSubMenuCreation = 0; 

                for (let i = 0; i < navPathSegments.length; i++) {
                    const segmentToFindOrActivate = navPathSegments[i];
                    const isLastSegmentInPath = (i === navPathSegments.length - 1);

                    if (!currentIterationLi) {
                        console.warn(`${logPrefix} - Path broken at segment: "${segmentToFindOrActivate}" (index ${i}).`);
                        break; 
                    }
                    // console.log(`${logPrefix} - Iteration ${i}: Activating segment: "${segmentToFindOrActivate}" on LI:`, currentIterationLi.textContent.trim()); // Less verbose

                    if (isMobileView) {
                        currentIterationLi.classList.add(isLastSegmentInPath ? 'active' : 'ancestor-active');
                        currentIterationLi.classList.remove(isLastSegmentInPath ? 'ancestor-active' : 'active');
                    } else { 
                        currentIterationLi.classList.add('active');
                        currentIterationLi.classList.remove('ancestor-active');
                    }
                    
                    if ((currentIterationLi.classList.contains('folder') || currentIterationLi.classList.contains('main-folder')) && !isLastSegmentInPath) {
                        currentPathForSubMenuFetch = currentIterationLi.getAttribute('data-folder');
                        currentLevelForSubMenuCreation = currentIterationLi.matches('#main-menu > li') ? 0 : (parseInt(currentIterationLi.getAttribute('data-level'), 10) || 0);
                       
                        // console.log(`${logPrefix} - Calling fetchSubMenu for folder: "${currentPathForSubMenuFetch}", level: ${currentLevelForSubMenuCreation}`); // Less verbose
                        const subMenuUL = await OMEGADEX_APP.fetchSubMenu(currentPathForSubMenuFetch, currentLevelForSubMenuCreation, navPathSegments, isMobileView ? currentIterationLi : null);
                        
                        if (!subMenuUL) { console.warn(`${logPrefix} - Sub-menu for "${currentPathForSubMenuFetch}" empty/failed.`); break; } 
                        
                        const nextSegmentToFind = navPathSegments[i+1];
                        let nextLiElement = null;
                        
                        if (subMenuUL) { 
                            nextLiElement = Array.from(subMenuUL.querySelectorAll(':scope > li')).find(li => { 
                                const dataFolderAttr = li.getAttribute('data-folder');
                                const dataFileAttr = li.getAttribute('data-file');
                                let keyToMatch = "";
                                if (li.classList.contains('folder') && dataFolderAttr) { keyToMatch = dataFolderAttr.split('/').pop(); } 
                                else if (li.classList.contains('file') && dataFileAttr) { keyToMatch = decodeURIComponent(dataFileAttr).split('/').pop(); }
                                if (!keyToMatch && li.textContent) keyToMatch = li.textContent.trim().replace(/^#\d+\s*/, '');
                                return keyToMatch === nextSegmentToFind;
                            });
                        }

                        if (nextLiElement) {
                            // console.log(`${logPrefix} - Found next LI for "${nextSegmentToFind}":`, nextLiElement.textContent.trim()); // Less verbose
                            currentIterationLi = nextLiElement; 
                        } else { console.warn(`${logPrefix} - Auto-nav: Could not find child LI for segment "${nextSegmentToFind}".`); currentIterationLi = null; break; }
                    } else { 
                        // console.log(`${logPrefix} - Reached last segment OR item is a file: "${segmentToFindOrActivate}".`); // Less verbose
                        if (currentIterationLi.classList.contains('folder') && isLastSegmentInPath) {
                            const finalLevel = currentIterationLi.matches('#main-menu > li') ? 0 : (parseInt(currentIterationLi.getAttribute('data-level'), 10) || 0);
                            const finalPathForFetch = currentIterationLi.getAttribute('data-folder');
                            if (isMobileView) { if (!currentIterationLi.nextElementSibling || !currentIterationLi.nextElementSibling.classList.contains('mobile-submenu')) { await OMEGADEX_APP.fetchSubMenu(finalPathForFetch, finalLevel, navPathSegments, currentIterationLi); } } 
                            else if (OMEGADEX_APP.navContainer) { if (!OMEGADEX_APP.navContainer.querySelector(`#sub-menu-l${finalLevel}-f-${finalPathForFetch.replace(/[^a-zA-Z0-9-_]/g, '')}`)) { await OMEGADEX_APP.fetchSubMenu(finalPathForFetch, finalLevel, navPathSegments, null); } }
                        }
                        break; 
                    }
                } 
            }
        }
        if (history.replaceState) { const cUrl = new URL(window.location.href); cUrl.searchParams.delete('navpath'); cUrl.searchParams.delete('folder'); cUrl.searchParams.delete('file'); history.replaceState(null, '', `${cUrl.pathname}?${cUrl.searchParams.toString()}${cUrl.hash}`);}
    
    } else if (OMEGADEX_APP.mainMenu) { 
        let activeMainFolderElement = OMEGADEX_APP.mainMenu.querySelector('li.main-folder.active'); 
        if (!activeMainFolderElement) { activeMainFolderElement = OMEGADEX_APP.mainMenu.querySelector('li.main-folder[data-folder="#1 Welcome"]'); if (activeMainFolderElement) activeMainFolderElement.classList.add('active'); }
        if (activeMainFolderElement) {
            const folderToFetchSubMenuFor = activeMainFolderElement.getAttribute('data-folder').replace(/\\/g, '/');
            await OMEGADEX_APP.fetchSubMenu(folderToFetchSubMenuFor, 0, [folderToFetchSubMenuFor], isMobileView ? activeMainFolderElement : null); 
        }
    }
    if (highlightTerm && OMEGADEX_APP.contentElem) { OMEGADEX_APP.applySearchTermHighlighting(); }
    // console.log(`${logPrefix} END.`); // Less verbose
};