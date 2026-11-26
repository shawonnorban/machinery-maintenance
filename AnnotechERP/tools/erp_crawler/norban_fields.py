"""Norban probe output -> the docs generator's per-screen field files.

probe.mjs (out/norban/<key>.json: headings, tables, fields, buttons) is
turned into out/fields/norban/<key>.json in the shape build_erp_docs.py reads
for Brickland screens ({views: [{title, fields, grids, sections, buttons}]}).
Norban's page-wide extras (the address-book and signature dialogs, the chat
widget) are dropped, and per-row inputs of a grid (rows[0][x], template[x])
are listed once.

usage: python -I AnnotechERP/tools/erp_crawler/norban_fields.py
"""
import json
import re
from pathlib import Path

HERE = Path(__file__).resolve().parent
SRC = HERE / 'out' / 'norban'
DST = HERE / 'out' / 'fields' / 'norban'
SCREENS = HERE.parent / 'source' / 'norban_screens.json'

NOISE_BUTTONS = {'Save & refresh list', 'Open full address book page', 'Signature 1', 'Signature 2', 'Close', 'Save'}
NOISE_HEADINGS = {'Address Book', 'Signatures'}
NOISE_LABELS = {'Name', 'Email *', 'Signature 1', 'Signature 2', 'None'}


def field_rows(fields):
    out, seen = [], set()
    for f in fields:
        name = f.get('name') or ''
        if name.startswith('template['):
            continue
        m = re.match(r'rows\[\d+\]\[(\w+)\]', name)
        key = m.group(1) if m else name
        label = (f.get('label') or '').strip() or (key.replace('_', ' ').title() if key else '')
        if label in NOISE_LABELS or (key, label) in seen:
            continue
        seen.add((key, label))
        kind = f.get('type') or f.get('tag')
        if f.get('tag') == 'select':
            kind = 'select-multiple' if f.get('type') == 'select-multiple' else 'select'
        out.append({'label': label, 'type': kind, 'required': bool(f.get('req')), 'options': [o for o in (f.get('opts') or []) if o][:12]})
    return out


def convert(key):
    d = json.loads((SRC / f'{key}.json').read_text(encoding='utf-8'))
    headings = [h for h in d.get('headings', []) if h not in NOISE_HEADINGS]
    grids = [t['head'][-1] for t in d.get('tables', []) if t.get('head') and any(t['head'][-1])]
    return {'views': [{
        'title': headings[0] if headings else '',
        'fields': field_rows(d.get('fields', [])),
        'grids': grids,
        'sections': headings[1:16],
        'buttons': [b for b in d.get('buttons', []) if b not in NOISE_BUTTONS],
    }]}


def main():
    DST.mkdir(parents=True, exist_ok=True)
    n = 0
    for s in json.loads(SCREENS.read_text(encoding='utf-8')):
        if (SRC / f"{s['probe']}.json").exists():
            (DST / f"{s['probe']}.json").write_text(json.dumps(convert(s['probe']), ensure_ascii=False, indent=1), encoding='utf-8')
            n += 1
    print(f'wrote {n} screen files to {DST}')


if __name__ == '__main__':
    main()
