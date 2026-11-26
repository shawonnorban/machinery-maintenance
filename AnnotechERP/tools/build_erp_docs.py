"""Builds the AnnotechERP implementation docs from the crawled Brickland ERP.

Run from anywhere:  python AnnotechERP/tools/build_erp_docs.py [--check]

Source of truth
- tools/erp_crawler/out/menu.json + out/fields/<module>/<screen>.json (crawler
  output, gitignored). The structure is snapshotted to
  tools/source/brickland_screens.json so the docs can be rebuilt without out/.
- MODULES below: every Brickland module, its implementation phase/order, plus the
  kept Machinery module and the MIS module (Erp.xlsx management reports).

What it writes
- AnnotechERP/<NN>_<Module>/00_Index.md and one <SS>_<Group>.md per sidebar group
  (MASTER DATA always first). Only the <!-- AUTO:x:start/end --> blocks are
  rewritten on later runs; everything else in a file is hand-written and kept.
- The status/phase table in AnnotechERP/00_Index.md.
Checks: every crawled screen lands in exactly one file; every as-built glob
matches files; every relative markdown link resolves.
"""
import glob as globmod
import json
import re
import sys
from collections import OrderedDict
from pathlib import Path

sys.stdout.reconfigure(encoding='utf-8')
ROOT = Path(__file__).resolve().parents[2]
DOCS = ROOT / 'AnnotechERP'
OUT = DOCS / 'tools' / 'erp_crawler' / 'out'
SNAPSHOT = DOCS / 'tools' / 'source' / 'brickland_screens.json'
CHECK_ONLY = '--check' in sys.argv

DONE, PARTIAL, TODO = '✅ সম্পন্ন', '🟡 আংশিক', '⬜ শুরু হয়নি'
BN = str.maketrans('0123456789', '০১২৩৪৫৬৭৮৯')


def bn(n):
    return str(n).translate(BN)


def nfix(t):
    return t.replace('\u09dc', '\u09a1\u09bc').replace('\u09dd', '\u09a2\u09bc').replace('\u09df', '\u09af\u09bc')


# ---------------------------------------------------------------------------
# Modules in implementation order. (folder no, slug, title, phase)
# Phase 1 (masters) additionally pulls every module's MASTER DATA group forward.
# ---------------------------------------------------------------------------
PHASES = OrderedDict([
    (0, 'Machinery (বিদ্যমান, অপরিবর্তিত)'),
    (1, 'ভিত্তি ও মাস্টার ডাটা'),
    (2, 'বিক্রয় ও মার্চেন্ডাইজিং'),
    (3, 'সাপ্লাই ও স্টোর'),
    (4, 'প্ল্যানিং ও IE'),
    (5, 'প্রোডাকশন'),
    (6, 'শিপমেন্ট ও কমার্শিয়াল'),
    (7, 'ফাইন্যান্স ও HR'),
    (8, 'পোর্টাল, AI ও অ্যানালিটিক্স'),
    (9, 'MIS (Erp.xlsx — ম্যানেজমেন্ট রিপোর্ট)'),
])
MODULES = [
    ('00', 'machinery', 'Machinery Maintenance', 0),
    ('01', 'company-management', 'Company Management', 1),
    ('02', 'security', 'Access Control', 1),
    ('03', 'settings', 'Settings', 1),
    ('04', 'approval-center', 'Approval Center', 1),
    ('05', 'crm-marketing', 'CRM & Marketing', 2),
    ('06', 'merchandising', 'Merchandise Desk', 2),
    ('07', 'sample', 'Prototype Desk', 2),
    ('08', 'material-procurement', 'Material Procurement', 3),
    ('09', 'inventory', 'Store & Material', 3),
    ('10', 'general-store', 'General Store', 3),
    ('11', 'gate-pass', 'Gate Pass', 3),
    ('12', 'planning', 'Capacity Planning', 4),
    ('13', 'fabric-planning', 'Fabric Requirement', 4),
    ('14', 'ie', 'Work Study', 4),
    ('15', 'knitting', 'Knit Production', 5),
    ('16', 'dyeing-lab', 'Colour Lab', 5),
    ('17', 'dyeing', 'Dye House', 5),
    ('18', 'dyeing-finishing', 'Finishing Line', 5),
    ('19', 'cutting', 'Cutting Floor', 5),
    ('20', 'garments', 'Assembly Floor', 5),
    ('30', 'production-tracking', 'Production Tracking', 5),
    ('21', 'sales-delivery', 'Sales & Shipment', 6),
    ('22', 'commercial', 'Trade & Export', 6),
    ('23', 'accounting', 'Finance & Ledger', 7),
    ('24', 'hr-payroll', 'HR & Payroll', 7),
    ('25', 'buyer-portal', 'Buyer Gateway', 8),
    ('26', 'ai-file-import', 'AI File & PO Import', 8),
    ('27', 'executive-dashboard', 'Command Center', 8),
    ('28', 'bi-analytics', 'Insight Studio', 8),
    ('29', 'mis', 'MIS Reports', 9),
    # The user's other ERP, Norban (http://127.0.0.1:8000, 2026-11-17): four of its modules
    # built as new modules. Screens from tools/source/norban_screens.json, fields from
    # erp_crawler/norban_fields.py (out/fields/norban/).
    ('31', 'norban-dev-sample', 'Dev Sample Status', 2),
    ('32', 'norban-order-summary', 'Order Summary', 2),
    ('33', 'norban-knit-woven', 'Knit Woven', 2),
    ('34', 'norban-accessories', 'Accessories Pre-Budget', 2),
]
NORBAN = {slug for _, slug, _, _ in MODULES if slug.startswith('norban-')}
NORBAN_SCREENS = DOCS / 'tools' / 'source' / 'norban_screens.json'
MOD = {slug: (no, title, phase) for no, slug, title, phase in MODULES}

