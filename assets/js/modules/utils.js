/* assets/js/modules/utils.js (V6.19m) */
// console.log("JS: utils.js loaded."); // Keep console cleaner

if (!window.OMEGADEX_APP) window.OMEGADEX_APP = {};

OMEGADEX_APP.adjustNavContainerWidth = () => { 
    if (!OMEGADEX_APP.navContainer) return; 
    const subMenus = OMEGADEX_APP.navContainer.querySelectorAll('.nav-menu');
    let totalWidth = 0;
    subMenus.forEach(subMenu => {
        const styles = window.getComputedStyle(subMenu);
        const marginLeft = parseFloat(styles.marginLeft) || 0;
        const marginRight = parseFloat(styles.marginRight) || 0;
        totalWidth += subMenu.offsetWidth + marginLeft + marginRight;
    });
    // Small buffer avoids fractional-pixel clipping on scaled displays.
    OMEGADEX_APP.navContainer.style.width = `${Math.ceil(totalWidth + 2)}px`;
};

OMEGADEX_APP.escapeRegExp = (string) => { 
    if (typeof string !== 'string') return '';
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); 
};