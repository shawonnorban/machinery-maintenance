import { cache } from "react";
import { apiFetch } from "@/lib/api-server";

/**
 * The caller's menu tree (`GET /access/navigation`), fetched once per request
 * however many server components ask — the layout's sidebar and a page's
 * header both read it.
 */
const getNavigation = cache(() => apiFetch("/access/navigation"));

/** Every screen of a module, in sidebar order (folders flattened). */
function screensOf(mod) {
  return mod.entries.flatMap((entry) => (entry.type === "folder" ? entry.items.map((item) => ({ ...item, folder: entry })) : [entry]));
}

/**
 * Where a URL sits in the tree: its module, folder, screen and Brickland's
 * "NN of MM" position within the module. The longest matching screen url
 * wins, so `/company-management/building` beats the module's dashboard at
 * `/company-management`.
 */
function locateScreen(navigation, pathname) {
  let best = null;
  for (const mod of navigation) {
    const screens = screensOf(mod);
    screens.forEach((screen, index) => {
      const matches = pathname === screen.url || (screen.url !== "/" && pathname.startsWith(`${screen.url}/`));
      if (matches && (!best || screen.url.length > best.screen.url.length)) {
        best = { module: mod, screen, folder: screen.folder ?? null, index: index + 1, total: screens.length };
      }
    });
  }
  return best;
}

/** The same, for a server page that knows its own url. */
async function screenContext(url) {
  return locateScreen(await getNavigation(), url);
}

export { getNavigation, locateScreen, screenContext, screensOf };