# The kept machinery system: hand-defined groups with as-built globs (directories
# ending in '/' are summarised as file counts).
FE = 'frontend/src'
MACHINERY_GROUPS = [
    ('Assets & Machine Register', ['app/Modules/Asset/', f'{FE}/app/(app)/assets/', f'{FE}/components/assets/', 'tests/Feature/Asset/']),
    ('Preventive Maintenance', ['app/Modules/Maintenance/', 'app/Modules/Calendar/', f'{FE}/app/(app)/maintenance/', f'{FE}/components/maintenance/', 'tests/Feature/Maintenance/']),
    ('Breakdowns', ['app/Modules/Breakdown/', f'{FE}/app/(app)/breakdowns/', f'{FE}/components/breakdowns/', 'tests/Feature/Breakdown/']),
    ('Work Orders', ['app/Modules/WorkOrder/', f'{FE}/app/(app)/work-orders/', f'{FE}/components/work-orders/', 'tests/Feature/WorkOrder/']),
    ('Spare Parts & Inventory', ['app/Modules/Inventory/', f'{FE}/app/(app)/inventory/', f'{FE}/components/inventory/', 'tests/Feature/Inventory/']),
    ('Metering', ['app/Modules/Metering/', f'{FE}/app/(app)/metering/', f'{FE}/components/metering/', 'tests/Feature/Metering/']),
    ('Technicians & Teams', [f'{FE}/app/(app)/technicians/', f'{FE}/app/(app)/teams/', f'{FE}/components/technicians/', f'{FE}/components/teams/']),
    ('Vendors, Warranty & Service Contracts', ['app/Modules/Vendor/', f'{FE}/app/(app)/vendors/', f'{FE}/components/vendors/', 'tests/Feature/Vendor/']),
    ('Maintenance Cost & Analytics', ['app/Modules/Costing/', 'app/Modules/Analytics/', f'{FE}/app/(app)/scan/']),
]

# sidebar section headings (not groups); a screen listed right under one belongs to
# a group named after its module
SECTIONS = {'overview', 'system administration', 'human resource', 'sales & merchandising', 'planning', 'production',
            'inventory & stores', 'finance & accounting', 'commercial & export', 'industrial engineering', 'laboratory'}
JUNK_GROUP = re.compile(r'^$|key figures|failed to fetch|modules all|^\d+ |low stock items|recent transactions|notifications & alerts', re.I)


