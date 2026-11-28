/**
 * Production Trend (the reference screen, 2026-11-27): what was achieved as
 * a green line over a soft green area with a dot at each point, the target
 * as a dashed blue line, round figures up the side (10k, 20k…), a few
 * labels along the bottom, and the last figure in a green flag over its dot.
 */
import { useState } from "react";
import { View } from "react-native";
import Svg, { Defs, G, Line, LinearGradient, Path, Rect, Stop, Circle, Text as SvgText } from "react-native-svg";
import { Text } from "./ui";
import { fonts, space } from "../theme/tokens";
import { useColors } from "../theme/theme";

/** 47250 → "47k", 1250 → "1.3k", 90 → "90". */
export const short = (n) => (n >= 1000 ? `${Number((n / 1000).toFixed(n >= 10000 ? 0 : 1))}k` : String(Math.round(n)));

/** A round top for the side: 47,250 → 50,000; 90 → 100. */
function niceMax(v) {
  if (v <= 0) return 10;
  const step = 10 ** Math.floor(Math.log10(v));
  for (const m of [1, 2, 2.5, 5, 10]) {
    if (m * step >= v) return m * step;
  }

  return 10 * step;
}

/**
 * `labels` along the bottom (one per point; `showLabel(i)` says which are
 * written), `achieved` the line's figures, `target` one figure or one per
 * point (null where there is none).
 */
export function TrendChart({ labels, achieved, target, showLabel = () => true, height = 230 }) {
  const colors = useColors();
  const [width, setWidth] = useState(0);
  const targets = Array.isArray(target) ? target : labels.map(() => target ?? null);
  const top = niceMax(Math.max(1, ...achieved.filter((v) => v !== null), ...targets.filter((v) => v !== null && v !== undefined)) * 1.04);
  const left = 40;
  const right = 14;
  const topPad = 34;
  const bottom = 26;
  const w = Math.max(0, width - left - right);
  const h = height - topPad - bottom;
  const x = (i) => left + (labels.length <= 1 ? w / 2 : (i / (labels.length - 1)) * w);
  const y = (v) => topPad + h - (Math.max(0, v) / top) * h;
  const ticks = [0, 0.2, 0.4, 0.6, 0.8, 1].map((f) => f * top);
  const points = achieved.map((v, i) => (v === null || v === undefined ? null : [x(i), y(v)])).filter(Boolean);
  const line = points.map(([px, py], i) => `${i ? "L" : "M"}${px.toFixed(1)} ${py.toFixed(1)}`).join(" ");
  const area = points.length ? `${line} L${points[points.length - 1][0].toFixed(1)} ${y(0)} L${points[0][0].toFixed(1)} ${y(0)} Z` : "";
  const tLine = targets.map((v, i) => (v === null || v === undefined ? null : [x(i), y(v)])).filter(Boolean);
  const tPath = tLine.length === 1 ? `M${left} ${tLine[0][1]} L${left + w} ${tLine[0][1]}` : tLine.map(([px, py], i) => `${i ? "L" : "M"}${px.toFixed(1)} ${py.toFixed(1)}`).join(" ");
  const last = points[points.length - 1];
  const lastValue = [...achieved].reverse().find((v) => v !== null && v !== undefined);
  const flag = last ? Number(lastValue).toLocaleString("en-US") : null;
  const flagW = flag ? flag.length * 8 + 16 : 0;

  return (
    <View onLayout={(e) => setWidth(e.nativeEvent.layout.width)} style={{ height }}>
      {width ? (
        <Svg width={width} height={height}>
          <Defs>
            <LinearGradient id="achievedArea" x1="0" y1="0" x2="0" y2="1">
              <Stop offset="0" stopColor={colors.success} stopOpacity="0.22" />
              <Stop offset="1" stopColor={colors.success} stopOpacity="0.03" />
            </LinearGradient>
          </Defs>
          {ticks.map((v) => (
            <G key={v}>
              <Line x1={left} x2={left + w} y1={y(v)} y2={y(v)} stroke={colors.border} strokeWidth={1} />
              <SvgText x={left - 6} y={y(v) + 4} fontSize={11} fill={colors.foregroundMuted} textAnchor="end" fontFamily={fonts.regular}>
                {short(v)}
              </SvgText>
            </G>
          ))}
          {labels.map((l, i) =>
            showLabel(i) ? (
              <G key={`${l}${i}`}>
                <Line x1={x(i)} x2={x(i)} y1={topPad} y2={y(0)} stroke={colors.border} strokeWidth={1} strokeOpacity={0.6} />
                <SvgText x={x(i)} y={height - 6} fontSize={11} fill={colors.foregroundMuted} textAnchor="middle" fontFamily={fonts.regular}>
                  {l}
                </SvgText>
              </G>
            ) : null,
          )}
          {tPath ? <Path d={tPath} stroke={colors.brand} strokeWidth={2} strokeDasharray="6 5" fill="none" /> : null}
          {area ? <Path d={area} fill="url(#achievedArea)" /> : null}
          {line ? <Path d={line} stroke={colors.success} strokeWidth={2.5} fill="none" strokeLinejoin="round" /> : null}
          {points.map(([px, py], i) => (
            <Circle key={i} cx={px} cy={py} r={i === points.length - 1 ? 5.5 : 4} fill={colors.success} stroke="#ffffff" strokeWidth={i === points.length - 1 ? 2 : 0} />
          ))}
          {last ? (
            <G>
              <Rect x={Math.min(Math.max(last[0] - flagW / 2, left), left + w - flagW + right)} y={Math.max(2, last[1] - 34)} width={flagW} height={24} rx={6} fill={colors.success} />
              <SvgText x={Math.min(Math.max(last[0], left + flagW / 2), left + w + right - flagW / 2)} y={Math.max(2, last[1] - 34) + 16} fontSize={12} fill="#ffffff" textAnchor="middle" fontFamily={fonts.bold} fontWeight="bold">
                {flag}
              </SvgText>
            </G>
          ) : null}
        </Svg>
      ) : null}
    </View>
  );
}

/** The chart's key: a blue dot for the target, a green one for what was achieved. */
export function TrendLegend({ target, achieved }) {
  const colors = useColors();
  const item = (color, label) => (
    <View style={{ flexDirection: "row", alignItems: "center", gap: space[1.5] }}>
      <View style={{ width: 10, height: 10, borderRadius: 5, backgroundColor: color }} />
      <Text style={{ fontSize: 14 }}>{label}</Text>
    </View>
  );

  return (
    <View style={{ flexDirection: "row", justifyContent: "center", gap: space[6] }}>
      {item(colors.brand, target)}
      {item(colors.success, achieved)}
    </View>
  );
}
