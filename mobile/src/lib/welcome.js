/** Whether this phone has seen the first screen (language chosen); kept on the phone. */
import Storage from "expo-sqlite/kv-store";

const KEY = "welcomed";

export const welcomed = () => Storage.getItemSync(KEY) === "1";
export const markWelcomed = () => Storage.setItemSync(KEY, "1");
