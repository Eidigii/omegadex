/* assets/js/modules/eventListeners.js (V6.25 - Corrected async on navWrapper Listener) */
//console.log("JS: eventListeners.js (V6.25) loaded.");

if (!window.OMEGADEX_APP) window.OMEGADEX_APP = {};

OMEGADEX_APP.attachEventListeners = () => {
    // console.log("JS:EventListeners: Attaching event listeners (V6.25).");

    function clearAllMobileNavStates() {
        if (!OMEGADEX_APP.navWrapper) return;
        // Deactivate all items in main menu and submenus
        OMEGADEX_APP.navWrapper.querySelectorAll('#main-menu li, .mobile-submenu li').forEach(li => {
            li.classList.remove('active', 'ancestor-active');
        });
        // Remove all mobile submenus
        OMEGADEX_APP.navWrapper.querySelectorAll('ul.mobile-submenu').forEach(ul => ul.remove());
    }

    // --- Main Menu Click Listener ---
    if (OMEGADEX_APP.mainMenu) {
        OMEGADEX_APP.mainMenu.addEventListener('click', async (event) => { 
            const isMobileView = window.innerWidth <= 900;
            const targetLi = event.target.closest('li.main-folder');
            if (!targetLi) return;

            if (isMobileView) {
                const GwasActive = targetLi.classList.contains('active');
                const existingSubMenu = targetLi.nextElementSibling;
                
                // If clicking an already active main menu item that has an open submenu, just close it.
                if (GwasActive && existingSubMenu && existingSubMenu.classList.contains('mobile-submenu')) {
                    existingSubMenu.remove(); 
                    targetLi.classList.remove('active'); 
                    return; 
                }
                
                // Otherwise, it's a new selection or re-selection, so reset everything.
                clearAllMobileNavStates(); // Reset all states and submenus

                targetLi.classList.add('active'); // Activate the clicked main item
            } else { // Desktop
                Array.from(OMEGADEX_APP.mainMenu.children).forEach(child => { 
                    if (child !== targetLi) child.classList.remove('active', 'ancestor-active'); 
                }); 
                if (OMEGADEX_APP.navContainer) OMEGADEX_APP.navContainer.innerHTML = ''; 
                targetLi.classList.add('active'); 
                targetLi.classList.remove('ancestor-active'); 
            }
            
            const folder = targetLi.getAttribute('data-folder').replace(/\\/g, '/');
            await OMEGADEX_APP.fetchContent(folder, 'folder');
            await OMEGADEX_APP.fetchSubMenu(folder, 0, [folder], isMobileView ? targetLi : null);
        });
    }

     // --- Mobile Accordion Sub-menu Click Listener (on navWrapper) ---
    if (OMEGADEX_APP.navWrapper) {
        OMEGADEX_APP.navWrapper.addEventListener('click', async (event) => { 
            const isMobileView = window.innerWidth <= 900;
            if (!isMobileView && OMEGADEX_APP.navContainer && OMEGADEX_APP.navContainer.contains(event.target)) { return; } 
            
            if (isMobileView) {
                const targetLi = event.target.closest('ul.mobile-submenu > li');
                if (!targetLi) return; 
                
                const parentUl = targetLi.closest('ul.mobile-submenu');
                if (!parentUl) return; 
                const grandParentLi = parentUl.previousElementSibling; 

                const GwasActive = targetLi.classList.contains('active');
                const existingSubMenu = targetLi.nextElementSibling;

                // Deactivate siblings AND their children first
                Array.from(parentUl.children).forEach(siblingLi => { 
                    if (siblingLi !== targetLi) { 
                        siblingLi.classList.remove('active', 'ancestor-active'); 
                        const subSubMenu = siblingLi.nextElementSibling;
                        if (subSubMenu && subSubMenu.classList.contains('mobile-submenu')) subSubMenu.remove();
                    }
                });
                
                if (targetLi.classList.contains('folder')) {
                    if (GwasActive && existingSubMenu && existingSubMenu.classList.contains('mobile-submenu')) {
                        existingSubMenu.remove(); 
                        targetLi.classList.remove('active', 'ancestor-active'); 
                        if(grandParentLi && grandParentLi.tagName ==='LI'){ 
                            grandParentLi.classList.add('active'); 
                            grandParentLi.classList.remove('ancestor-active');
                        }
                        return;
                    }
                    targetLi.classList.add('active'); 
                    targetLi.classList.remove('ancestor-active');
                    if (grandParentLi && grandParentLi.tagName === 'LI') { 
                        grandParentLi.classList.add('ancestor-active'); 
                        grandParentLi.classList.remove('active');
                    }
                    const folder = targetLi.getAttribute('data-folder').replace(/\\/g, '/');
                    const level = parseInt(targetLi.getAttribute('data-level'), 10); 
                    await OMEGADEX_APP.fetchContent(folder, 'folder');
                    const navPathArray = folder.split('/');
                    await OMEGADEX_APP.fetchSubMenu(folder, level, navPathArray, targetLi); 
                } else if (targetLi.classList.contains('file')) {
                    targetLi.classList.add('active'); 
                    targetLi.classList.remove('ancestor-active'); 
                    if (grandParentLi && grandParentLi.tagName === 'LI') { 
                        grandParentLi.classList.add('ancestor-active'); 
                        grandParentLi.classList.remove('active');
                    }
                    const filePath = decodeURIComponent(targetLi.getAttribute('data-file')).replace(/\\/g, '/');
                    await OMEGADEX_APP.fetchContent(filePath, 'file');
                    if (OMEGADEX_APP.navWrapper && OMEGADEX_APP.navWrapper.classList.contains('open')) {
                        OMEGADEX_APP.navWrapper.classList.remove('open');
                    }
                }
            }
        });
    }
    
    // --- Desktop Specific Sub-menu Click Listener ---
    if (OMEGADEX_APP.navContainer) {
        OMEGADEX_APP.navContainer.addEventListener('click', async (event) => { 
            const isMobileView = window.innerWidth <= 900; 
            if (isMobileView) return; 
            const target = event.target.closest('li'); 
            if (!target) return; 
            const parentUl = target.closest('ul'); 
            if (parentUl) { 
                Array.from(parentUl.children).forEach(sibling => { 
                    if (sibling !== target) sibling.classList.remove('active', 'ancestor-active'); 
                }); 
            } 
            target.classList.add('active'); 
            target.classList.remove('ancestor-active'); 
            let currentMenuDiv = target.closest('.nav-menu'); 
            if (currentMenuDiv) { 
                let prevNavElement = currentMenuDiv.previousElementSibling; 
                while(prevNavElement) { 
                    let listToUpdate = null; 
                    if (prevNavElement.classList.contains('nav-menu')) listToUpdate = prevNavElement.querySelector('ul'); else if (prevNavElement.id === 'main-menu-container') listToUpdate = OMEGADEX_APP.mainMenu;
                    if (listToUpdate) { 
                        listToUpdate.querySelectorAll('li.active, li.ancestor-active').forEach(activeLi => {
                            activeLi.classList.add('active'); 
                            activeLi.classList.remove('ancestor-active'); 
                        }); 
                    } 
                    if (prevNavElement.id === 'main-menu-container') break; 
                    prevNavElement = prevNavElement.previousElementSibling; } 
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
    }

    // --- Search Box Native Clear Functionality ---
    const setupSearchInputListeners = (inputElement, inputNameForLog) => {
        if (inputElement) {
            inputElement.addEventListener('search', function() { 
                if (!this.value) { 
                    const urlParams = new URLSearchParams(window.location.search);
                    if (urlParams.has('highlight')) {
                        if(typeof OMEGADEX_APP.clearSearchTermHighlighting === 'function') { OMEGADEX_APP.clearSearchTermHighlighting(); }
                        urlParams.delete('highlight'); urlParams.delete('search_query_display'); 
                        history.replaceState(null, '', `${window.location.pathname}?${urlParams.toString()}${window.location.hash}`);
                    }
                }
            });
            inputElement.addEventListener('input', function() { 
                 if (!this.value) { 
                    const urlParams = new URLSearchParams(window.location.search);
                    if (urlParams.has('highlight')) {
                        if(typeof OMEGADEX_APP.clearSearchTermHighlighting === 'function') { OMEGADEX_APP.clearSearchTermHighlighting(); }
                        urlParams.delete('highlight');
                        history.replaceState(null, '', `${window.location.pathname}?${urlParams.toString()}${window.location.hash}`);
                    }
                }
            });
        }
    };
    setupSearchInputListeners(OMEGADEX_APP.mainQueryInputMobile, "MobileHeaderSearch (main-query-input-mobile)");    
    setupSearchInputListeners(OMEGADEX_APP.desktopFooterQueryInput, "DesktopFooterSearch (desktop-footer-query-input)"); 
};