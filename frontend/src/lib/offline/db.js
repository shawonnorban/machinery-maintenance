/**
 * IndexedDB wrapper for the offline draft queue (docs/12-Stack-Migration-
 * Implementation-Plan.md Phase E; ported unchanged from
 * resources/js/offline/queue.js's `openDb`/`tx`, since the storage shape
 * is exactly the same problem in either stack).
 */
const DB_NAME = "mm-offline";
const STORE = "drafts";

function openDb() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, 1);
    request.onupgradeneeded = () => request.result.createObjectStore(STORE, { keyPath: "key" });
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });
}

async function tx(mode, fn) {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const transaction = db.transaction(STORE, mode);
    const result = fn(transaction.objectStore(STORE));
    transaction.oncomplete = () => resolve(result?.result ?? result);
    transaction.onerror = () => reject(transaction.error);
  });
}

export { tx, STORE };
