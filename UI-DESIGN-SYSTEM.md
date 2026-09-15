# Annotech RMG — UI Design System

The standing brief for every screen in this application. Read it before adding
a page or a component. It is a contract, not a suggestion: a screen that
invents its own spacing, radius or button style is a defect even if it works.

**Direction:** a modern, premium, enterprise SaaS dashboard — clean,
data-dense, consistent, restrained. Visual quality comparable to commercial
admin products such as Vuexy, used as *inspiration only*. No proprietary code,
assets, illustrations or layouts are copied, and the product is never presented
as being one of them.

**Aim for:** modern · premium · clean · professional · enterprise-ready ·
consistent · highly usable · responsive.

**Avoid:** excessive gradients, heavy shadows, over-colourful surfaces, oversized
headings, cluttered layouts, inconsistent spacing, random border radii, and
button styles that differ between pages.

---

## 1. Tokens

Everything visual comes from a token. No component hard-codes a hex value, a
pixel radius, or an arbitrary margin. Tokens live in
`frontend/src/app/globals.css`.

### Colour

| Role | Token | Use |
|---|---|---|
| Primary | `brand`, `brand-hover`, `brand-subtle`, `brand-foreground` | Primary action, active navigation, focus ring |
| Secondary | `surface`, `surface-muted`, `surface-raised` | Panels, table headers, hover fills |
| Success | `success`, `success-subtle` | Completed, approved, passed |
| Warning | `warning`, `warning-subtle` | Grace, pending, rework |
| Danger | `danger`, `danger-subtle` | Rejected, suspended, destructive actions |
| Info | `info`, `info-subtle` | Neutral notices, draft states |
| Background | `background` | Page canvas |
| Surface | `surface` | Cards, sidebar, topbar |
| Border | `border`, `border-strong` | Dividers, input outlines |
| Text | `foreground`, `foreground-muted`, `foreground-subtle` | Primary / secondary / muted text |

Colour carries meaning on a factory floor. Amber and red are reserved for real
production states — rework, reject, overdue, suspended — and are never
decorative. Every status is stated in words as well as colour, because colour
alone is unreadable to a colour-blind user and meaningless on a monochrome
wall display.

Contrast must meet WCAG AA: 4.5:1 for body text, 3:1 for large text and for
meaningful non-text elements.

### Typography

One family, seven roles. Never invent an eighth.

| Role | Size / weight | Used for |
|---|---|---|
| Page title | `text-2xl font-semibold tracking-tight` | One per page, in `PageHeader` |
| Section title | `text-lg font-semibold` | Major divisions within a page |
| Card title | `text-sm font-semibold` | Card headers |
| Body | `text-sm` | Default |
| Table text | `text-sm` | Cell content |
| Label | `text-sm font-medium` | Form labels |
| Small / helper | `text-xs text-foreground-muted` | Hints, captions, metadata |

Numbers compared down a column use `.tabular` (`font-variant-numeric:
tabular-nums`). Production counts, targets and DHU percentages are read at a
glance, often from a distance.

### Spacing

A 4px scale, expressed through Tailwind's spacing utilities. Permitted steps:
`1, 1.5, 2, 3, 4, 5, 6, 8, 10, 12, 16`. Nothing else.

- Page padding: `p-6` (`p-4` below `sm`)
- Card padding: `p-5`
- Gap between cards: `gap-5`
- Gap between form fields: `gap-4`
- Table cell padding: `px-4 py-3`

### Radius

| Token | Value | Use |
|---|---|---|
| `rounded-sm` | 8px | Inputs, buttons, menu items |
| `rounded-sm` | 12px | Cards, panels, modals |
| `rounded-full` | — | Badges, avatars, pills only |

Nothing else. A `rounded-sm` in a new component is a bug.

### Elevation

Subtle. Depth comes from borders and surface colour first, shadow second.

| Token | Use |
|---|---|
| `shadow-xs` | Resting cards |
| `shadow-sm` | Hovered cards, sticky topbar |
| `shadow-md` | Dropdowns, popovers |
| `shadow-lg` | Modals, mobile drawer |

No shadow larger than `lg`. No coloured shadows.

### Motion

150–200ms, ease-out, on `colors`, `opacity` and `transform` only. Never animate
layout. Everything must respect `prefers-reduced-motion`.

---

## 2. Layout

```
┌──────────┬────────────────────────────────────────┐
│ Sidebar  │ Topbar                                 │
│  logo    ├────────────────────────────────────────┤
│  company │ Breadcrumb                             │
│  nav     │ Page title            [page actions]   │
│  groups  │                                        │
│  ...     │ Content                                │
│  user    │                                        │
└──────────┴────────────────────────────────────────┘
```

- **Sidebar** — fixed, 260px expanded / 72px collapsed. Logo, company context,
  grouped navigation with icons, active state, collapse toggle, user section.
  Collapsed mode shows icons with tooltips. Below `lg` it becomes an overlay
  drawer with a backdrop.
- **Topbar** — sticky. Sidebar toggle, breadcrumb, search where useful,
  notifications, theme toggle, profile menu.
- **Content** — `max-w-7xl`, `p-6`, `space-y-6`.

Two shells, one design system: `AppShell` for the tenant application and
`PlatformShell` for the superadmin console. The console is deliberately
distinguishable at a glance — someone who can suspend a factory's account must
never think they are inside that factory's own application.

---

## 3. Navigation

