// The app's icons, made from one picture so a change is made once:
// `npm run icons`. The picture is assets/brand-a.png — the blue A of the
// Annotech RMG logo, cut out on a clear background (user, 2026-11-27).
// Uses the web app's sharp. `ICONS_OUT=<dir>` writes somewhere else to compare.
import { createRequire } from "node:module";
import path from "node:path";
import { fileURLToPath } from "node:url";
import fs from "node:fs";

const here = path.dirname(fileURLToPath(import.meta.url));
const require = createRequire(path.join(here, "..", "..", "frontend", "package.json"));
const sharp = require("sharp");
const assets = path.join(here, "..", "assets");
const out = process.env.ICONS_OUT ?? assets;
const source = path.join(assets, "brand-a.png");
fs.mkdirSync(out, { recursive: true });

const SIZE = 1024;
const CLEAR = { r: 0, g: 0, b: 0, alpha: 0 };
const WHITE = { r: 255, g: 255, b: 255, alpha: 1 };

/** The A, its longer side `scale` of the square, a little below the middle (the swoosh weighs the top). */
async function placed({ scale, background = CLEAR, silhouette = false }) {
  const long = Math.round(SIZE * scale);
  let a = await sharp(source).resize({ width: long, height: long, fit: "inside" }).png().toBuffer();
  const { width, height } = await sharp(a).metadata();
  if (silhouette) {
    // Themed icons: the A's shape in white.
    a = await sharp({ create: { width, height, channels: 4, background: WHITE } })
      .composite([{ input: a, blend: "dest-in" }])
      .png()
      .toBuffer();
  }

  return sharp({ create: { width: SIZE, height: SIZE, channels: 4, background } })
    .composite([{ input: a, left: Math.round((SIZE - width) / 2), top: Math.round((SIZE - height) / 2 + SIZE * 0.01) }])
    .png();
}

const files = {
  // Android crops the adaptive icon to the middle two thirds; its background is white (app.json).
  "android-icon-foreground.png": { scale: 0.56 },
  "android-icon-monochrome.png": { scale: 0.56, silhouette: true },
  // The sign-in screen, iOS and older Android: on white.
  "icon.png": { scale: 0.72, background: WHITE },
  "splash-icon.png": { scale: 0.9 },
};

for (const [name, spec] of Object.entries(files)) {
  await (await placed(spec)).toFile(path.join(out, name));
  console.log(name);
}
