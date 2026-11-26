#!/usr/bin/env bash
# Full re-crawl in 4 parallel workers, split by module (each worker writes only its
# own modules' files): refresh every screen, then the dialog/validation pass.
# Run from the project root:  bash AnnotechERP/tools/erp_crawler/run_parallel.sh
set -u
cd "$(dirname "$0")/../../.."
export ERP_AUDIT_ENV="${ERP_AUDIT_ENV:-$PWD/.annotech-erp-audit.env}"
CRAWL=AnnotechERP/tools/erp_crawler/crawl.mjs
OUTDIR=AnnotechERP/tools/erp_crawler/out
WORKERS=(
  "merchandising,sample,crm-marketing,commercial,ie"
  "knitting,dyeing,dyeing-lab,cutting"
  "hr-payroll,accounting,general-store,sales-delivery,planning,fabric-planning,dyeing-finishing"
  "garments,inventory,gate-pass,material-procurement,security,settings,approval-center,company-management,executive-dashboard,buyer-portal,ai-file-import,bi-analytics"
)
i=0
for mods in "${WORKERS[@]}"; do
  i=$((i + 1))
  (
    node "$CRAWL" --screens --refresh --allow-load-posts --only "$mods" --delay 500 > "$OUTDIR/worker$i.log" 2>&1
    node "$CRAWL" --dialogs --allow-writes --only "$mods" --delay 400 >> "$OUTDIR/worker$i.log" 2>&1
    echo "worker$i finished" >> "$OUTDIR/worker$i.log"
  ) &
  sleep 20 # stagger the logins
done
wait
for n in 1 2 3 4; do
  echo "worker$n: ok=$(grep -c '^  ok' "$OUTDIR/worker$n.log") dlg=$(grep -c '^  dlg' "$OUTDIR/worker$n.log") err=$(grep -c '^  ERR' "$OUTDIR/worker$n.log")"
done
