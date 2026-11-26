"use client";

import { createContext, useContext } from "react";

/**
 * A screen's Brickland words (AnnotechERP UI parity), given once by the page
 * and read by the kit below it: "Save colour" on the New tab, Edit opening an
 * "Edit colour" view in place of the grid with "Rewriting …", Cancel and
 * "Update colour", and "Remove this colour?" on delete. Built by
 * `lib/brickland-labels.js`; without a provider the kit keeps its plain words.
 */
const ScreenWords = createContext(null);

function ScreenWordsProvider({ words, children }) {
  return <ScreenWords.Provider value={words}>{children}</ScreenWords.Provider>;
}

const useScreenWords = () => useContext(ScreenWords);

export { ScreenWordsProvider, useScreenWords };
