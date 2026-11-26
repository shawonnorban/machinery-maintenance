import Link from "next/link";
import { ChevronRight } from "lucide-react";
import { cn } from "@/lib/utils";
import { moduleTone } from "@/lib/module-tones";
import { MenuIcon } from "@/components/layout/menu-icon";
import { getT } from "@/lib/i18n-server";

/**
 * Brickland's page header (AnnotechERP UI parity): Home › Modules › Module ›
 * Screen, the screen's icon on its module's colour, the title with its
 * "NN of MM" place in the module, and one line of description.
 *
 * `context` is what `screenContext(url)` returns; a page the menu does not
 * know (a detail page, say) passes `title` and `breadcrumb` itself.
 *
 * @param {{
 *   context?: { module: object, screen: object, index: number, total: number } | null,
 *   title?: string, description?: string, icon?: string, moduleCode?: string,
 *   breadcrumb?: { label: string, href?: string }[], actions?: React.ReactNode, locale?: string,
 * }} props
 */
async function ErpPageHeader({ context, title, description, icon, moduleCode, breadcrumb, actions, locale = "en" }) {
  const t = await getT("nav");
  const bangla = locale === "bn";
  const name = (node) => (bangla && node?.name_bn) || node?.name;
  const mod = context?.module;
  const tone = moduleTone(moduleCode ?? mod?.code);
  const heading = title ?? name(context?.screen);
  const crumbs = breadcrumb ?? [{ label: t("home"), href: "/" }, { label: t("modules") }, ...(mod ? [{ label: name(mod) }] : []), { label: heading }];

  return (
    <div className="flex flex-col gap-3 pb-5">
      <nav aria-label="Breadcrumb" className="flex flex-wrap items-center gap-1.5 text-[13px] text-foreground-muted">
        {crumbs.map((crumb, index) => (
          <span key={`${crumb.label}-${index}`} className="flex items-center gap-1.5">
            {index > 0 ? <ChevronRight className="size-3.5 text-foreground-subtle" /> : null}
            {crumb.href ? (
              <Link href={crumb.href} className="hover:text-foreground">
                {crumb.label}
              </Link>
            ) : (
              <span className={index === crumbs.length - 1 ? "text-foreground" : undefined}>{crumb.label}</span>
            )}
          </span>
        ))}
      </nav>

      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="flex min-w-0 items-start gap-4">
          <span className={cn("flex size-11 shrink-0 items-center justify-center rounded-lg [&_svg]:size-5", tone.tile)}>
            <MenuIcon name={icon ?? context?.screen?.icon} />
          </span>
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-2.5">
              <h1 className="text-[26px] leading-tight font-bold tracking-tight text-foreground">{heading}</h1>
              {context ? (
                <span className="rounded-full border border-border bg-surface-muted px-2 py-0.5 text-xs font-semibold text-foreground-muted tabular-nums">
                  {t("screen_of", { current: String(context.index).padStart(2, "0"), last: context.total })}
                </span>
              ) : null}
            </div>
            {description ? <p className="mt-1 text-sm text-foreground-muted">{description}</p> : null}
          </div>
        </div>
        {actions ? <div className="flex flex-wrap items-center gap-2">{actions}</div> : null}
      </div>
    </div>
  );
}

export { ErpPageHeader };
