/* Generated by chatGPT 4o */

document.addEventListener('DOMContentLoaded', () => {
    const mainMenu = document.getElementById('main-menu');
    const navContainer = document.getElementById('nav-container');
    const contentElem = document.getElementById('content');
    const menuToggle = document.getElementById('menu-toggle');
    const navWrapper = document.getElementById('nav-wrapper');

    const fetchSubMenu = async (folder, level) => {
        try {
            const response = await fetch(`navigation_sub.php?folder=${encodeURIComponent(folder)}`);
            const data = await response.json();

            // Remove all sub-menus below the current level
            Array.from(navContainer.children).slice(level).forEach(subMenu => subMenu.remove());

            // Add new sub-menu if there are items to show
            if (Object.keys(data).length > 0) {
                const subMenuContainer = document.createElement('div');
                subMenuContainer.className = 'nav-menu';
                subMenuContainer.innerHTML = `<ul id="sub-menu-${level}"></ul>`;
                navContainer.appendChild(subMenuContainer);

                const ulElem = subMenuContainer.querySelector('ul');
                for (const key in data) {
                    const displayKey = key.replace(/^#\d+\s*/, '').replace(/\.txt$/, '');
                    if (typeof data[key] === 'object') {
                        ulElem.innerHTML += `<li class="folder" data-folder="${folder}/${key}" data-level="${level + 1}">${displayKey}</li>`;
                    } else {
                        ulElem.innerHTML += `<li class="file" data-file="${encodeURIComponent(data[key])}">${displayKey}</li>`;
                    }
                }
            }

            adjustNavContainerWidth();
        } catch (error) {
            console.error('Error fetching sub-menu data:', error);
        }
    };

    const fetchContent = async (path, type = 'file') => {
        try {
            const response = await fetch(`content.php?${type}=${encodeURIComponent(path)}`);
            const html = await response.text();
            contentElem.innerHTML = html;
        } catch (error) {
            console.error('Error fetching content:', error);
        }
    };

    const adjustNavContainerWidth = () => {
        const subMenus = document.querySelectorAll('.nav-menu');
        let totalWidth = 0;
        let count = 0;
        subMenus.forEach(subMenu => {
            if (count >= 1) {
                totalWidth += subMenu.offsetWidth;
            }
            count += 1;
        });
        navContainer.style.width = `${totalWidth}px`;
    };

    mainMenu.addEventListener('click', (event) => {
        const target = event.target;

        if (target.tagName === 'LI') {
            for (const child of mainMenu.children) {
                child.classList.remove('active');
            }
            target.classList.add('active');
        }

        if (target.classList.contains('main-folder')) {
            const folder = target.getAttribute('data-folder');
            fetchContent(folder, 'folder'); // Load main folder's index content
            fetchSubMenu(folder, 0); // Load sub-menu items
        }
    });

    navContainer.addEventListener('click', (event) => {
        const target = event.target;

        if (target.tagName === 'LI') {
            const siblings = target.parentNode.children;
            for (let i = 0; i < siblings.length; i++) {
                if (siblings[i] !== target) {
                    siblings[i].classList.remove('active');
                }
            }
            target.classList.add('active');
        }

        if (target.classList.contains('folder')) {
            const folder = target.getAttribute('data-folder');
            const level = parseInt(target.getAttribute('data-level'), 10);
            fetchContent(folder, 'folder'); // Load sub-folder's index content
            fetchSubMenu(folder, level); // Load further sub-menu items
        } else if (target.classList.contains('file')) {
            const filePath = target.getAttribute('data-file');
            fetchContent(filePath, 'file');
        }
    });

    adjustNavContainerWidth();

    // Mobile menu toggle
    menuToggle.addEventListener('click', () => {
        navWrapper.classList.toggle('open');
    });

    // Close navigation menu when clicking on the content area on mobile
    contentElem.addEventListener('click', () => {
        if (window.innerWidth <= 900) {
            navWrapper.classList.remove('open');
        }
    });
    // Ensure menu closes when the viewport is resized to more than 768px
    window.addEventListener('resize', () => {
        if (window.innerWidth > 900) {
            navWrapper.classList.remove('open');
        }
    });
});