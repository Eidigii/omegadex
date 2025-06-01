/* assets/js/modules/eventListeners.js (V6.23 - Complete, Targets Both Search Inputs) */
//console.log("JS: eventListeners.js (V6.23) loaded.");

if (!window.OMEGADEX_APP) window.OMEGADEX_APP = {};

OMEGADEX_APP.attachEventListeners = () => {
    // console.log("JS:EventListeners: Attaching event listeners (V6.23).");

    // --- Main Menu Click Listener ---
    if (OMEGADEX_APP.mainMenu) {
        OMEGADEX_APP.mainMenu.addEventListener('click', async (event) => {
            const isMobileView = window.innerWidth <= 900;
            const target = event.target.closest('li.main-folder');
            if (!target) return;

            OMEGADEX_APP.mainMenu.querySelectorAll('li.main-folder.ancestor-active').forEach(li => {
                if (li !== target) li.classList.remove('ancestor-active');
            });

            if (isMobileView) {
                const GwasActive = target.classList.contains('active'); 
                const existingSubMenu = target.nextElementSibling;
                
                OMEGADEX_APP.mainMenu.querySelectorAll('li.main-folder.active').forEach(activeLi => {
                    if (activeLi !== target) { 
                        activeLi.classList.remove('active', 'ancestor-active'); 
                        const otherSub = activeLi.nextElementSibling; 
                        if (otherSub && otherSub.classList.contains('mobile-submenu')) otherSub.remove(); 
                    }
                });

                if (GwasActive && existingSubMenu && existingSubMenu.classList.contains('mobile-submenu')) {
                    existingSubMenu.remove(); 
                    target.classList.remove('active'); 
                    return; 
                }
                target.classList.add('active'); 
                target.classList.remove('ancestor-active');
            } else { 
                Array.from(OMEGADEX_APP.mainMenu.children).forEach(child => { 
                    if (child !== target) child.classList.remove('active', 'ancestor-active'); 
                }); 
                if (OMEGADEX_APP.navContainer) OMEGADEX_APP.navContainer.innerHTML = ''; 
                target.classList.add('active'); 
                target.classList.remove('ancestor-active'); 
            }
            
            const folder = target.getAttribute('data-folder').replace(/\\/g, '/');
            await OMEGADEX_APP.fetchContent(folder, 'folder');
            await OMEGADEX_APP.fetchSubMenu(folder, 0, [folder], isMobileView ? target : null);
        });
    } else { console.error("JS:EventListeners: mainMenu not found for listener."); }

    // --- Mobile Accordion & Desktop Sub-menu Click Listener ---
    if (OMEGADEX_APP.navWrapper) {
        OMEGADEX_APP.navWrapper.addEventListener('click', async (event) => {
            const isMobileView = window.innerWidth <= 900;
            if (!isMobileView && OMEGADEX_APP.navContainer && OMEGADEX_APP.navContainer.contains(event.target)) { return; } 
            
            if (isMobileView) {
                const target = event.target.closest('ul.mobile-submenu > li');
                if (!target) return; 
                const parentUl = target.closest('ul.mobile-submenu');
                if (!parentUl) return; 
                const grandParentLi = parentUl.previousElementSibling; 

                const GwasActive = target.classList.contains('active');
                const existingSubMenu = target.nextElementSibling;

                Array.from(parentUl.children).forEach(siblingLi => { 
                    if (siblingLi !== target) { 
                        siblingLi.classList.remove('active', 'ancestor-active'); 
                        const subSubMenu = siblingLi.nextElementSibling;
                        if (subSubMenu && subSubMenu.classList.contains('mobile-submenu')) subSubMenu.remove();
                    }
                });
                
                if (target.classList.contains('folder')) {
                    if (GwasActive && existingSubMenu && existingSubMenu.classList.contains('mobile-submenu')) {
                        existingSubMenu.remove(); 
                        target.classList.remove('active', 'ancestor-active'); 
                        if(grandParentLi && grandParentLi.tagName ==='LI' && grandParentLi.classList.contains('ancestor-active') && !parentUl.querySelector('li.active')){ 
                            grandParentLi.classList.add('active'); grandParentLi.classList.remove('ancestor-active');
                        }
                        return;
                    }
                    target.classList.add('active'); target.classList.remove('ancestor-active');
                    if (grandParentLi && grandParentLi.tagName === 'LI') { 
                        grandParentLi.classList.add('ancestor-active'); grandParentLi.classList.remove('active');
                    }
                    const folder = target.getAttribute('data-folder').replace(/\\/g, '/');
                    const level = parseInt(target.getAttribute('data-level'), 10); 
                    await OMEGADEX_APP.fetchContent(folder, 'folder');
                    const navPathArray = folder.split('/');
                    await OMEGADEX_APP.fetchSubMenu(folder, level, navPathArray, target); 
                } else if (target.classList.contains('file')) {
                    target.classList.add('active'); target.classList.remove('ancestor-active'); 
                    if (grandParentLi && grandParentLi.tagName === 'LI') { 
                        grandParentLi.classList.add('ancestor-active'); grandParentLi.classList.remove('active');
                    }
                    const filePath = decodeURIComponent(target.getAttribute('data-file')).replace(/\\/g, '/');
                    await OMEGADEX_APP.fetchContent(filePath, 'file');
                    if (OMEGADEX_APP.navWrapper && OMEGADEX_APP.navWrapper.classList.contains('open')) {
                        OMEGADEX_APP.navWrapper.classList.remove('open');
                    }
                }
            }
        });
    } else { console.error("JS:EventListeners: navWrapper not found for listener."); }
    
    if (OMEGADEX_APP.navContainer) { 
         OMEGADEX_APP.navContainer.addEventListener('click', async (event) => { 
            const isMobileView = window.innerWidth <= 900; if (isMobileView) return; 
            const target = event.target.closest('li'); if (!target) return;
            const parentUl = target.closest('ul');
            if (parentUl) { Array.from(parentUl.children).forEach(sibling => { if (sibling !== target) sibling.classList.remove('active', 'ancestor-active'); }); }
            target.classList.add('active'); target.classList.remove('ancestor-active');
            let currentMenuDiv = target.closest('.nav-menu');
            if (currentMenuDiv) {
                let prevNavElement = currentMenuDiv.previousElementSibling;
                while(prevNavElement) { 
                    let listToUpdate = null;
                    if (prevNavElement.classList.contains('nav-menu')) listToUpdate = prevNavElement.querySelector('ul');
                    else if (prevNavElement.id === 'main-menu-container') listToUpdate = OMEGADEX_APP.mainMenu;
                    if (listToUpdate) { listToUpdate.querySelectorAll('li.active, li.ancestor-active').forEach(activeLi => { activeLi.classList.add('active'); activeLi.classList.remove('ancestor-active'); }); } 
                    if (prevNavElement.id === 'main-menu-container') break;
                    prevNavElement = prevNavElement.previousElementSibling;
                }
            }
            if (target.classList.contains('folder')) { 
                const folder = target.getAttribute('data-folder').replace(/\\/g, '/'); 
                const level = parseInt(target.getAttribute('data-level'), 10);
                await OMEGADEX_APP.fetchContent(folder, 'folder'); 
                const navPathArray = folder.split('/'); 
                await OMEGADEX_APP.fetchSubMenu(folder, level, navPathArray, null); 
            } else if (target.classList.contains('file')) { 
                const filePath = decodeURIComponent(target.getAttribute('data-file')).replace(/\\/g, '/'); 
                await OMEGADEX_APP.fetchContent(filePath, 'file'); 
            }
        });
    } else { console.error("JS:EventListeners: navContainer not found for listener."); }

    // --- Search Box Native Clear Functionality (Targets specific input IDs from OMEGADEX_APP) ---
    const setupSearchInputListeners = (inputElement, inputNameForLog) => {
        if (inputElement) {
            // console.log(`JS:EventListeners: Setting up listeners for ${inputNameForLog} (ID: ${inputElement.id})`);
            inputElement.addEventListener('search', function() { // Native clear event
                if (!this.value) { 
                    // console.log(`JS:EventListeners: Native search input '${inputNameForLog}' cleared by 'search' event.`);
                    const urlParams = new URLSearchParams(window.location.search);
                    if (urlParams.has('highlight')) {
                        if(typeof OMEGADEX_APP.clearSearchTermHighlighting === 'function') {
                            OMEGADEX_APP.clearSearchTermHighlighting();
                        }
                        urlParams.delete('highlight');
                        urlParams.delete('search_query_display'); 
                        history.replaceState(null, '', `${window.location.pathname}?${urlParams.toString()}${window.location.hash}`);
                    }
                }
            });
            inputElement.addEventListener('input', function() { // For backspace to empty
                 if (!this.value) { 
                    const urlParams = new URLSearchParams(window.location.search);
                    if (urlParams.has('highlight')) { 
                        if(typeof OMEGADEX_APP.clearSearchTermHighlighting === 'function') {
                            OMEGADEX_APP.clearSearchTermHighlighting();
                        }
                        urlParams.delete('highlight');
                        history.replaceState(null, '', `${window.location.pathname}?${urlParams.toString()}${window.location.hash}`);
                    }
                }
            });
        } else {
            // This warning now correctly refers to the specific inputNameForLog
            console.warn(`JS:EventListeners: Search input element for ${inputNameForLog} was NOT found.`);
        }
    };

    // Setup listeners for both search inputs using their specific global references
    setupSearchInputListeners(OMEGADEX_APP.mainQueryInputMobile, "MobileHeaderSearch (main-query-input-mobile)");    
    setupSearchInputListeners(OMEGADEX_APP.desktopFooterQueryInput, "DesktopFooterSearch (desktop-footer-query-input)"); 
};