def clean_name(s):
    s = re.sub(r'\s+', ' ', s or '').strip()
    s = re.sub(r'\s+\d[\d,.]*\s*(kg|rolls?|pcs|%|৳)?(\s.*)?$', '', s) if re.search(r'\d', s) and len(s) > 40 else s
    return re.sub(r'\s+\d+$', '', s)


def slugify(s):
    return re.sub(r'[^A-Za-z0-9]+', '_', s).strip('_')[:50] or 'General'


# ---------------------------------------------------------------------------
# Load crawl (or snapshot)
# ---------------------------------------------------------------------------
def load_screens():
    menu_file = OUT / 'menu.json'
    if not menu_file.exists():
        return json.loads(SNAPSHOT.read_text(encoding='utf-8')), False
    menu = json.loads(menu_file.read_text(encoding='utf-8'))
    module_titles = {t.lower() for _, _, t, _ in MODULES}
    best = OrderedDict()
    for m in menu:
        if not m.get('href'):
            continue
        path = re.sub(r'^https?://[^/]+', '', m['href']).split('?')[0].rstrip('/')
        parts = path.strip('/').split('/')
        if len(parts) < 3 or parts[0] != 'm':
            continue
        trail = [t for t in m.get('path', []) if t != 'Module navigation' and t.lower() not in module_titles]
        group = trail[-1] if trail else ''
        if group.lower() in SECTIONS:
            group = MOD.get(parts[1], ('', parts[1], 0))[1]
        junk = bool(JUNK_GROUP.search(group)) or group.isupper() and len(group) > 40
        cand = {'module': parts[1], 'path': path, 'menu_name': clean_name(m['name']), 'group': '' if junk else group}
        if path not in best or (best[path]['group'] == '' and cand['group']):
            best[path] = cand
    screens = []
    for s in best.values():
        fj = OUT / 'fields' / slugify_dir(s['module']) / f"{slugify_dir('_'.join(s['path'].strip('/').split('/')[2:]))}.json"
        data = json.loads(fj.read_text(encoding='utf-8')) if fj.exists() else None
        title = ''
        if data and data.get('views'):
            title = data['views'][0].get('title', '')
        s['name'] = title if title and len(title) <= 70 else s['menu_name']
        s['group'] = s['group'] or 'GENERAL'
        s['data_file'] = str(fj.relative_to(OUT)).replace('\\', '/') if data else None
        screens.append(s)
    return screens, True


def slugify_dir(s):
    return re.sub(r'[^\w]+', '_', s, flags=re.U).strip('_')[:80] or 'screen'


SCREENS, LIVE = load_screens()
if LIVE and not CHECK_ONLY:
    SNAPSHOT.parent.mkdir(parents=True, exist_ok=True)
    SNAPSHOT.write_text(json.dumps([{k: s[k] for k in ('module', 'group', 'name', 'menu_name', 'path')} for s in SCREENS], ensure_ascii=False, indent=1), encoding='utf-8')
BRICKLAND_TOTAL = len(SCREENS)
for s in json.loads(NORBAN_SCREENS.read_text(encoding='utf-8')):
    fj = OUT / 'fields' / 'norban' / f"{s['probe']}.json"
    SCREENS.append({**s, 'data_file': f"fields/norban/{s['probe']}.json" if fj.exists() else None})

unknown = sorted({s['module'] for s in SCREENS} - set(MOD))
if unknown:
    raise SystemExit(f'Crawled modules missing from MODULES: {unknown}')


# ---------------------------------------------------------------------------
# Group files
# ---------------------------------------------------------------------------
def is_master(g):
    return bool(re.search(r'master data|^master', g, re.I))


groups = OrderedDict()  # (module) -> OrderedDict(group -> [screens])
for s in SCREENS:
    groups.setdefault(s['module'], OrderedDict()).setdefault(s['group'], []).append(s)


def ordered_groups(mod):
    g = groups.get(mod, OrderedDict())
    keys = sorted(g, key=lambda k: (0 if is_master(k) else 1, list(g).index(k)))
    return [(k, g[k]) for k in keys]


def folder(mod):
    no, title, _ = MOD[mod]
    return f"{no}_{slugify(title)}"


