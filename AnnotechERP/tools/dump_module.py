"""Compact per-screen summary of one crawled Brickland module, for writing specs.

    python AnnotechERP/tools/dump_module.py security [screen-substring]

Prints, per screen: tabs, fields (label/type/required/options), grid columns,
action buttons, and every New/Add dialog with its fields and the validation
messages an empty Save produced. Reads tools/erp_crawler/out/fields/<module>/.
"""
import json
import re
import sys
from pathlib import Path

sys.stdout.reconfigure(encoding='utf-8')
OUT = Path(__file__).resolve().parent / 'erp_crawler' / 'out' / 'fields'
NAV_NOISE = re.compile(r'^(Records \d+|\d+|Export|Clear|Search.*|Items per page)$')


def fields_line(fields):
    parts = []
    for f in fields:
        if f['label'].startswith('Search') or f['label'] == 'Items per page':
            continue
        opts = [o for o in f.get('options', []) if not o.startswith('--') and o not in ('Select…', 'All')]
        s = f"{f['label'] or '?'}:{f['type']}{'*' if f.get('required') else ''}"
        if opts:
            s += '[' + '|'.join(opts[:8]) + ('…' if len(opts) > 8 else '') + ']'
        parts.append(s)
    return '; '.join(parts)


def main():
    module = sys.argv[1]
    needle = sys.argv[2].lower() if len(sys.argv) > 2 else ''
    for f in sorted((OUT / module.replace('-', '_')).glob('*.json')):
        d = json.loads(f.read_text(encoding='utf-8'))
        title = d['views'][0].get('title') or f.stem
        if needle and needle not in title.lower() and needle not in f.stem:
            continue
        print(f'\n### {title}  ({f.stem})')
        for v in d['views']:
            tab = v.get('tab') or '-'
            print(f'  [tab {tab}]')
            fl = fields_line(v.get('fields', []))
            if fl:
                print('    fields:', fl)
            for g in v.get('grids', []):
                print('    grid:', ', '.join(g))
            btns = [b for b in v.get('buttons', []) if not NAV_NOISE.match(b)]
            if btns:
                print('    buttons:', ', '.join(btns[:14]))
        for dl in d.get('dialogs') or []:
            print(f"  [dialog {dl.get('opener')}] {fields_line(dl.get('fields', []))}")
            msgs = [x['message'] for x in dl.get('validation') or [] if 'message' in x]
            if msgs:
                print('    validation:', ' | '.join(msgs[:6]))


main()
