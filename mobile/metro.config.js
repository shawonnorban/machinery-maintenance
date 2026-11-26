// Metro, with the web app's translations and clock readable from here. The app
// IMPORTS those files rather than copying them (frontend/messages, written by
// scripts/extract-i18n.php from lang/*, and frontend/src/lib), so a wording or
// a rule is changed once. Nothing else of the web app is watched: its
// node_modules and .next would be read as part of this app.
const path = require("path");
const { getDefaultConfig } = require("expo/metro-config");

const config = getDefaultConfig(__dirname);
const web = path.resolve(__dirname, "..", "frontend");

config.watchFolders = [path.join(web, "messages"), path.join(web, "src", "lib")];
// A shared file's own imports (Babel helpers) come from this app's packages.
config.resolver.nodeModulesPaths = [path.join(__dirname, "node_modules")];

module.exports = config;