FILES = []  # (module, idx, group, screens, path)
for no, mod, title, phase in MODULES:
    if mod in ('machinery', 'mis'):
        continue
    for i, (g, scr) in enumerate(ordered_groups(mod), 1):
        p = DOCS / folder(mod) / f"{i:02d}_{slugify(g.title())}.md"
        FILES.append((mod, i, g, scr, p))
for i, (g, globs) in enumerate(MACHINERY_GROUPS, 1):
    FILES.append(('machinery', i, g, globs, DOCS / folder('machinery') / f"{i:02d}_{slugify(g)}.md"))


# ---------------------------------------------------------------------------
# Renderers
# ---------------------------------------------------------------------------
def esc(t):
    return (t or '').replace('|', '\\|').replace('\n', ' ')


def screen_detail(s):
    lines = [f"#### {esc(s['name'])}", '', f"`{s['path']}`" + (f" — মেনুতে: {esc(s['menu_name'])}" if s['menu_name'] != s['name'] else '')]
    data = None
    if s.get('data_file'):
        data = json.loads((OUT / s['data_file']).read_text(encoding='utf-8'))
    if not data:
        return '\n'.join(lines + ['', '_লাইভ ডাটা নেই (ক্রল হয়নি)।_', ''])
    for v in data.get('views', []):
        desc = re.sub(r'\s*\d+ of \d+\s*PostgreSQL\s*', ' ', v.get('description', '')).strip()
        desc = desc[len(v.get('title', '')):].strip() if desc.startswith(v.get('title', '')) else desc
        tab = v.get('tab') or ''
        lines.append('')
        lines.append(f"**ট্যাব: {esc(tab)}**" if tab else '**ভিউ**')
        if desc:
            lines.append(f"> {esc(desc)}")
        if v.get('fields'):
            lines += ['', '| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |', '|---|---|---|---|']
            for f in v['fields']:
                opts = ', '.join(o for o in f.get('options', []) if not o.startswith('--'))[:120] or f.get('placeholder', '')
                lines.append(f"| {esc(f['label']) or '—'} | {f['type']} | {'✔' if f.get('required') else ''} | {esc(opts)} |")
        for g in v.get('grids', []):
            lines.append(f"- গ্রিড কলাম: {esc(', '.join(g))}")
        if v.get('sections'):
            lines.append(f"- সেকশন: {esc(', '.join(v['sections'][:15]))}")
        btns = [b for b in v.get('buttons', []) if not re.fullmatch(r'\d+|Records \d+', b)]
        if btns:
            lines.append(f"- বাটন/অ্যাকশন: {esc(', '.join(btns[:20]))}")
    for d in data.get('dialogs', []) or []:
        lines += ['', f"**ডায়ালগ: {esc(d.get('opener', ''))}**" + (' (মডাল)' if d.get('inDialog') else '')]
        if d.get('error'):
            lines.append(f"- খোলা যায়নি: {esc(d['error'])}")
            continue
        if d.get('fields'):
            lines += ['', '| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |', '|---|---|---|---|']
            for f in d['fields']:
                opts = ', '.join(o for o in f.get('options', []) if not o.startswith('--'))[:120]
                lines.append(f"| {esc(f['label']) or '—'} | {f['type']} | {'✔' if f.get('required') else ''} | {esc(opts)} |")
        val = d.get('validation') or []
        if val:
            msgs = [v['message'] for v in val if 'message' in v]
            inv = [v['invalid'] for v in val if 'invalid' in v]
            if msgs:
                lines.append(f"- খালি Save-এ মেসেজ: {esc(' · '.join(msgs[:10]))}")
            if inv:
                lines.append(f"- invalid চিহ্নিত ফিল্ড: {len(inv)}টা")
    lines.append('')
    return '\n'.join(lines)


