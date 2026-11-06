const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

const adapter = fs.readFileSync(path.join(__dirname, '../../webroot/js/cakephp.dataTables.js'), 'utf8');

function setup() {
    const dom = new JSDOM('<table id="users"></table>', {
        url: 'http://localhost/', runScripts: 'outside-only', pretendToBeVisual: true,
    });
    dom.window.eval(fs.readFileSync(require.resolve('datatables.net'), 'utf8'));
    dom.window.eval(adapter);
    assert.equal(dom.window.jQuery, undefined);
    assert.equal(dom.window.$, undefined);
    return dom;
}

    test('DataTables 3: named ordering, safe rendering and reusable options', () => {
        const dom = setup();
        try {
            const options = dom.window.JSON.parse(JSON.stringify({
                data: [{ display_title: '<img src=x>' }, { display_title: 'B' }],
                columns: [{ data: 'display_title', name: 'display_title', title: 'Title' }],
                order: { name: 'display_title', dir: 'desc' },
            }));
            const columns = options.columns;
            let initialized = false;
            options.init = [api => { initialized = api.rows().count() === 2; }];
            const table = dom.window.dt.initDataTables('#users', options);
            assert.equal(initialized, true);
            assert.equal(columns[0].render, undefined);
            assert.equal(dom.window.document.querySelector('tbody img'), null);
            assert.match(dom.window.document.querySelector('tbody').textContent, /<img src=x>/);
            assert.equal(dom.window.document.querySelector('tbody tr').textContent, 'B');
            table.destroy();
        } finally {
            dom.window.close();
        }
    });

    test('DataTables 3: server-side search and paging protocol', () => {
        const dom = setup();
        try {
            const requests = [];
            const table = dom.window.dt.initDataTables('#users', {
                serverSide: true,
                columns: [{ data: 'display_title', title: 'Title' }],
                ajax: (request, callback) => {
                    requests.push(request);
                    callback({ draw: request.draw, recordsTotal: 3, recordsFiltered: 1,
                        data: [{ display_title: 'First!' }] });
                },
            });
            table.search('0').draw();
            const request = requests.at(-1);
            assert.equal(request.search.value, '0');
            assert.equal(request.columns[0].data, 'display_title');
            assert.equal(request.start, 0);
            assert.equal(request.length, 10);
            assert.equal(table.page.info().recordsTotal, 3);
            assert.equal(table.page.info().recordsDisplay, 1);
            table.destroy();
        } finally {
            dom.window.close();
        }
    });

test('missing DataTables produces an actionable error', () => {
    const dom = new JSDOM('<table id="users"></table>', { runScripts: 'outside-only' });
    try {
        dom.window.eval(adapter);
        assert.throws(() => dom.window.dt.initDataTables('#users'), /not loaded/);
    } finally {
        dom.window.close();
    }
});

test('older DataTables majors are rejected', () => {
    const dom = new JSDOM('<table id="users"></table>', { runScripts: 'outside-only' });
    try {
        dom.window.DataTable = { version: '2.3.8' };
        dom.window.eval(adapter);
        assert.throws(() => dom.window.dt.initDataTables('#users'), /requires DataTables 3/);
    } finally {
        dom.window.close();
    }
});
