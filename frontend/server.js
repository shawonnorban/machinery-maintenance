// Production entry point for hosts whose Node.js runner needs a single JS
// file to `require` rather than a package.json script — cPanel's "Setup
// Node.js App" (Cloudlinux/Phusion Passenger) is the case this exists for
// (see DEPLOYMENT-GUIDE.md §1.5). `npm start` (plain `next start`) is still
// the right entry point anywhere that can run an npm script directly; this
// file is only needed on hosts that can't.
const { createServer } = require("http");
const next = require("next");

const app = next({ dev: false });
const handle = app.getRequestHandler();

app.prepare().then(() => {
  const port = process.env.PORT || 3000;

  createServer((req, res) => handle(req, res)).listen(port, () => {
    console.log(`Next.js listening on ${port}`);
  });
});
