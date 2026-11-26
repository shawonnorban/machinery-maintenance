import json
P = "main button:has(svg.lucide-printer)"
D = "[role=dialog] button:has(svg.lucide-printer)"
s = []
def pr(url, name, sel=P, pre=()):
    s.append({"goto": url}); s.append({"wait": 4000})
    s.extend(pre)
    s.append({"printpdf": sel, "as": name})
pr("/merchandise-desk/budget-sheet?order=Order", "p08-budget")
pr("/prototype-desk/sample-360-view", "p12-s360")
pr("/store-material/in-house-grn?grn=GRN-2026-00001", "p14-grn")
pr("/assets/labels?status=BREAKDOWN", "p16-labels")
pr("/gate-pass/gp-list", "p17-gatepass", pre=[{"click": "tr:has-text('GP-2026-000002')"}, {"wait": 2500}])
pr("/general-store/gs-issue", "p19-gsissue", sel="button:has(svg.lucide-printer)[aria-label$='IS-2026-000001']")
pr("/general-store/gs-current-stock", "p22-gsstock")
pr("/general-store/gs-ledger", "p23-gsledger", pre=[{"pick": "Item", "option": "A4 Paper 80 GSM"}, {"click": "main button:has-text('Search')"}, {"wait": 5000}])
pr("/general-store/gs-req-approved", "p24-gsreq", sel=D, pre=[{"click": "button[aria-label$='REQ-2026-000001']:has(svg.lucide-eye)"}, {"wait": 2500}])
pr("/merchandise-desk/style-development?tab=sheet&style=FLOW%20STYLE-01", "p25-bom", pre=[{"wait": 3000}, {"click": "role=tab[name=/BOM/i]"}, {"wait": 6000}])
pr("/merchandise-desk/style-registration?tab=registration&open=SRQ-2026-000001", "p29-registration", sel=D, pre=[{"wait": 12000}])
json.dump(s, open("plans/pr04.json", "w"), indent=1)
