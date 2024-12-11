"use strict";

const dt = dt || { init: {}, render: {} }; // initialize namespace

/**
 * Initialize DataTables
 * @param {string} id - Table selector
 * @param {object} options - DataTables configuration options
 */
dt.initDataTables = function (id, options) {
    // Default to text renderer for columns
    options.columns.forEach((column, i) => {
        if (!column.render) {
            options.columns[i].render = $.fn.dataTable.render.text();
        }
    });

    // Attach initializers if provided
    if (options.init) {
        const initializers = options.init;
        delete options.init;

        $(id).on('preInit.dt', function () {
            const table = $(id).DataTable();
            initializers.forEach(init => init(table));
        });
    }

    // Initialize DataTable instance
    $(id).DataTable(options);
};

/**
 * Delay search trigger for DataTables input field
 * @param {object} table - DataTables instance
 * @param {number} minSearchCharacters - Minimum characters required to trigger search
 * @param {number} delay - Delay in ms to prevent premature searches
 * @param {string} selector - Optional selector for external search field
 */
dt.init.delayedSearch = function (table, minSearchCharacters = 3, delay = 200, selector) {
    const inputSelector = selector || `#${table.attr('id')}_filter input`;
    let timer = null;

    const triggerSearch = value => table.api().search(value).draw();

    $(inputSelector)
        .off()
        .on('input', function () {
            if (this.value.length >= minSearchCharacters || !this.value) {
                clearTimeout(timer);
                timer = setTimeout(() => triggerSearch(this.value), delay);
            }
        })
        .on('keydown', function (e) {
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
    table.api().on('select', (e, dt, type, indexes) => {
        const row = table.api().rows(indexes).data()[0];
        const url = `${urlbase}/${row.id}`;

        if (target) {
            $(target).load(url);
        } else {
            window.location.href = url;
        }

        table.api().rows(indexes).deselect();
    });
};

/**
 * Resize table to fit window
 * @param {object} table - DataTables instance
 * @param {number} offset - Height offset for adjustments
 * @param {boolean} fullscreen - Whether to fit fullscreen
 */
dt.init.fitIntoWindow = function (table, offset = 0, fullscreen = false) {
    const wrapper = $(table.api().table().container());
    let body = wrapper.find('.dataTables_scrollBody');
    if (!body.length) body = table;

    const minHeight = body.outerHeight();
    table.data('fullscreen', fullscreen);

    const resizeHandler = () => {
        const windowHeight = window.innerHeight;
        let totalHeight = fullscreen ? windowHeight : windowHeight - wrapper.offset().top;

        totalHeight -= $('body').outerHeight(true) - $('body').outerHeight(false);
        totalHeight -= wrapper.outerHeight(true) - body.outerHeight(false);

        const newHeight = Math.max(totalHeight - offset, minHeight);
        if (body.height() !== newHeight) {
            body.height(`${newHeight}px`);
            if (table.api().scroller) {
                table.api().scroller.measure(false);
            }
        }
    };

    table.on('fitIntoWindow', resizeHandler);
    table.trigger('fitIntoWindow');

    $(window).on('resize', debounce(() => table.trigger('fitIntoWindow'), 250));

    table.on('toggleFullscreen', () => {
        table.data('fullscreen', !table.data('fullscreen'));
        table.trigger('fitIntoWindow');
    });
};

/**
 * Escapes HTML characters in a string
 * @param {string} str - String to escape
 * @returns {string} Escaped string
 */
dt.h = str => (typeof str === 'string' ? str.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;') : str);

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
