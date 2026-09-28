#!/usr/bin/env python3
"""Convert a legacy Excel 97-2003 (.xls) sheet to UTF-8 CSV.

The backend reads CSV/XLSX (OpenSpout) but not .xls, and the item library a
factory hands over is usually .xls. Requires `pip install xlrd`.

    python scripts/xls-to-csv.py "Item List.xls" storage/app/legacy/item-list.csv [sheet-index]
"""
import csv
import os
import sys

import xlrd


def main() -> int:
    if len(sys.argv) < 3:
        print(__doc__)
        return 1

    source, target = sys.argv[1], sys.argv[2]
    sheet_index = int(sys.argv[3]) if len(sys.argv) > 3 else 0

    book = xlrd.open_workbook(source)
    sheet = book.sheet_by_index(sheet_index)
    os.makedirs(os.path.dirname(target) or ".", exist_ok=True)

    with open(target, "w", newline="", encoding="utf-8") as handle:
        writer = csv.writer(handle)
        for r in range(sheet.nrows):
            row = []
            for c in range(sheet.ncols):
                cell = sheet.cell(r, c)
                value = cell.value
                # Excel stores every number as a float; an id column reads
                # back as "337788.0" otherwise.
                if cell.ctype == xlrd.XL_CELL_NUMBER and float(value).is_integer():
                    value = str(int(value))
                row.append(str(value).strip() if value is not None else "")
            writer.writerow(row)

    print(f"{sheet.nrows - 1} rows -> {target}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
