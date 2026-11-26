// The app's icons, drawn from one mark (an A whose bar is a check mark) so a
// change is made once: `npm run icons`. Uses the web app's sharp.
import { createRequire } from "node:module";
import path from "node:path";
import { fileURLToPath } from "node:url";
import fs from "node:fs";

const here = path.dirname(fileURLToPath(import.meta.url));
const require = createRequire(path.join(here, "..", "..", "frontend", "package.json"));
const sharp = require("sharp");
const out = path.join(here, "..", "assets");
fs.mkdirSync(out, { recursive: true });

const BRAND = "#4f46e5";
/** The mark in a 100 × 100 box. */
const mark = (colour) => `
  <path d="M18 84 L50 16 L82 84" fill="none" stroke="${colour}" stroke-width="13" stroke-linecap="round" stroke-linejoin="round"/>
  <path d="M38 62 L49 72 L67 51" fill="none" stroke="${colour}" stroke-width="9" stroke-linecap="round" stroke-linejoin="round"/>`;
const svg = ({ size = 1024, scale, background, colour, round = 0 }) => `
  <svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">
    ${background ? `<rect width="${size}" height="${size}" rx="${round}" fill="${background}"/>` : ""}
    <g transform="translate(${(size * (1 - scale)) / 2} ${(size * (1 - scale)) / 2}) scale(${(size * scale) / 100})">${mark(colour)}</g>
  </svg>`;

const files = {
  "icon.png": { scale: 0.62, background: BRAND, colour: "#ffffff" },
  // Android crops the adaptive icon to the middle two thirds.
  "android-icon-foreground.png": { scale: 0.42, colour: "#ffffff" },
  "android-icon-monochrome.png": { scale: 0.42, colour: "#ffffff" },
  "splash-icon.png": { scale: 0.62, background: BRAND, colour: "#ffffff", round: 220 },
};

for (const [name, spec] of Object.entries(files)) {
  await sharp(Buffer.from(svg(spec))).png().toFile(path.join(out, name));
  console.log(name);
}
