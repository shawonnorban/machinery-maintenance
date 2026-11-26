"""Condensed print of a Brickland view spec: columns, filters, row actions, option data and panels."""
import json, sys
d = json.load(open(sys.argv[1], encoding='utf-8'))
v = d['view']
U = '$undefined'


def f(x):
    out = []
    for fl in x or []:
        s = f"{fl.get('key')}:{fl.get('label') or fl.get('header')}[{fl.get('control') or fl.get('kind') or ''}]"
        if fl.get('required'): s += '*'
        if fl.get('readOnly'): s += '(ro)'
        if fl.get('optionsKey'): s += '{' + fl['optionsKey'] + '}'
        if fl.get('choices'): s += '{' + '|'.join(f"{c.get('value')}={c.get('label')}" for c in fl['choices']) + '}'
        for k in ('hint', 'placeholder', 'defaultValue', 'group', 'suffix', 'min', 'max', 'total', 'align', 'tone', 'format'):
            if fl.get(k) not in (None, U, ''):
                s += f' {k}={fl[k]}'
        out.append(s)
    return out


print('#', v['slug'], '|', v.get('recordsLabel'), '|', v.get('tableCaption'))
print('flags', {k: v[k] for k in ('canCreate', 'canEdit', 'canDelete', 'canCopy', 'columnFilters', 'defaultTab', 'createPanelId', 'hideRecords') if v.get(k) != U})
print('COLUMNS:'); [print('  ', c) for c in f(v.get('columns'))]
fl = v.get('filters') or {}
if isinstance(fl, dict):
    print('FILTERS:', fl.get('title'), '| note:', fl.get('note'))
    [print('  ', c) for c in f(fl.get('fields'))]
    if fl.get('actions'): print('  filter actions', json.dumps(fl['actions'], ensure_ascii=False))
    rest = {k: x for k, x in fl.items() if k not in ('title', 'note', 'fields', 'actions', 'loadLabel')}
    if rest: print('  filters rest', json.dumps(rest, ensure_ascii=False)[:1500])
print('ROW ACTIONS:', json.dumps(v.get('rowActions'), ensure_ascii=False))
data = v.get('data') or {}
for k, x in data.items():
    print('DATA', k, json.dumps(x, ensure_ascii=False)[:300])
for p in d['props'].get('panels') or []:
    print('\n## PANEL', json.dumps({k: x for k, x in p.items() if k not in ("headerFields", "sections", "fields", "columns", "listColumns", "options", "data")}, ensure_ascii=False)[:2500])
    for k in ('headerFields', 'fields'):
        if p.get(k):
            print(' ', k); [print('    ', c) for c in f(p[k])]
    if p.get('listColumns'): print('  listColumns', f(p['listColumns']))
    if p.get('columns'): print('  columns', f(p['columns']))
    for sec in p.get('sections') or []:
        print('  SECTION', json.dumps({k: x for k, x in sec.items() if k not in ('columns', 'fields')}, ensure_ascii=False)[:1200])
        [print('     ', c) for c in f(sec.get('columns'))]
        [print('     f:', c) for c in f(sec.get('fields'))]
