"use client";

import { useState } from "react";
import { Tabs, TabsIndicator, TabsList, TabsPanel, TabsTab } from "@/components/ui/tabs";

/*
 * Pre-Budget → Master Data → Pre-Budget Master Data (user, 2026-11-19): the
 * module's own lists (Accessories Item, Yarn Type, Wash Type, Additional
 * Process, GSM, Sustainability, Order Type) on one screen, one tab each. A
 * tab switches in the page (no navigation); the address keeps ?tab= so a
 * reload or a link opens the same list.
 */
function MasterTabs({ tabs, initial, panels }) {
  const [tab, setTab] = useState(initial);
  const change = (value) => {
    setTab(value);
    const url = new URL(window.location.href);
    url.searchParams.set("tab", value);
    url.searchParams.delete("status");
    window.history.replaceState(window.history.state, "", url);
  };

  return (
    <Tabs value={tab} onValueChange={change}>
      <TabsList>
        {tabs.map((t) => (
          <TabsTab key={t.key} value={t.key}>
            {t.label}
          </TabsTab>
        ))}
        <TabsIndicator />
      </TabsList>
      {tabs.map((t) => (
        <TabsPanel key={t.key} value={t.key}>
          {panels[t.key]}
        </TabsPanel>
      ))}
    </Tabs>
  );
}

export { MasterTabs };