def block_screens(scr):
    rows = ['| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |', '|---|---|---|---|---|---|']
    for i, s in enumerate(scr, 1):
        nviews = nfields = ndlg = 0
        if s.get('data_file'):
            d = json.loads((OUT / s['data_file']).read_text(encoding='utf-8'))
            nviews = len(d.get('views', []))
            nfields = sum(len(v.get('fields', [])) for v in d.get('views', []))
            ndlg = len(d.get('dialogs', []) or [])
        rows.append(f"| {i} | {esc(s['name'])} | `{s['path']}` | {nviews} | {nfields} | {ndlg} |")
    return '\n'.join(rows)


def block_detail(scr):
    return '\n'.join(screen_detail(s) for s in scr).rstrip()


GLOB_PROBLEMS = []


def block_impl(globs, rel):
    if not globs:
        return '_এখনো কোনো কোড নেই।_'
    out = ['বিদ্যমান কোড (জেনারেটর ডিস্ক থেকে মিলিয়ে লেখে):', '']
    for g in globs:
        if g.endswith('/'):
            d = ROOT / g
            if not d.exists():
                GLOB_PROBLEMS.append(f'{rel}: directory missing -> {g}')
                continue
            n = sum(1 for f in d.rglob('*') if f.is_file())
            out.append(f'- `{g}` ({bn(n)}টা ফাইল)')
        else:
            hits = [h for h in globmod.glob(str(ROOT / g), recursive=True) if Path(h).is_file()]
            if not hits:
                GLOB_PROBLEMS.append(f'{rel}: glob matched nothing -> {g}')
            out += [f"- `{Path(h).relative_to(ROOT).as_posix()}`" for h in sorted(hits)]
    return '\n'.join(out)


def put_block(text, name, body):
    start, end = f'<!-- AUTO:{name}:start -->', f'<!-- AUTO:{name}:end -->'
    return re.sub(re.escape(start) + r'.*?' + re.escape(end), lambda m: f'{start}\n{body}\n{end}', text, flags=re.S)


PH = '_বাস্তবায়নের আগে লেখা হবে।_'
GROUP_TEMPLATE = f'''# {{title}}

> **স্ট্যাটাস:** {{status}}
> **মডিউল:** [{{mtitle}}](00_Index.md) · **ফেজ {{phase_no}}:** {{phase}} · **কী:** `{{key}}`

**⬅ ফিরে যান:** [{{mtitle}}](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন ({{source}} থেকে)

<!-- AUTO:screens:start -->
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

{PH}

## ৪. ডাটা মডেল (Entity ও Field)

{PH}

## ৫. Workflow / Status ও Business Rules

{PH}

## ৬. API ও পারমিশন

{PH}

## ৭. Frontend পেজ (লাইট থিম)

{PH}

## ৮. Implementation Files

<!-- AUTO:impl:start -->
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

{PH}

## ৯. টেস্ট ও যাচাই

{PH}

## ১০. খোলা প্রশ্ন

_নেই এখনো।_

## ১১. As-built নোট

_বাস্তবায়নের পরে লেখা হবে।_
'''

MACHINERY_TEMPLATE = '''# {title}

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Machinery Maintenance](00_Index.md) · **ফেজ 0:** বিদ্যমান সিস্টেম, অপরিবর্তিত থাকবে · **কী:** `{key}`

**⬅ ফিরে যান:** [Machinery Maintenance](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. বিবরণ

বর্তমান machinery-maintenance সিস্টেমের অংশ। নতুন ERP-তে এটা অপরিবর্তিত থাকবে, শুধু লাইট থিম আর নতুন নেভিগেশনে বসবে।

## ২. Implementation Files

<!-- AUTO:impl:start -->
<!-- AUTO:impl:end -->

## ৩. নতুন ERP-র সাথে সংযোগ

_বাস্তবায়নের সময় লেখা হবে (যেমন Company Management-এর Machine Master = এখানকার Asset)।_
'''

MODULE_INDEX = '''# {title}

> **ফেজ {phase_no}:** {phase} · **ফোল্ডার:** `{folder}`

**⬅ ফিরে যান:** [AnnotechERP Index](../00_Index.md)

<!-- AUTO:status:start -->
<!-- AUTO:status:end -->

## নোট

_এই মডিউলের ক্রস-কাটিং সিদ্ধান্ত এখানে লেখা হবে।_
'''

