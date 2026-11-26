/** Scores and percentages on the AI screens: one decimal at most. Shared by the server screens and the client kit. */
const num = new Intl.NumberFormat("en-US", { maximumFractionDigits: 1 });

export { num };
