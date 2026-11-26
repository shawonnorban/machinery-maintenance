import * as lucide from "lucide-react";

/**
 * A sidebar icon named by the menu tree (AnnotechERP 02/01) — the menu stores
 * a lucide icon name ("Building2"), so an admin can change it without a
 * deploy. Looked up among the named exports, which also carry lucide's older
 * alias names ("CheckCircle2"); an unknown name falls back to a plain dot.
 */
function MenuIcon({ name }) {
  const Icon = (name && (lucide.icons[name] ?? lucide[name])) || lucide.icons.Circle;

  return <Icon />;
}

export { MenuIcon };
