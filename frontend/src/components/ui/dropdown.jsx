"use client";

import { Menu } from "@base-ui/react/menu";
import { cn } from "@/lib/utils";

/**
 * docs/UI-DESIGN-SYSTEM.md §4/§5: keyboard-navigable menu, used for row
 * actions (a trailing menu, not a row of buttons) as well as generic
 * dropdown actions elsewhere.
 *
 * @param {{
 *   trigger: React.ReactNode,
 *   items: ({ label: string, icon?: React.ReactNode, onSelect?: () => void, destructive?: boolean, disabled?: boolean } | "separator")[],
 *   align?: "start" | "center" | "end",
 * }} props
 */
function Dropdown({ trigger, items, align = "end" }) {
  return (
    <Menu.Root>
      <Menu.Trigger className="outline-none">{trigger}</Menu.Trigger>
      <Menu.Portal>
        <Menu.Positioner align={align} sideOffset={4} className="z-50 outline-none">
          <Menu.Popup className="min-w-40 rounded-sm border border-border bg-surface p-1 shadow-md">
            {items.map((item, index) =>
              item === "separator" ? (
                <Menu.Separator key={index} className="my-1 h-px bg-border" />
              ) : (
                <Menu.Item
                  key={item.label}
                  disabled={item.disabled}
                  onClick={item.onSelect}
                  className={cn(
                    "flex cursor-pointer items-center gap-2 rounded-sm px-3 py-2 text-sm outline-none",
                    "data-[highlighted]:bg-surface-muted",
                    item.destructive ? "text-danger" : "text-foreground",
                    "data-[disabled]:pointer-events-none data-[disabled]:opacity-50",
                  )}
                >
                  {item.icon ? <span className="[&_svg]:size-4">{item.icon}</span> : null}
                  {item.label}
                </Menu.Item>
              ),
            )}
          </Menu.Popup>
        </Menu.Positioner>
      </Menu.Portal>
    </Menu.Root>
  );
}

export { Dropdown };