Driven by the API's `modules` payload, which is already filtered by both
entitlement and permission. Nothing is hidden here that the server would allow,
and nothing is shown that it would refuse — a menu item never leads to a 403 or
a 404. A purchased module with no screen yet is shown inert rather than hidden,
so a customer can see what they have bought.

Groups follow the module registry's own grouping. Requirements: icons, clear
active state, nested items where a module has sub-screens, smooth expand
transitions, tooltips when collapsed.

---

## 4. Components

Build once, in `frontend/src/components/`. If something exists, improve it —
do not fork it.

| Component | Responsibility |
|---|---|
| `Button` | primary / secondary / outline / ghost / danger / success; default, hover, active, disabled, loading |
| `Card` | header (title, description, actions), body, footer |
| `StatCard` | KPI: value, label, trend, icon, supporting text |
| `DataTable` | search, filter, sort, pagination, selection, bulk actions, row actions, empty / loading / error states |
| `StatusBadge` | maps a domain status to a tone, consistently across the app |
| `Badge` | neutral pill |
| `FormField` | label, required marker, control, helper text, error |
| `Input` `Select` `Textarea` `Checkbox` `Switch` | one height, one radius, one focus ring |
| `DatePicker` | the only date control — a calendar popover, never `<input type="date">` |
| `Modal` | focus trap, Escape to close, backdrop click, scroll lock |
| `ConfirmDialog` | destructive confirmation, built on Modal |
| `Dropdown` | keyboard-navigable menu |
| `Tabs` | section switching within a page |
| `PageHeader` | breadcrumb, title, description, actions |
| `EmptyState` | icon, message, optional action |
| `Skeleton` | loading placeholder matching the real content's shape |
| `ErrorState` | message with a retry affordance |
| `Alert` | inline notice: info / success / warning / danger |
| `Toast` | transient confirmation |

**Dates** use `DatePicker`, never a native `<input type="date">`: the native
control renders a different widget in every browser, ignores the theme, and
follows the operating system's date order rather than the one the rest of the
interface uses. Values cross the boundary as `YYYY-MM-DD` strings, never `Date`
objects — converting through a `Date` is how a filter silently shifts by a day.
The calendar is `react-day-picker`, restyled through its own CSS variables from
our tokens rather than re-implemented.

**Never pass a width class to `Input`, `Select`, `Textarea` or `DatePicker`.**
They already carry `w-full`, and two width utilities in the same layer are
resolved by the stylesheet's order, not the class attribute's — so the override
silently loses. Size the wrapping element instead.

Icons: `lucide-react`, 16px inside controls, 18–20px in navigation. Icons are
used where they aid recognition, never on every button by reflex.

---

## 5. Tables

Tables matter more than anything else in this product. Requirements:

- compact but readable rows (`px-4 py-3`)
- numeric columns right-aligned and tabular
- status shown as a badge, in words
- row actions in a trailing menu, not a row of buttons
- sticky header on long tables
- explicit empty, loading and error states — never a blank rectangle
- **mobile:** collapse to stacked cards below `md`, or scroll inside an
  `overflow-x-auto` container. The page body must never scroll horizontally.

Status vocabulary and tone:

| Status | Tone |
|---|---|
| Active, Approved, Completed, Passed | success |
| Pending, Grace, Rework | warning |
| Rejected, Suspended, Cancelled, Failed | danger |
| Draft, Inactive, Trial | neutral / info |

---

## 6. Forms

Clear labels above controls. Required fields marked. Helper text where a field
needs explanation. Validation errors shown against the field, with the server's
message rather than an invented one. Consistent field height across every
input, select, date picker and file input (36px, shrunk from an original
40px on direct request) — the default Button size stays 40px, its own
separate token.

Large forms are split into sections, cards, tabs or a stepper — never one flat
wall of inputs. Submit buttons show a loading state and disable while pending.

---

## 7. Dark mode

Three states: explicit light, explicit dark, and system default. The complete
light palette is defined on bare `:root`; only the tokens that change are
redefined under `@media (prefers-color-scheme: dark)` guarded as
`:root:not([data-theme="light"])`, and again under `:root[data-theme="dark"]`
so an explicit toggle wins in both directions.

Dark mode is not inverted light mode. Surfaces lift rather than darken, borders
soften, and accent colours are re-chosen for contrast on a dark ground. Every
component needs deliberate background, surface, border, text, input, hover,
active, disabled and modal treatment in both themes.

---

## 8. Responsive

| Breakpoint | Behaviour |
|---|---|
| `< sm` (mobile) | Sidebar is a drawer; tables become cards; single column; `p-4` |
| `sm–lg` (tablet) | Sidebar collapses to icons; two-column grids |
| `≥ lg` (desktop) | Full sidebar; three- and four-column grids |

Verify on every change: sidebar, topbar, tables, forms, cards, modals,
dropdowns, charts.

---

## 9. Rules that outrank preference

1. **The redesign never changes behaviour.** API calls, authentication,
   authorization, routing, validation and business logic stay exactly as they
   are.
2. **Refactor incrementally.** Improve and reuse components rather than
   rewriting working screens.
3. **Consolidate duplication.** A pattern appearing twice becomes a component.
4. **The frontend draws, the API decides.** Hiding a control is a courtesy to
   the user, never a security boundary.
5. **Finish the sweep.** A design system applied to some screens is worse than
   none — the inconsistency is what looks unprofessional.
