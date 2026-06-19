"use strict";

// Initialize the namespace if not already initialized
var dt = dt || { init: {}, render: {} };

function resolveElement(value) {
    if (!value) {
        return null;
    }

    if (value instanceof Element) {
        return value;
    }

    return document.querySelector(value);
}

function loadIntoTarget(target, url) {
    const element = resolveElement(target);

    if (!element) {
        return;
    }

    fetch(url, { credentials: 'same-origin' })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Request failed with status ${response.status}`);
            }

            return response.text();
        })
        .then(html => {
            element.innerHTML = html;
        })
        .catch(() => {
            window.location.href = url;
        });
}

/**
 * Initialize DataTables
 * @param {string} id - Table selector
 * @param {object} options - DataTables configuration options
 */
dt.initDataTables = function (id, options) {
    const tableElement = resolveElement(id);

    if (!tableElement) {
        throw new Error(`DataTables element not found: ${id}`);
    }

    const config = Object.assign({}, options || {});
    const initializers = Array.isArray(config.init) ? config.init.slice() : [];

    delete config.init;

    // Default text renderer for columns without a render function
    if (Array.isArray(config.columns)) {
        config.columns.forEach((column, i) => {
            if (column && !column.render) {
                config.columns[i].render = DataTable.render.text();
            }
        });
    }

    // Initialize the DataTable instance with the provided options
    const table = new DataTable(tableElement, config);

    initializers.forEach(init => {
        if (typeof init === 'function') {
            init(table);
        }
    });

    return table;
};

/**
 * Delay search trigger for DataTables input field
 * @param {object} table - DataTables instance
 * @param {number} minSearchCharacters - Minimum characters required to trigger search
 * @param {number} delay - Delay in ms to prevent premature searches
 * @param {string} selector - Optional selector for external search field
 */
dt.init.delayedSearch = function (table, minSearchCharacters = 3, delay = 200, selector) {
    const input = selector ? resolveElement(selector) : table.table().container().querySelector('input[type="search"]');

    if (!input) {
        throw new Error('Unable to locate the DataTables search input.');
    }

    let timer = null;

    // Trigger search when the input reaches the minimum character threshold
    const triggerSearch = value => {
        table.search(value).draw();
    };

    input.addEventListener('input', function () {
        if (this.value.length >= minSearchCharacters || !this.value) {
            clearTimeout(timer);
            timer = setTimeout(() => triggerSearch(this.value), delay);
        }
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            clearTimeout(timer);
            triggerSearch(this.value);
        }
    });
};

/**
 * Add clickable behavior to table rows
 * @param {object} table - DataTables instance
 * @param {string} urlbase - Base URL for navigation
 * @param {string} [target] - Optional target for loading content
 */
dt.init.rowLinks = function (table, urlbase, target) {
    table.on('select', (e, dt, type, indexes) => {
        const row = table.rows(indexes).data()[0];
        if (!row) {
            return;
        }

        const rowId = row.id ?? (typeof table.row === 'function' && indexes.length ? table.row(indexes[0]).id() : null);

        if (!rowId) {
            return;
        }

        const url = `${urlbase}/${rowId}`;

        if (target) {
            loadIntoTarget(target, url);
        } else {
            window.location.href = url;
        }

        table.rows(indexes).deselect();
    });
};

/**
 * Resize table to fit window
 * @param {object} table - DataTables instance
 * @param {number} offset - Height offset for adjustments
 * @param {boolean} fullscreen - Whether to fit fullscreen
 */
dt.init.fitIntoWindow = function (table, offset = 0, fullscreen = false) {
    const wrapper = table.table().container();
    let body = wrapper.querySelector('.dataTables_scrollBody') || wrapper;
    const tableNode = table.table().node();

    if (tableNode.dtFitIntoWindowCleanup) {
        tableNode.dtFitIntoWindowCleanup();
    }

    const minHeight = body.offsetHeight;
    tableNode.dataset.fullscreen = fullscreen ? 'true' : 'false';

    const resizeHandler = () => {
        const windowHeight = window.innerHeight;
        const wrapperRect = wrapper.getBoundingClientRect();
        const isFullscreen = tableNode.dataset.fullscreen === 'true';
        const totalHeight = isFullscreen ? windowHeight : windowHeight - wrapperRect.top;

        const newHeight = Math.max(totalHeight - offset, minHeight);
        if (body.offsetHeight !== newHeight) {
            body.style.height = `${newHeight}px`;
            if (table.scroller) {
                table.scroller.measure(false);
            }
        }
    };

    const debouncedResize = debounce(() => tableNode.dispatchEvent(new Event('fitIntoWindow')), 250);
    const fullscreenHandler = () => {
        tableNode.dataset.fullscreen = tableNode.dataset.fullscreen === 'true' ? 'false' : 'true';
        tableNode.dispatchEvent(new Event('fitIntoWindow'));
    };

    tableNode.addEventListener('fitIntoWindow', resizeHandler);
    tableNode.dispatchEvent(new Event('fitIntoWindow'));

    window.addEventListener('resize', debouncedResize);
    tableNode.addEventListener('toggleFullscreen', fullscreenHandler);

    // Expose cleanup for consumers that re-initialize tables in dynamic UIs.
    tableNode.dtFitIntoWindowCleanup = () => {
        tableNode.removeEventListener('fitIntoWindow', resizeHandler);
        tableNode.removeEventListener('toggleFullscreen', fullscreenHandler);
        window.removeEventListener('resize', debouncedResize);
        delete tableNode.dtFitIntoWindowCleanup;
    };

    return tableNode.dtFitIntoWindowCleanup;
};

/**
 * Escapes HTML characters in a string
 * @param {string} str - String to escape
 * @returns {string} Escaped string
 */
dt.h = str => {
    return typeof str === 'string'
        ? str.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
        : str;
};

/**
 * Helper function to debounce events
 * @param {Function} func - Function to debounce
 * @param {number} wait - Delay in milliseconds
 * @returns {Function} Debounced function
 */
function debounce(func, wait) {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => func(...args), wait);
    };
}
