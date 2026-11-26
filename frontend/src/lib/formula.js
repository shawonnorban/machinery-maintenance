/*
 * The spreadsheet formula language of the configurable sheets (Order Summary,
 * AnnotechERP 32) in the browser — the live figures while a row is typed. It
 * is App\Shared\Formula\Formula + FormulaSet line for line: the server works
 * the same formulas out again on save, and tests/fixtures/formula_cases.json
 * holds the cases both must agree on. Change one side, change the other.
 *
 * Values: null is blank, numbers, 'YYYY-MM-DD' dates, text. Blank in, blank
 * out (see the PHP class for the full rules).
 */

const FUNCTIONS = {
  IF: [3, 3], AND: [1, null], OR: [1, null], NOT: [1, 1],
  ROUND: [1, 2], MIN: [1, null], MAX: [1, null], ABS: [1, 1], SUM: [1, null],
  N: [1, 1], ISBLANK: [1, 1], TODAY: [0, 0], DAYS: [2, 2], ADD_DAYS: [2, 2], JOIN: [2, null],
};

const DATE_TYPES = ["date"];
const NUMBER_TYPES = ["number", "money", "percent", "days"];
/** Places a worked-out number keeps: enough for any sheet, few enough to drop 0.1 + 0.2 noise. */
const PRECISION = 9;

class FormulaError extends Error {
  constructor(kind, params = {}, position = null) {
    super(kind + (Object.keys(params).length ? ` ${JSON.stringify(params)}` : ""));
    this.kind = kind;
    this.params = params;
    this.position = position;
  }
}

/* ── Reading ────────────────────────────────────────────────────── */

function tokens(src) {
  const out = [];
  let i = 0;
  while (i < src.length) {
    const c = src[i];
    if (/\s/.test(c)) {
      i++;
      continue;
    }
    const at = i;
    if (c === "[" || c === "{") {
      const close = c === "[" ? "]" : "}";
      const end = src.indexOf(close, i);
      if (end < 0) throw new FormulaError("unclosed_name", {}, at);
      out.push({ type: c === "[" ? "name" : "key", text: src.slice(i, end + 1), value: src.slice(i + 1, end).trim(), at });
      i = end + 1;
    } else if (c === '"') {
      const end = src.indexOf('"', i + 1);
      if (end < 0) throw new FormulaError("unclosed_text", {}, at);
      out.push({ type: "str", text: src.slice(i, end + 1), value: src.slice(i + 1, end), at });
      i = end + 1;
    } else if (/[0-9]/.test(c) || (c === "." && /[0-9]/.test(src[i + 1] ?? ""))) {
      const m = /^(\d*\.?\d+(?:[eE][+-]?\d+)?|\d+\.?)/.exec(src.slice(i));
      out.push({ type: "num", text: m[0], value: parseFloat(m[0]), at });
      i += m[0].length;
    } else if (/[A-Za-z_]/.test(c)) {
      const m = /^[A-Za-z_][A-Za-z0-9_]*/.exec(src.slice(i));
      out.push({ type: "ident", text: m[0], value: m[0].toUpperCase(), at });
      i += m[0].length;
    } else if (["<=", ">=", "<>"].includes(src.slice(i, i + 2))) {
      out.push({ type: "op", text: src.slice(i, i + 2), at });
      i += 2;
    } else if ("+-*/=<>&(),".includes(c)) {
      out.push({ type: "(),".includes(c) ? c : "op", text: c, at });
      i++;
    } else {
      throw new FormulaError("bad_character", { text: c }, at);
    }
  }
  return out;
}

/**
 * Read formula text into its tree. `resolve(name)` turns a [Column Name] into
 * its key (or null); `{key}` is taken as is and checked with `known(key)`.
 */
function parse(source, resolve, known = null) {
  const t = tokens(source);
  const p = { i: 0 };
  const ast = comparison(t, p, resolve, known);
  if (p.i < t.length) throw new FormulaError("unexpected", { text: t[p.i].text }, t[p.i].at);
  return ast;
}

function binaryLevel(next, ops) {
  return (t, p, r, k) => {
    let left = next(t, p, r, k);
    while (t[p.i] && t[p.i].type === "op" && ops.includes(t[p.i].text)) {
      const op = t[p.i++].text;
      left = ["b", op, left, next(t, p, r, k)];
    }
    return left;
  };
}

