import json, re, sys
src, out = sys.argv[1], sys.argv[2]
s = open(src, encoding='utf-8').read()
parts = []
for m in re.finditer(r'self\.__next_f\.push\(\[1,("(?:[^"\\]|\\.)*")\]\)', s):
    parts.append(json.loads(m.group(1)))
text = ''.join(parts)
open(out, 'w', encoding='utf-8').write(text)
print(out, len(text))
