import { cn } from "@/lib/utils";

const TONE_CLASS = {
  brand: "bg-brand-subtle text-brand-hover",
  danger: "bg-danger-subtle text-danger",
  success: "bg-success-subtle text-success",
};

function initials(name) {
  return name
    .split(" ")
    .map((part) => part[0])
    .filter(Boolean)
    .slice(0, 2)
    .join("")
    .toUpperCase();
}

/**
 * Overlapping initials circles — the reference design's "Today's Heroes"
 * row, applied to whoever is actually on shift rather than a stock photo
 * strip. A Server Component: no interactivity, just initials and tone.
 *
 * @param {{ people: { id: string, name: string, tone?: "brand"|"danger"|"success" }[], max?: number, className?: string }} props
 */
function AvatarStack({ people, max = 6, className }) {
  const shown = people.slice(0, max);
  const overflow = people.length - shown.length;

  return (
    <div className={cn("flex items-center", className)}>
      {shown.map((person, index) => (
        <span
          key={person.id}
          title={person.name}
          className={cn(
            "flex size-8 shrink-0 items-center justify-center rounded-full border-2 border-surface text-xs font-semibold",
            index > 0 && "-ml-2",
            TONE_CLASS[person.tone] ?? TONE_CLASS.brand,
          )}
        >
          {initials(person.name)}
        </span>
      ))}
      {overflow > 0 ? (
        <span className="-ml-2 flex size-8 shrink-0 items-center justify-center rounded-full border-2 border-surface bg-surface-muted text-xs font-semibold text-foreground-muted">
          +{overflow}
        </span>
      ) : null}
    </div>
  );
}

export { AvatarStack };
