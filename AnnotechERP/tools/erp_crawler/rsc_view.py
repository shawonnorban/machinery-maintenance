"""Pulls the screen spec ("view" prop) and the step strip out of a decoded RSC payload."""
import json, re, sys
src, out = sys.argv[1], sys.argv[2]
s = open(src, encoding='utf-8').read()
found = {}
steps = []
for line in s.split('\n'):
    m = re.match(r'^([0-9a-z]+):(\[.*)$', line)
    if not m:
        continue
    try:
        v = json.loads(m.group(2))
    except Exception:
        continue
    def walk(x):
        if isinstance(x, dict):
            if 'view' in x and isinstance(x['view'], dict) and 'slug' in x['view']:
                found['view'] = x['view']
                found['props'] = {k: v for k, v in x.items() if k != 'view'}
            for y in x.values():
                walk(y)
        elif isinstance(x, list):
            if len(x) >= 4 and x[0] == '$' and x[1] == 'li' and isinstance(x[3], dict):
                h = json.dumps(x[3], ensure_ascii=False)
                mm = re.search(r'"href": "(/m/[^"]+)"', h)
                tt = re.findall(r'"children": "([^"]+)"', h)
                if mm and tt:
                    steps.append([mm.group(1), tt[-1]])
            for y in x:
                walk(y)
    walk(v)
found['steps'] = steps
open(out, 'w', encoding='utf-8').write(json.dumps(found, ensure_ascii=False, indent=1))
print(out, 'view' in found, len(steps))