STATUS_RE = re.compile(r'^> \*\*স্ট্যাটাস:\*\* (.+)$', re.M)
PENDING = {}


def write(p, text):
    text = nfix(text)
    old = p.read_text(encoding='utf-8') if p.exists() else None
    if old != text:
        PENDING[p] = text


file_status = {}
for mod, i, g, payload, p in FILES:
    no, mtitle, phase = MOD[mod]
    key = f'{no}/{i:02d}'
    if mod == 'machinery':
        text = p.read_text(encoding='utf-8') if p.exists() else MACHINERY_TEMPLATE.format(title=g, key=key)
        text = put_block(text, 'impl', block_impl(payload, p.relative_to(DOCS).as_posix()))
    else:
        text = p.read_text(encoding='utf-8') if p.exists() else GROUP_TEMPLATE.format(
            title=g.title() if g.isupper() else g, status=TODO, mtitle=mtitle, phase_no=bn(1 if is_master(g) else phase),
            phase=PHASES[1 if is_master(g) else phase], key=key, source='Norban ERP' if mod in NORBAN else 'Brickland ERP')
        if LIVE:
            text = put_block(text, 'screens', block_screens(payload))
            text = put_block(text, 'detail', block_detail(payload))
        text = put_block(text, 'impl', '_এখনো কোনো কোড নেই।_' if '<!-- AUTO:impl:start -->\n<!-- AUTO:impl:end -->' in text else re.search(r'<!-- AUTO:impl:start -->\n(.*?)\n<!-- AUTO:impl:end -->', text, re.S).group(1))
    m = STATUS_RE.search(text)
    file_status[p] = m.group(1).strip() if m else TODO
    write(p, text)

# MIS module placeholder
mis_dir = DOCS / folder('mis')
mis_file = mis_dir / '01_Management_Reports.md'
if not mis_file.exists():
    write(mis_file, '''# Management Reports (MIS)

> **স্ট্যাটাস:** ⬜ শুরু হয়নি
> **মডিউল:** [MIS Reports](00_Index.md) · **ফেজ ৯:** MIS (Erp.xlsx — ম্যানেজমেন্ট রিপোর্ট) · **কী:** `29/01`

**⬅ ফিরে যান:** [MIS Reports](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. উৎস

`Erp.xlsx`-এর শুধু MIS অংশ (ম্যানেজমেন্ট রিপোর্ট)। কোন কোন রিপোর্ট আর প্রতিটার ভেতরের কলাম কী হবে, তা ব্যবহারকারী পরে দেবেন।

## ২. রিপোর্ট তালিকা ও কলাম

_ব্যবহারকারীর কাছ থেকে আসার অপেক্ষায়।_

## ৩. উৎস ডাটা (কোন মডিউল থেকে)

_রিপোর্ট তালিকা আসার পরে লেখা হবে।_
''')
    file_status[mis_file] = TODO
else:
    m = STATUS_RE.search(mis_file.read_text(encoding='utf-8'))
    file_status[mis_file] = m.group(1).strip() if m else TODO


# ---------------------------------------------------------------------------
# Indexes
# ---------------------------------------------------------------------------
def module_rows(mod):
    rows = []
    for fmod, i, g, payload, p in FILES:
        if fmod != mod:
            continue
        n = len(payload) if mod != 'machinery' else '—'
        rows.append((p, g, n))
    if mod == 'mis':
        rows.append((mis_file, 'Management Reports', '—'))
    return rows


for no, mod, title, phase in MODULES:
    idx = DOCS / folder(mod) / '00_Index.md'
    text = idx.read_text(encoding='utf-8') if idx.exists() else MODULE_INDEX.format(title=title, phase_no=bn(phase), phase=PHASES[phase], folder=folder(mod))
    rows = ['| কী | গ্রুপ | স্ক্রিন | স্ট্যাটাস |', '|---|---|---|---|']
    for p, g, n in module_rows(mod):
        key = f"{no}/{p.name[:2]}"
        rows.append(f"| `{key}` | [{esc(g.title() if g.isupper() else g)}]({p.name}) | {bn(n)} | {file_status.get(p, TODO)} |")
    write(idx, put_block(text, 'status', '\n'.join(rows)))

