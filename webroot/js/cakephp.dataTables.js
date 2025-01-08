"use strict";

// Initialize the namespace if not already initialized
var dt = dt || { init: {}, render: {} };

/**
 * Initialize DataTables
 * @param {string} id - Table selector
 * @param {object} options - DataTables configuration options
 */
dt.initDataTables = function (id, options) {
    // Default text renderer for columns without a render function
    options.columns.forEach((column, i) => {
        if (!column.render) {
            options.columns[i].render = DataTable.render.text();
        }
    });

    // Attach initializers if provided
    if (options.init) {
        const initializers = options.init;
        delete options.init;

        document.querySelector(id).addEventListener('preInit.dt', function () {
            const table = new DataTable(document.querySelector(id), options);
            initializers.forEach(init => init(table));
        });
    }

    // Initialize the DataTable instance with the provided options
    new DataTable(document.querySelector(id), options);
};

/**
 * Delay search trigger for DataTables input field
 * @param {object} table - DataTables instance
 * @param {number} minSearchCharacters - Minimum characters required to trigger search
 * @param {number} delay - Delay in ms to prevent premature searches
 * @param {string} selector - Optional selector for external search field
 */
dt.init.delayedSearch = function (table, minSearchCharacters = 3, delay = 200, selector) {
    const inputSelector = selector || `#${table.table().node().id}_filter input`;
    const input = document.querySelector(inputSelector);
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
        const url = `${urlbase}/${row.id}`;

        if (target) {
            document.querySelector(target).load(url);
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

    const minHeight = body.offsetHeight;
    table.table().node().dataset.fullscreen = fullscreen;

    const resizeHandler = () => {
        const windowHeight = window.innerHeight;
        const wrapperRect = wrapper.getBoundingClientRect();
        let totalHeight = fullscreen ? windowHeight : windowHeight - wrapperRect.top;

        const newHeight = Math.max(totalHeight - offset, minHeight);
        if (body.offsetHeight !== newHeight) {
            body.style.height = `${newHeight}px`;
            if (table.scroller) {
                table.scroller.measure(false);
            }
        }
    };

    table.on('fitIntoWindow', resizeHandler);
    table.trigger('fitIntoWindow');

    window.addEventListener('resize', debounce(() => table.trigger('fitIntoWindow'), 250));

    table.on('toggleFullscreen', () => {
        table.table().node().dataset.fullscreen = !(table.table().node().dataset.fullscreen === 'true');
        table.trigger('fitIntoWindow');
    });
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
