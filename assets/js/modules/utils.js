/* assets/js/modules/utils.js (V6.19m) */
// console.log("JS: utils.js loaded."); // Keep console cleaner

if (!window.OMEGADEX_APP) window.OMEGADEX_APP = {};

OMEGADEX_APP.adjustNavContainerWidth = () => { 
    if (!OMEGADEX_APP.navContainer) return; 
    const subMenus = OMEGADEX_APP.navContainer.querySelectorAll('.nav-menu');
    let totalWidth = 0;
    subMenus.forEach(subMenu => { totalWidth += subMenu.offsetWidth; });
    OMEGADEX_APP.navContainer.style.width = `${totalWidth}px`;
};

OMEGADEX_APP.escapeRegExp = (string) => { 
    if (typeof string !== 'string') return '';
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); 
};