function unary(t, p, r, k) {
  if (t[p.i] && t[p.i].type === "op" && (t[p.i].text === "-" || t[p.i].text === "+")) {
    const op = t[p.i++].text;
    const x = unary(t, p, r, k);
    return op === "-" ? ["u", "-", x] : x;
  }
  return primary(t, p, r, k);
}

const term = binaryLevel(unary, ["*", "/"]);
const additive = binaryLevel(term, ["+", "-", "&"]);
const comparison = binaryLevel(additive, ["=", "<>", "<", ">", "<=", ">="]);

function expect(t, p, type) {
  if (t[p.i]?.type !== type) throw new FormulaError("expected", { text: type }, t[p.i]?.at ?? null);
  p.i++;
}

function primary(t, p, r, k) {
  const tok = t[p.i];
  if (!tok) throw new FormulaError("unexpected_end");
  p.i++;
  switch (tok.type) {
    case "num":
      return ["n", tok.value];
    case "str":
      return ["s", tok.value];
    case "name": {
      const key = r(tok.value);
      if (key === null || key === undefined) throw new FormulaError("unknown_column", { name: tok.value }, tok.at);
      return ["r", key];
    }
    case "key":
      if (k && !k(tok.value)) throw new FormulaError("unknown_column", { name: tok.value }, tok.at);
      return ["r", tok.value];
    case "(": {
      const inner = comparison(t, p, r, k);
      expect(t, p, ")");
      return inner;
    }
    case "ident": {
      const name = tok.value;
      if (!FUNCTIONS[name]) throw new FormulaError("unknown_function", { name: tok.text }, tok.at);
      expect(t, p, "(");
      const args = [];
      if (t[p.i]?.type !== ")") {
        args.push(comparison(t, p, r, k));
        while (t[p.i]?.type === ",") {
          p.i++;
          args.push(comparison(t, p, r, k));
        }
      }
      expect(t, p, ")");
      const [min, max] = FUNCTIONS[name];
      if (args.length < min || (max !== null && args.length > max)) throw new FormulaError("arg_count", { name, min, max: max ?? "…" }, tok.at);
      return ["f", name, args];
    }
    default:
      throw new FormulaError("unexpected", { text: tok.text }, tok.at);
  }
}

function refs(ast) {
  const out = new Set();
  const walk = (n) => {
    if (n[0] === "r") out.add(n[1]);
    else if (n[0] === "f") n[2].forEach(walk);
    else if (n[0] === "u") walk(n[2]);
    else if (n[0] === "b") {
      walk(n[2]);
      walk(n[3]);
    }
  };
  walk(ast);
  return [...out];
}

function usesToday(ast) {
  if (ast[0] === "f") return ast[1] === "TODAY" || ast[2].some(usesToday);
  if (ast[0] === "u") return usesToday(ast[2]);
  if (ast[0] === "b") return usesToday(ast[2]) || usesToday(ast[3]);
  return false;
}

function rewrite(src, replace) {
  let out = "";
  let from = 0;
  for (const tok of tokens(src)) {
    const next = replace(tok);
    if (next !== null) {
      out += src.slice(from, tok.at) + next;
      from = tok.at + tok.text.length;
    }
  }
  return out + src.slice(from);
}

/** What the user wrote with names, as stored: [Name] → {key}. */
function stored(source, resolve) {
  parse(source, resolve);
  return rewrite(source, (tok) => (tok.type === "name" ? `{${resolve(tok.value)}}` : null));
}

/** The stored text shown with names: {key} → [Name]. */
function display(storedText, label) {
  return rewrite(storedText, (tok) => (tok.type === "key" ? `[${label(tok.value) ?? tok.value}]` : null));
}

/* ── Evaluation ─────────────────────────────────────────────────── */

const blank = (v) => v === null || v === undefined || v === "" || (Array.isArray(v) && v.length === 0);

