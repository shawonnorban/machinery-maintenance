"""Collect every built sub-module's open questions into one register.

Reads section "## ১০. খোলা প্রশ্ন" of each AnnotechERP/<NN>_<Module>/<SS>_<Group>.md
and writes AnnotechERP/90_Open_Questions.md, module by module, each block
linking back to the doc it came from. The docs stay the source: a question
is answered by editing its doc (and its code), then this is run again.

usage: python AnnotechERP/tools/build_open_questions.py
"""
import glob
import os
import re

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(ROOT, '90_Open_Questions.md')
NONE = '_নেই এখনো।_'
BN = str.maketrans('0123456789', '০১২৩৪৫৬৭৮৯')


def title(path):
    """The doc's own title (its first heading) and the key line's `NN/SS`."""
    text = open(path, encoding='utf-8').read()
    head = re.search(r'^# (.+)$', text, re.M)
    key = re.search(r'\*\*কী:\*\* `([^`]+)`', text)
    return text, (head.group(1).strip() if head else os.path.basename(path)), (key.group(1) if key else '')


def module_name(folder):
    idx = os.path.join(ROOT, folder, '00_Index.md')
    if os.path.exists(idx):
        head = re.search(r'^# (.+)$', open(idx, encoding='utf-8').read(), re.M)
        if head:
            return head.group(1).strip()
    return folder


def main():
    blocks = []
    count = 0
    for path in sorted(glob.glob(os.path.join(ROOT, '[0-9][0-9]_*', '[0-9][0-9]_*.md'))):
        text, name, key = title(path)
        m = re.search(r'## ১০\. খোলা প্রশ্ন\n(.*?)\n## ১১', text, re.S)
        body = m.group(1).strip() if m else ''
        # A section already answered in its doc ("_সমাধান হয়েছে…_" alone) is not pending.
        if not body or NONE in body or (body.startswith('_সমাধান হয়েছে') and '?' not in body):
            continue
        folder = os.path.basename(os.path.dirname(path))
        rel = f'{folder}/{os.path.basename(path)}'
        count += 1
        blocks.append((folder, f'### `{key}` [{name}]({rel})\n\n{body}\n'))

    lines = [
        '# খোলা প্রশ্ন (Open Questions)',
        '',
        '> প্রতিটা তৈরি হওয়া সাব-মডিউলের ডকের **"১০. খোলা প্রশ্ন"** অংশ থেকে তোলা, এক জায়গায়।',
        '> উত্তর পেলে মূল ডকে (আর কোডে) সমাধান লিখুন, তারপর আবার চালান: `python AnnotechERP/tools/build_open_questions.py`।',
        '',
        f'**মোট:** {str(count).translate(BN)}টা সাব-মডিউলে প্রশ্ন বাকি।',
        '',
        '**⬅ ফিরে যান:** [AnnotechERP Index](00_Index.md)',
        '',
        '---',
        '',
    ]
    current = None
    for folder, block in blocks:
        if folder != current:
            lines += [f'## {module_name(folder)}', '']
            current = folder
        lines += [block, '']
    open(OUT, 'w', encoding='utf-8', newline='\n').write('\n'.join(lines).rstrip() + '\n')
    print(f'{count} docs -> {os.path.relpath(OUT, os.path.dirname(ROOT))}')


if __name__ == '__main__':
    main()
