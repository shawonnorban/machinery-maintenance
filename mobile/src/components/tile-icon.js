/**
 * The user's own icon pictures (2026-11-28), cut from their sheet into
 * assets/tiles: one drawn, coloured rounded square per module. They replace
 * the line icons wherever the module shows — Home and More tiles, the menu
 * rows, the page headers, the sections and the machine counts.
 */
import { Image, View } from "react-native";

export const TILES = {
  chat: require("../../assets/tiles/chat.png"),
  order: require("../../assets/tiles/order.png"),
  budget: require("../../assets/tiles/budget.png"),
  production: require("../../assets/tiles/production.png"),
  machines: require("../../assets/tiles/machines.png"),
  breakdown: require("../../assets/tiles/breakdown.png"),
  "work-order": require("../../assets/tiles/work-order.png"),
  maintenance: require("../../assets/tiles/maintenance.png"),
  cutting: require("../../assets/tiles/cutting.png"),
  sewing: require("../../assets/tiles/sewing.png"),
  quality: require("../../assets/tiles/quality.png"),
  "line-target": require("../../assets/tiles/line-target.png"),
  history: require("../../assets/tiles/history.png"),
  review: require("../../assets/tiles/review.png"),
  "scan-machine": require("../../assets/tiles/scan-machine.png"),
  "scan-bundle": require("../../assets/tiles/scan-bundle.png"),
  notification: require("../../assets/tiles/notification.png"),
  account: require("../../assets/tiles/account.png"),
  about: require("../../assets/tiles/about.png"),
  running: require("../../assets/tiles/running.png"),
  idle: require("../../assets/tiles/idle.png"),
  down: require("../../assets/tiles/down.png"),
};

/** A picture by its name (or a picture given as it is); null when there is none. */
export function tileSource(image) {
  if (!image) return null;
  return typeof image === "string" ? (TILES[image] ?? null) : image;
}

/**
 * One icon picture on its rounded square, `size` across, with a light shade
 * under it (user, 2026-11-28) — `lift={false}` leaves it flat.
 */
export function TileIcon({ image, size = 40, lift = true, style }) {
  const source = tileSource(image);
  if (!source) return null;
  const corner = Math.round(size * 0.24);
  const big = size >= 44;

  return (
    <View
      style={[
        { width: size, height: size, borderRadius: corner },
        lift ? { backgroundColor: "#ffffff", shadowColor: "#475569", shadowOffset: { width: 0, height: big ? 4 : 2 }, shadowRadius: big ? 7 : 4, shadowOpacity: 0.16, elevation: big ? 4 : 2 } : null,
        style,
      ]}
    >
      <Image source={source} style={{ width: size, height: size, borderRadius: corner }} />
    </View>
  );
}