function num(v) {
  if (typeof v === "boolean") return v ? 1 : 0;
  if (typeof v === "number") return Number.isNaN(v) ? null : v;
  if (typeof v === "string") {
    const s = v.trim().replace(/,/g, "");
    return s !== "" && /^[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?$/.test(s) ? parseFloat(s) : null;
  }
  return null;
}

function text(v) {
  if (blank(v)) return "";
  if (typeof v === "boolean") return v ? "TRUE" : "FALSE";
  if (typeof v === "number") {
    const s = v.toFixed(10).replace(/0+$/, "").replace(/\.$/, "");
    return s === "-0" ? "0" : s;
  }
  if (Array.isArray(v)) return v.map(text).join(", ");
  return String(v);
}

function round(x, places) {
  const f = 10 ** places;
  const v = Math.floor(Math.abs(x) * f + 0.5 + 1e-9) / f;
  return x < 0 ? -v : v;
}

function day(v) {
  if (typeof v !== "string") return null;
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(v);
  if (!m) return null;
  const [y, mo, d] = [Number(m[1]), Number(m[2]), Number(m[3])];
  const ms = Date.UTC(y, mo - 1, d);
  const back = new Date(ms);
  if (back.getUTCFullYear() !== y || back.getUTCMonth() !== mo - 1 || back.getUTCDate() !== d) return null;
  return Math.floor(ms / 86400000);
}

const date = (n) => new Date(n * 86400000).toISOString().slice(0, 10);

function truthy(v) {
  if (blank(v)) return false;
  if (typeof v === "boolean") return v;
  const n = num(v);
  return n === null ? true : n !== 0;
}

function equal(a, b) {
  if (blank(a) || blank(b)) return blank(a) && blank(b);
  const x = num(a);
  const y = num(b);
  if (x !== null && y !== null) return Math.abs(x - y) < 1e-9;
  return text(a).trim().toLowerCase() === text(b).trim().toLowerCase();
}

function binary(op, a, b) {
  if (op === "&") return text(a) + text(b);
  if (op === "=" || op === "<>") {
    const same = equal(a, b);
    return op === "=" ? same : !same;
  }
  if (["<", ">", "<=", ">="].includes(op)) {
    if (blank(a) || blank(b)) return null;
    const x = num(a);
    const y = num(b);
    const c = x !== null && y !== null ? Math.sign(x - y) : String(a) < String(b) ? -1 : String(a) > String(b) ? 1 : 0;
    return op === "<" ? c < 0 : op === ">" ? c > 0 : op === "<=" ? c <= 0 : c >= 0;
  }
  const x = num(a);
  const y = num(b);
  if (x === null || y === null) return null;
  if (op === "+") return x + y;
  if (op === "-") return x - y;
  if (op === "*") return x * y;
  return y === 0 ? null : x / y;
}

function call(name, args, values, today) {
  const ev = (a) => evaluate(a, values, today);
  switch (name) {
    case "IF":
      return truthy(ev(args[0])) ? ev(args[1]) : ev(args[2]);
    case "AND":
      return args.every((a) => truthy(ev(a)));
    case "OR":
      return args.some((a) => truthy(ev(a)));
    case "NOT":
      return !truthy(ev(args[0]));
    case "TODAY":
      return today;
    case "ISBLANK":
      return blank(ev(args[0]));
    case "N":
      return num(ev(args[0])) ?? 0;
    case "ABS": {
      const x = num(ev(args[0]));
      return x === null ? null : Math.abs(x);
    }
    case "ROUND": {
      const x = num(ev(args[0]));
      const p = args[1] ? num(ev(args[1])) : 0;
      return x === null || p === null ? null : round(x, Math.trunc(p));
    }
    case "SUM":
    case "MIN":
    case "MAX": {
      const nums = args.map((a) => num(ev(a))).filter((v) => v !== null);
      if (!nums.length) return null;
      if (name === "SUM") return nums.reduce((s, v) => s + v, 0);
      return name === "MIN" ? Math.min(...nums) : Math.max(...nums);
    }
    case "DAYS": {
      const a = day(ev(args[0]));
      const b = day(ev(args[1]));
      return a === null || b === null ? null : a - b;
    }
    case "ADD_DAYS": {
      const d = day(ev(args[0]));
      const n = num(ev(args[1]));
      return d === null || n === null ? null : date(d + Math.trunc(n));
    }
    case "JOIN": {
      const sep = text(ev(args[0]));
      return args.slice(1).map((a) => text(ev(a))).filter((s) => s !== "").join(sep);
    }
    default:
      throw new FormulaError("unknown_function", { name });
  }
}

function evaluate(ast, values, today) {
  switch (ast[0]) {
    case "n":
    case "s":
      return ast[1];
    case "r": {
      const v = values[ast[1]];
      return blank(v) ? null : v;
    }
    case "u": {
      const x = num(evaluate(ast[2], values, today));
      return x === null ? null : -x;
    }
    case "b":
      return binary(ast[1], evaluate(ast[2], values, today), evaluate(ast[3], values, today));
    case "f":
      return call(ast[1], ast[2], values, today);
    default:
      return null;
  }
}

/* ── A sheet's formula columns ──────────────────────────────────── */

/** Working order of formula columns: each after those it reads; a loop throws. */
function order(trees) {
  const out = [];
  const state = {};
  const visit = (key, path) => {
    if (state[key] === "done") return;
    if (state[key] === "busy") {
      const loop = path.slice(path.indexOf(key));
      throw new FormulaError("loop", { columns: [...loop, key].join(" → ") });
    }
    state[key] = "busy";
    for (const ref of refs(trees[key])) if (trees[ref]) visit(ref, [...path, key]);
    state[key] = "done";
    out.push(key);
  };
  Object.keys(trees).forEach((k) => visit(k, []));
  return out;
}

/**
 * columns: { key: { formula, type, decimals } } — formula in stored form.
 * `apply(values, today, withVolatile)` fills the row's formula columns.
 */
/** The number a pairs column stands for: SUM of its lines' numbers or the FIRST line's; null without one. */
function pairsNumber(lines, reduce = "SUM") {
  if (!Array.isArray(lines)) return num(lines);
  const nums = lines.map((l) => (l && typeof l === "object" ? num(l.value) : null)).filter((n) => n !== null);
  if (!nums.length) return null;
  return reduce === "FIRST" ? nums[0] : nums.reduce((a, b) => a + b, 0);
}

class FormulaSet {
  constructor(columns) {
    this.columns = columns;
    const trees = {};
    for (const [key, c] of Object.entries(columns)) {
      if (c.formula) trees[key] = parse(c.formula, () => null, (k) => Boolean(columns[k]));
    }
    this.trees = {};
    this.volatile = new Set();
    for (const key of order(trees)) {
      this.trees[key] = trees[key];
      if (usesToday(trees[key]) || refs(trees[key]).some((r) => this.volatile.has(r))) this.volatile.add(key);
    }
  }

  keys() {
    return Object.keys(this.trees);
  }

  isFormula(key) {
    return Boolean(this.trees[key]);
  }

  isVolatile(key) {
    return this.volatile.has(key);
  }

  apply(values, today, withVolatile = false) {
    const out = { ...values };
    // A formula reads a pairs column (lines of a name and a number) as one number: its lines' total, or its first line.
    const view = { ...values };
    for (const [key, c] of Object.entries(this.columns)) {
      if (c.type === "pairs" && key in view) view[key] = pairsNumber(view[key], c.reduce ?? "SUM");
    }
    for (const [key, tree] of Object.entries(this.trees)) {
      if (!withVolatile && this.volatile.has(key)) {
        delete out[key];
        delete view[key];
        continue;
      }
      out[key] = view[key] = this.shape(key, evaluate(tree, view, today));
    }
    return out;
  }

  shape(key, v) {
    const type = this.columns[key]?.type ?? "text";
    if (blank(v)) return null;
    if (DATE_TYPES.includes(type)) return day(v) === null ? null : String(v).slice(0, 10);
    if (NUMBER_TYPES.includes(type)) {
      const n = num(v);
      // Kept whole, as Excel keeps it (only float noise trimmed): the column's
      // places shape how it shows, and a formula reading it gets the full value.
      return n === null || !Number.isFinite(n) ? null : round(n, PRECISION);
    }
    return text(v);
  }
}

export { FUNCTIONS, FormulaError, FormulaSet, pairsNumber, parse, refs, usesToday, stored, display, evaluate, order, round, day, date, num, text, blank };
