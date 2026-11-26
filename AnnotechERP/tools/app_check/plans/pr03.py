import json
P = "main button:has(svg.lucide-printer)"
D = "[role=dialog] button:has(svg.lucide-printer)"
s = []
def pr(url, name, sel=P, pre=()):
    s.append({"goto": url}); s.append({"wait": 4000})
    s.extend(pre)
    s.append({"printpdf": sel, "as": name})
pr("/work-study/gmt-item-specification", "p01-itemlist")
pr("/capacity-planning/master-plan?plan=MP-2026120001&v=1", "p02-masterplan")
pr("/merchandise-desk/accessories-pr?tab=entry&ref=PRQ-ACC-2026100001", "p06-accreq")
pr("/merchandise-desk/purchase-fabric-pr?tab=entry&ref=PRQ-FAB-2026100001", "p07-pfab")
pr("/merchandise-desk/budget-sheet?order=Order", "p08-budget")
pr("/merchandise-desk/sample-production?batch=SMB-00001", "p09-samplecards")
pr("/prototype-desk/sample-360-view", "p12-s360")
pr("/prototype-desk/bundle-movement?tab=lookup", "p13-bundles")
pr("/store-material/in-house-grn?grn=GRN-2026-00001", "p14-grn")
pr("/store-material/store-reports", "p15-storereport")
pr("/assets/labels?status=BREAKDOWN", "p16-labels")
pr("/gate-pass/gp-list", "p17-gatepass", pre=[{"click": "tr:has-text('GP-2026-000002')"}, {"wait": 2500}])
pr("/gate-pass/gp-create", "p18-gpcreate", sel="main button:has(svg.lucide-printer)")
pr("/general-store/gs-issue", "p19-gsissue", sel="button:has(svg.lucide-printer)[aria-label$='IS-2026-000001']")
pr("/general-store/gs-receive", "p20-gsreceive", sel="button:has(svg.lucide-printer)[aria-label$='RC-2026-000001']")
pr("/general-store/gs-transfer", "p21-gstransfer", sel="button:has(svg.lucide-printer)[aria-label$='TR-2026-000001']")
pr("/general-store/gs-current-stock", "p22-gsstock")
pr("/general-store/gs-ledger", "p23-gsledger")
pr("/general-store/gs-req-approved", "p24-gsreq", sel=D, pre=[{"click": "button[aria-label$='REQ-2026-000001']:has(svg.lucide-eye)"}, {"wait": 2500}])
pr("/merchandise-desk/style-development?tab=sheet&style=FLOW%20STYLE", "p25-bom", pre=[{"click": "role=tab[name=/BOM/i]"}, {"wait": 4000}])
pr("/merchandise-desk/tna-followup-marchandising?order=Order", "p26-tna")
s += [{"goto": "/gate-pass/gp-reports"}, {"wait": 4000}, {"pdfhref": "main a:has(svg.lucide-printer)", "as": "p27-gpreport"}]
s += [{"goto": "/general-store/gs-rpt-issue"}, {"wait": 4000}, {"pdfhref": "main a:has(svg.lucide-printer)", "as": "p28-gsreport"}]
s += [{"goto": "/merchandise-desk/style-registration?tab=registration"}, {"wait": 4000}, {"click": "main button:has-text('New')"}, {"wait": 2500},
      {"click": "[role=dialog] button:has-text('Select'):not([disabled])"}, {"wait": 6000}, {"shot": "p29-pre"},
      {"printpdf": "[role=dialog] button:has(svg.lucide-printer)", "as": "p29-registration"}]
json.dump(s, open("plans/pr03.json", "w"), indent=1)
