/* assets/js/modules/utils.js (V6.19m) */
// console.log("JS: utils.js loaded."); // Keep console cleaner

if (!window.OMEGADEX_APP) window.OMEGADEX_APP = {};

const OMEGADEX_MAX_DESKTOP_SUBMENU_WIDTH = 245;

OMEGADEX_APP.adjustNavContainerWidth = () => { 
    if (!OMEGADEX_APP.navContainer) return; 
    const subMenus = OMEGADEX_APP.navContainer.querySelectorAll('.nav-menu');
    let totalWidth = 0;
    subMenus.forEach(subMenu => {
        if (window.innerWidth > 900 && typeof OMEGADEX_APP.fitSubMenuWidthForActiveState === 'function') {
            OMEGADEX_APP.fitSubMenuWidthForActiveState(subMenu);
        }
        const styles = window.getComputedStyle(subMenu);
        const marginLeft = parseFloat(styles.marginLeft) || 0;
        const marginRight = parseFloat(styles.marginRight) || 0;
        totalWidth += subMenu.offsetWidth + marginLeft + marginRight;
    });
    // Small buffer avoids fractional-pixel clipping on scaled displays.
    OMEGADEX_APP.navContainer.style.width = `${Math.ceil(totalWidth + 2)}px`;
};

OMEGADEX_APP.fitSubMenuWidthForActiveState = (menuElement) => {
    if (!menuElement || !(menuElement instanceof HTMLElement)) return;

    const listItems = Array.from(menuElement.querySelectorAll(':scope > ul > li'));
    if (listItems.length === 0) return;

    const sampleItem = listItems[0];
    const sampleStyles = window.getComputedStyle(sampleItem);
    const menuStyles = window.getComputedStyle(menuElement);

    const measureWrapper = document.createElement('div');
    measureWrapper.className = 'nav-menu';
    measureWrapper.style.position = 'absolute';
    measureWrapper.style.left = '-100000px';
    measureWrapper.style.top = '-100000px';
    measureWrapper.style.visibility = 'hidden';
    measureWrapper.style.width = 'auto';
    measureWrapper.style.minWidth = '0';
    measureWrapper.style.maxWidth = 'none';
    measureWrapper.style.height = 'auto';
    measureWrapper.style.overflow = 'visible';
    measureWrapper.style.paddingTop = menuStyles.paddingTop;
    measureWrapper.style.paddingRight = menuStyles.paddingRight;
    measureWrapper.style.paddingBottom = menuStyles.paddingBottom;
    measureWrapper.style.paddingLeft = menuStyles.paddingLeft;
    measureWrapper.style.borderTopWidth = menuStyles.borderTopWidth;
    measureWrapper.style.borderRightWidth = menuStyles.borderRightWidth;
    measureWrapper.style.borderBottomWidth = menuStyles.borderBottomWidth;
    measureWrapper.style.borderLeftWidth = menuStyles.borderLeftWidth;
    measureWrapper.style.borderTopStyle = menuStyles.borderTopStyle;
    measureWrapper.style.borderRightStyle = menuStyles.borderRightStyle;
    measureWrapper.style.borderBottomStyle = menuStyles.borderBottomStyle;
    measureWrapper.style.borderLeftStyle = menuStyles.borderLeftStyle;

    const measureList = document.createElement('ul');
    measureList.style.margin = '0';
    measureList.style.padding = '0';
    measureList.style.listStyle = 'none';
    measureWrapper.appendChild(measureList);

    listItems.forEach((item) => {
        const measureItem = document.createElement('li');
        measureItem.className = 'active';
        measureItem.textContent = item.textContent || '';
        measureItem.style.display = 'block';
        measureItem.style.width = 'max-content';
        measureItem.style.maxWidth = 'none';
        measureItem.style.whiteSpace = 'nowrap';
        measureItem.style.overflowWrap = 'normal';
        measureItem.style.wordBreak = 'normal';
        measureItem.style.paddingTop = sampleStyles.paddingTop;
        measureItem.style.paddingRight = sampleStyles.paddingRight;
        measureItem.style.paddingBottom = sampleStyles.paddingBottom;
        measureItem.style.paddingLeft = sampleStyles.paddingLeft;
        measureItem.style.fontSize = sampleStyles.fontSize;
        measureItem.style.lineHeight = sampleStyles.lineHeight;
        measureItem.style.fontFamily = sampleStyles.fontFamily;
        measureItem.style.letterSpacing = sampleStyles.letterSpacing;
        measureList.appendChild(measureItem);
    });

    document.body.appendChild(measureWrapper);
    let maxItemWidth = 0;
    Array.from(measureList.children).forEach((li) => {
        maxItemWidth = Math.max(maxItemWidth, li.getBoundingClientRect().width);
    });
    measureWrapper.remove();

    const menuHorizontalExtras =
        (parseFloat(menuStyles.paddingLeft) || 0) +
        (parseFloat(menuStyles.paddingRight) || 0) +
        (parseFloat(menuStyles.borderLeftWidth) || 0) +
        (parseFloat(menuStyles.borderRightWidth) || 0);

    const targetWidth = Math.ceil(maxItemWidth + menuHorizontalExtras + 2);
    const minimumWidth = parseFloat(menuStyles.minWidth) || parseFloat(menuStyles.width) || 220;
    const maximumWidth = Math.max(OMEGADEX_MAX_DESKTOP_SUBMENU_WIDTH, minimumWidth);
    const finalWidth = Math.min(Math.max(targetWidth, minimumWidth), maximumWidth);

    menuElement.style.width = `${finalWidth}px`;
    menuElement.style.minWidth = `${finalWidth}px`;
    menuElement.style.maxWidth = `${finalWidth}px`;
};

OMEGADEX_APP.escapeRegExp = (string) => { 
    if (typeof string !== 'string') return '';
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); 
};