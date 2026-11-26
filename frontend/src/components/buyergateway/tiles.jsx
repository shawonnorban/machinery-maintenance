import { Activity, BarChart3, Bell, CircleCheck, ClipboardList, FileInput, ScanLine, SearchCheck, Table2, Eye, Files, FileText, Inbox, KeyRound, LayoutGrid, LayoutPanelTop, MessageSquare, PackageSearch, Palette, SlidersHorizontal, Tags, ThumbsUp, TriangleAlert, Truck, UserPlus, Users, Wallet } from "lucide-react";
import { cn } from "@/lib/utils";

/*
 * The four tiles above a demo-data list (Buyer Gateway 25, AI File & PO
 * Import 26), as Brickland
 * draws them: the first with the screen's icon, then three plain counts.
 */

const ICONS = { users: Users, key: KeyRound, onboard: UserPlus, sliders: SlidersHorizontal, eye: Eye, sample: PackageSearch, truck: Truck, files: Files, inbox: Inbox, tags: Tags, invoice: FileText, wallet: Wallet, message: MessageSquare, alert: TriangleAlert, bell: Bell, thumbs: ThumbsUp, ticket: ClipboardList, chart: BarChart3, palette: Palette, activity: Activity, intake: FileInput, scan: ScanLine, table: Table2, check: SearchCheck, approve: CircleCheck, board: LayoutGrid, compose: LayoutPanelTop };

function Tiles({ icon, items }) {
  const Icon = ICONS[icon] ?? Users;
  return (
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      {items.map((x, i) => (
        <div key={x.label} className="flex items-center gap-3 rounded-lg border border-border bg-surface px-4 py-3.5">
          {i === 0 ? (
            <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-brand-subtle text-brand">
              <Icon className="size-4" aria-hidden />
            </span>
          ) : null}
          <div className="min-w-0">
            <p className="text-xs text-foreground-muted">{x.label}</p>
            <p className={cn("mt-0.5 text-xl font-semibold tabular-nums text-foreground")}>{x.value}</p>
          </div>
        </div>
      ))}
    </div>
  );
}

export { Tiles };
