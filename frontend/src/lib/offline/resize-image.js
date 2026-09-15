"use client";

/**
 * Resizes an image file to at most 1600px on its longest side before
 * upload (docs/12-Stack-Migration-Implementation-Plan.md Phase E) —
 * factory wifi can't absorb raw camera output, and a breakdown photo
 * doesn't need more resolution than that to be useful. Returns a new
 * `File` with the same name and a JPEG mime type; the original is never
 * mutated.
 *
 * @param {File} file
 * @param {number} [maxDimension]
 * @returns {Promise<File>}
 */
async function resizeImage(file, maxDimension = 1600) {
  if (!file.type.startsWith("image/")) {
    return file;
  }

  const bitmap = await createImageBitmap(file);

  const scale = Math.min(1, maxDimension / Math.max(bitmap.width, bitmap.height));
  const width = Math.round(bitmap.width * scale);
  const height = Math.round(bitmap.height * scale);

  if (scale === 1) {
    bitmap.close();
    return file;
  }

  const canvas = document.createElement("canvas");
  canvas.width = width;
  canvas.height = height;
  canvas.getContext("2d").drawImage(bitmap, 0, 0, width, height);
  bitmap.close();

  const blob = await new Promise((resolve) => canvas.toBlob(resolve, "image/jpeg", 0.85));

  return new File([blob], file.name.replace(/\.\w+$/, ".jpg"), { type: "image/jpeg" });
}

export { resizeImage };