master = DOCS / '00_Index.md'
if master.exists():
    L = [f'**মোট:** {bn(len(MODULES))}টা মডিউল · {bn(len(FILES) + 1)}টা গ্রুপ-ফাইল · Brickland-এর {bn(BRICKLAND_TOTAL)}টা স্ক্রিন, Norban-এর {bn(len(SCREENS) - BRICKLAND_TOTAL)}টা', '']
    L += ['### ফেজ ১-এর আগে: সব মডিউলের MASTER DATA (একসাথে)', '', '| মডিউল | মাস্টার ফাইল | স্ক্রিন | স্ট্যাটাস |', '|---|---|---|---|']
    for mod, i, g, payload, p in FILES:
        if mod != 'machinery' and is_master(g):
            L.append(f"| {MOD[mod][1]} | [{esc(g.title())}]({p.relative_to(DOCS).as_posix()}) | {bn(len(payload))} | {file_status[p]} |")
    L.append('')
    for ph, ptitle in PHASES.items():
        L += [f'### ফেজ {bn(ph)} — {ptitle}', '', '| # | মডিউল | গ্রুপ-ফাইল | স্ক্রিন | সম্পন্ন |', '|---|---|---|---|---|']
        for no, mod, title, phase in MODULES:
            if phase != ph:
                continue
            rows = module_rows(mod)
            done = sum(1 for p, _, _ in rows if file_status.get(p, '').startswith('✅'))
            nscr = sum(len(groups.get(mod, {}).get(g, [])) for g in groups.get(mod, {}))
            L.append(f"| {no} | [{title}]({folder(mod)}/00_Index.md) | {bn(len(rows))} | {bn(nscr) if mod not in ('machinery', 'mis') else '—'} | {bn(done)}/{bn(len(rows))} |")
        L.append('')
    write(master, put_block(master.read_text(encoding='utf-8'), 'status', '\n'.join(L).rstrip()))

# ---------------------------------------------------------------------------
# Write / check, links
# ---------------------------------------------------------------------------
if CHECK_ONLY:
    for p in PENDING:
        print('would change:', p.relative_to(ROOT))
else:
    for p, t in PENDING.items():
        p.parent.mkdir(parents=True, exist_ok=True)
        p.write_text(t, encoding='utf-8', newline='\n')

# group files no longer produced (e.g. a group was renamed): delete when they hold
# no hand-written content yet, otherwise report so nothing written by hand is lost
STALE = []
expected = {p.resolve() for *_, p in FILES} | {mis_file.resolve()}
for md in DOCS.glob('[0-9][0-9]_*/[0-9][0-9]_*.md'):
    if md.name.startswith('00_') or md.resolve() in expected:
        continue
    untouched = md.read_text(encoding='utf-8').count(PH) >= 5
    if untouched and not CHECK_ONLY:
        md.unlink()
        print('removed stale', md.relative_to(DOCS))
    else:
        STALE.append(f'stale file with hand-written content (merge it, then delete): {md.relative_to(DOCS)}')

placed = sum(len(payload) for mod, _, _, payload, _ in FILES if mod != 'machinery')
broken = []
for md in DOCS.rglob('*.md'):
    if 'erp_crawler' in md.parts and 'out' in md.parts:
        continue
    content = PENDING.get(md) or md.read_text(encoding='utf-8')
    for target in re.findall(r'\]\(([^)#\s]+)(?:#[^)]*)?\)', content):
        if re.match(r'^[a-z]+:', target):
            continue
        t = (md.parent / target).resolve()
        if not t.exists() and t not in {k.resolve() for k in PENDING}:
            broken.append(f'{md.relative_to(DOCS)} -> {target}')

print(f'live={LIVE} screens={len(SCREENS)} placed={placed} files={len(FILES)} changed={len(PENDING)}')
problems = GLOB_PROBLEMS + STALE + [f'broken link: {b}' for b in broken]
if placed != len(SCREENS):
    problems.append(f'{len(SCREENS) - placed} screens not placed in any file')
for p in problems:
    print('PROBLEM', p)
if problems or (CHECK_ONLY and PENDING):
    raise SystemExit(1)
