import { describe, expect, it } from "vitest";
import cases from "./formula.cases.json";
import { FormulaError, FormulaSet, display, evaluate, parse, stored } from "./formula";

/*
 * The browser's formula engine against the cases the server's engine
 * (tests/Feature/OrderSummary/FormulaTest.php) runs too.
 */

const known = new Set(cases.known);
const none = () => null;
const check = (actual, expected) => (typeof expected === "number" ? expect(actual).toBeCloseTo(expected, 9) : expect(actual).toEqual(expected));

describe("formula cases shared with the server", () => {
  for (const c of cases.eval) {
    it(c.name, () => {
      const ast = parse(c.formula, none, (k) => known.has(k) || k in c.values);
      check(evaluate(ast, c.values, cases.today), c.expect);
    });
  }

  for (const c of cases.errors) {
    it(`refuses: ${c.name}`, () => {
      let error = null;
      try {
        parse(c.formula, none, (k) => known.has(k));
      } catch (e) {
        error = e;
      }
      expect(error).toBeInstanceOf(FormulaError);
      expect(error.kind).toBe(c.kind);
    });
  }

  for (const s of cases.sets) {
    it(`set: ${s.name}`, () => {
      if (s.error) {
        expect(() => new FormulaSet(s.columns)).toThrow(FormulaError);
        return;
      }
      const set = new FormulaSet(s.columns);
      expect(set.keys().filter((k) => set.isVolatile(k))).toEqual(s.volatile);
      expect(set.apply(s.values, cases.today, false)).toEqual(s.stored);
      expect(set.apply(s.values, cases.today, true)).toEqual(s.read);
    });
  }
});

describe("names and stored text", () => {
  const byName = { "Quantity (Ticket)": "quantity_ticket", PAC: "pac" };
  const byKey = { quantity_ticket: "Quantity (Ticket)", pac: "PAC" };

  it("stores names as keys and shows keys as names, spacing kept", () => {
    const s = stored('[Quantity (Ticket)] *  [PAC] & "[PAC]"', (n) => byName[n] ?? null);
    expect(s).toBe('{quantity_ticket} *  {pac} & "[PAC]"');
    expect(display(s, (k) => byKey[k])).toBe('[Quantity (Ticket)] *  [PAC] & "[PAC]"');
  });

  it("an unknown name is refused with its position", () => {
    try {
      stored("[PAC] + [Nope]", (n) => byName[n] ?? null);
      throw new Error("not refused");
    } catch (e) {
      expect(e.kind).toBe("unknown_column");
      expect(e.position).toBe(8);
    }
  });
});
