import React, { useMemo, useState, useEffect } from "react";
import {
  View,
  Text,
  ScrollView,
  StyleSheet,
  Platform,
  TouchableOpacity,
} from "react-native";

const mono = Platform.select({
  ios: "Menlo",
  android: "monospace",
  default: "monospace",
});

const THEME_LIGHT = {
  key: "#0f172a",
  string: "#15803d",
  number: "#2563eb",
  literal: "#2563eb",
  punct: "#0f172a",
  plain: "#0f172a",
};

const THEME_DARK = {
  key: "#e2e8f0",
  string: "#4ade80",
  number: "#93c5fd",
  literal: "#93c5fd",
  punct: "#cbd5e1",
  plain: "#e2e8f0",
};

export function formatJson(value, space = 2) {
  try {
    return JSON.stringify(
      value,
      (_, v) => (typeof v === "bigint" ? v.toString() : v),
      space
    );
  } catch (e) {
    return `[formatJson] ${e?.message ?? e}`;
  }
}

function splitIntoPages(fullText, linesPerPage) {
  const lines = fullText.split("\n");
  const pages = [];
  for (let i = 0; i < lines.length; i += linesPerPage) {
    pages.push(lines.slice(i, i + linesPerPage).join("\n"));
  }
  return pages.length ? pages : [""];
}

function collectContainerPaths(v, segments = ["$"], acc = []) {
  if (v === null || typeof v !== "object") return acc;
  acc.push(JSON.stringify(segments));
  if (Array.isArray(v)) {
    v.forEach((item, i) =>
      collectContainerPaths(item, [...segments, i], acc)
    );
  } else {
    Object.keys(v).forEach((k) =>
      collectContainerPaths(v[k], [...segments, k], acc)
    );
  }
  return acc;
}

function highlightJsonValueSuffix(rest, theme, fontSize) {
  const st = { fontSize, fontFamily: mono };
  let body = rest.trimEnd();
  let suffix = "";
  if (body.endsWith(",")) {
    suffix = ",";
    body = body.slice(0, -1).trimEnd();
  }

  if (body.startsWith('"')) {
    let j = 1;
    while (j < body.length) {
      if (body[j] === "\\") {
        j += 2;
        continue;
      }
      if (body[j] === '"') break;
      j++;
    }
    if (j < body.length) {
      const str = body.slice(0, j + 1);
      const tail = body.slice(j + 1);
      return (
        <Text style={st}>
          <Text style={{ color: theme.string }}>{str}</Text>
          {tail ? (
            <Text style={{ color: theme.plain }}>{tail}</Text>
          ) : null}
          {suffix ? (
            <Text style={{ color: theme.punct }}>{suffix}</Text>
          ) : null}
        </Text>
      );
    }
  }

  if (body === "true" || body === "false" || body === "null") {
    return (
      <Text style={st}>
        <Text style={{ color: theme.literal }}>{body}</Text>
        {suffix ? (
          <Text style={{ color: theme.punct }}>{suffix}</Text>
        ) : null}
      </Text>
    );
  }

  if (/^-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?$/.test(body)) {
    return (
      <Text style={st}>
        <Text style={{ color: theme.number }}>{body}</Text>
        {suffix ? (
          <Text style={{ color: theme.punct }}>{suffix}</Text>
        ) : null}
      </Text>
    );
  }

  return <Text style={[st, { color: theme.punct }]}>{rest}</Text>;
}

function highlightJsonLine(line, theme, fontSize) {
  const st = { fontSize, fontFamily: mono };
  const indentMatch = line.match(/^(\s*)/);
  const indent = indentMatch ? indentMatch[1] : "";
  const rest = line.slice(indent.length);

  const keyMatch = rest.match(/^("(?:[^"\\]|\\.)*")\s*(:)\s*(.*)$/);
  if (keyMatch) {
    const [, key, colon, valuePart] = keyMatch;
    return (
      <Text style={st}>
        <Text style={{ color: theme.plain }}>{indent}</Text>
        <Text style={{ color: theme.key }}>{key}</Text>
        <Text style={{ color: theme.punct }}>{colon} </Text>
        {highlightJsonValueSuffix(valuePart, theme, fontSize)}
      </Text>
    );
  }

  return (
    <Text style={st}>
      <Text style={{ color: theme.plain }}>{indent}</Text>
      {highlightJsonValueSuffix(rest, theme, fontSize)}
    </Text>
  );
}

function HighlightedJsonText({ source, dark, fontSize }) {
  const theme = dark ? THEME_DARK : THEME_LIGHT;
  const lines = source.split("\n");
  const preStyle = { fontFamily: mono, fontSize };

  return (
    <Text selectable style={preStyle}>
      {lines.map((line, i) => (
        <Text key={i}>
          {i > 0 ? "\n" : ""}
          {highlightJsonLine(line, theme, fontSize)}
        </Text>
      ))}
    </Text>
  );
}

function JsonCollapsible({ value, dark, fontSize }) {
  const theme = dark ? THEME_DARK : THEME_LIGHT;
  const paths = useMemo(() => collectContainerPaths(value), [value]);
  const [open, setOpen] = useState(() => new Set(paths));

  useEffect(() => {
    setOpen(new Set(collectContainerPaths(value)));
  }, [value]);

  const toggle = (id) => {
    setOpen((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const tx = { fontFamily: mono, fontSize };

  const line = (children) => (
    <View style={{ marginBottom: 2 }}>{children}</View>
  );

  function renderValue(v, segments, depth, keyName, showComma) {
    const pathId = JSON.stringify(segments);
    const pad = "  ".repeat(depth);
    const comma = showComma ? (
      <Text style={{ color: theme.punct }}>,</Text>
    ) : null;

    if (v === null) {
      return line(
        <Text style={tx}>
          <Text style={{ color: theme.plain }}>{pad}</Text>
          {keyName != null ? (
            <>
              <Text style={{ color: theme.key }}>{JSON.stringify(keyName)}</Text>
              <Text style={{ color: theme.punct }}>: </Text>
            </>
          ) : null}
          <Text style={{ color: theme.literal }}>null</Text>
          {comma}
        </Text>
      );
    }

    if (typeof v === "number") {
      return line(
        <Text style={tx}>
          <Text style={{ color: theme.plain }}>{pad}</Text>
          {keyName != null ? (
            <>
              <Text style={{ color: theme.key }}>{JSON.stringify(keyName)}</Text>
              <Text style={{ color: theme.punct }}>: </Text>
            </>
          ) : null}
          <Text style={{ color: theme.number }}>{String(v)}</Text>
          {comma}
        </Text>
      );
    }

    if (typeof v === "boolean") {
      return line(
        <Text style={tx}>
          <Text style={{ color: theme.plain }}>{pad}</Text>
          {keyName != null ? (
            <>
              <Text style={{ color: theme.key }}>{JSON.stringify(keyName)}</Text>
              <Text style={{ color: theme.punct }}>: </Text>
            </>
          ) : null}
          <Text style={{ color: theme.literal }}>{v ? "true" : "false"}</Text>
          {comma}
        </Text>
      );
    }

    if (typeof v === "string") {
      return line(
        <Text style={tx}>
          <Text style={{ color: theme.plain }}>{pad}</Text>
          {keyName != null ? (
            <>
              <Text style={{ color: theme.key }}>{JSON.stringify(keyName)}</Text>
              <Text style={{ color: theme.punct }}>: </Text>
            </>
          ) : null}
          <Text style={{ color: theme.string }}>{JSON.stringify(v)}</Text>
          {comma}
        </Text>
      );
    }

    if (Array.isArray(v)) {
      const expanded = open.has(pathId);
      return (
        <View key={pathId}>
          <TouchableOpacity onPress={() => toggle(pathId)} activeOpacity={0.65}>
            <Text style={tx}>
              <Text style={{ color: theme.plain }}>{pad}</Text>
              {keyName != null ? (
                <>
                  <Text style={{ color: theme.key }}>{JSON.stringify(keyName)}</Text>
                  <Text style={{ color: theme.punct }}>: </Text>
                </>
              ) : null}
              <Text style={{ color: theme.punct }}>{expanded ? "▼ " : "▶ "}</Text>
              <Text style={{ color: theme.punct }}>[</Text>
              {!expanded ? (
                <Text style={{ color: theme.plain }}>
                  {v.length === 0 ? "]" : ` ${v.length} … ]`}
                </Text>
              ) : null}
              {expanded && v.length === 0 ? (
                <Text style={{ color: theme.punct }}> ]</Text>
              ) : null}
              {!expanded ? comma : null}
              {expanded && v.length === 0 ? comma : null}
            </Text>
          </TouchableOpacity>
          {expanded &&
            v.length > 0 &&
            v.map((item, i) => (
              <View key={i}>
                {renderValue(item, [...segments, i], depth + 1, null, i < v.length - 1)}
              </View>
            ))}
          {expanded && v.length > 0 ? (
            <Text style={tx}>
              <Text style={{ color: theme.plain }}>{pad}</Text>
              <Text style={{ color: theme.punct }}>]</Text>
              {comma}
            </Text>
          ) : null}
        </View>
      );
    }

    const keys = Object.keys(v);
    const expanded = open.has(pathId);
    return (
      <View key={pathId}>
        <TouchableOpacity onPress={() => toggle(pathId)} activeOpacity={0.65}>
          <Text style={tx}>
            <Text style={{ color: theme.plain }}>{pad}</Text>
            {keyName != null ? (
              <>
                <Text style={{ color: theme.key }}>{JSON.stringify(keyName)}</Text>
                <Text style={{ color: theme.punct }}>: </Text>
              </>
            ) : null}
            <Text style={{ color: theme.punct }}>{expanded ? "▼ " : "▶ "}</Text>
            <Text style={{ color: theme.punct }}>{"{"}</Text>
            {!expanded ? (
              <Text style={{ color: theme.plain }}>
                {keys.length === 0 ? "}" : ` ${keys.length} … }`}
              </Text>
            ) : null}
            {expanded && keys.length === 0 ? (
              <Text style={{ color: theme.punct }}> {"}"}</Text>
            ) : null}
            {!expanded ? comma : null}
            {expanded && keys.length === 0 ? comma : null}
          </Text>
        </TouchableOpacity>
        {expanded &&
          keys.length > 0 &&
          keys.map((k, i) => (
            <View key={k}>
              {renderValue(v[k], [...segments, k], depth + 1, k, i < keys.length - 1)}
            </View>
          ))}
        {expanded && keys.length > 0 ? (
          <Text style={tx}>
            <Text style={{ color: theme.plain }}>{pad}</Text>
            <Text style={{ color: theme.punct }}>{"}"}</Text>
            {comma}
          </Text>
        ) : null}
      </View>
    );
  }

  return <View>{renderValue(value, ["$"], 0, null, false)}</View>;
}

function parseData(data) {
  if (typeof data === "string") {
    try {
      return JSON.parse(data);
    } catch {
      return null;
    }
  }
  return data;
}

/**
 * @param {boolean} [props.collapsible=false] — ▶/▼ buka tutup object/array (seperti inspector)
 * @param {boolean} [props.highlight=true] — warna sintaks (non-collapsible)
 * Jika collapsible=true, paginate diabaikan.
 */
export function JsonView({
  data,
  style,
  maxHeight = 360,
  fontSize = 12,
  dark = true,
  paginate = false,
  linesPerPage = 45,
  highlight = true,
  collapsible = false,
  ...scrollProps
}) {
  const parsed = useMemo(() => parseData(data), [data]);
  const text = useMemo(() => {
    if (typeof data === "string") {
      try {
        return formatJson(JSON.parse(data));
      } catch {
        return data;
      }
    }
    return formatJson(data);
  }, [data]);

  const usePager = paginate && !collapsible;
  const pages = useMemo(() => {
    if (!usePager) return [text];
    return splitIntoPages(text, Math.max(1, linesPerPage));
  }, [text, usePager, linesPerPage]);

  const [page, setPage] = useState(0);

  useEffect(() => {
    setPage(0);
  }, [text, usePager, linesPerPage]);

  useEffect(() => {
    if (page >= pages.length) {
      setPage(Math.max(0, pages.length - 1));
    }
  }, [pages.length, page]);

  const display = pages[page] ?? "";
  const total = pages.length;
  const labelStyle = dark ? styles.txtDark : styles.txtLight;
  const btnDisabled = dark ? styles.pagerBtnTxtDisabledDark : styles.pagerBtnTxtDisabledLight;
  const panelBg = dark ? "#0f172a" : "#f1f5f9";
  const scrollStyle = { maxHeight, backgroundColor: panelBg };
  const scrollContent = [styles.pad, { backgroundColor: panelBg }];
  const plainFallback = dark ? THEME_DARK.plain : THEME_LIGHT.plain;
  const isErrorText = text.startsWith("[formatJson]");
  const canTree =
    collapsible &&
    parsed !== null &&
    typeof parsed === "object";

  let body;
  if (canTree) {
    body = <JsonCollapsible value={parsed} dark={dark} fontSize={fontSize} />;
  } else if (highlight && !isErrorText) {
    body = (
      <HighlightedJsonText source={display} dark={dark} fontSize={fontSize} />
    );
  } else {
    body = (
      <Text
        selectable
        style={[
          styles.pre,
          { fontSize, fontFamily: mono, color: plainFallback },
        ]}
      >
        {display}
      </Text>
    );
  }

  return (
    <View style={[dark ? styles.boxDark : styles.boxLight, style]}>
      <ScrollView
        nestedScrollEnabled
        style={scrollStyle}
        contentContainerStyle={scrollContent}
        {...scrollProps}
      >
        {body}
      </ScrollView>

      {usePager ? (
        <View
          style={[
            styles.pagerRow,
            { backgroundColor: panelBg },
            dark ? styles.pagerRowDark : styles.pagerRowLight,
          ]}
        >
          <TouchableOpacity
            onPress={() => setPage((p) => Math.max(0, p - 1))}
            disabled={page <= 0}
            style={styles.pagerBtn}
          >
            <Text style={[styles.pagerBtnTxt, labelStyle, page <= 0 && btnDisabled]}>
              ‹ Sebelum
            </Text>
          </TouchableOpacity>
          <Text style={[styles.pagerMeta, labelStyle]}>
            {page + 1} / {total}
          </Text>
          <TouchableOpacity
            onPress={() => setPage((p) => Math.min(total - 1, p + 1))}
            disabled={page >= total - 1}
            style={styles.pagerBtn}
          >
            <Text
              style={[
                styles.pagerBtnTxt,
                labelStyle,
                page >= total - 1 && btnDisabled,
              ]}
            >
              Sesudah ›
            </Text>
          </TouchableOpacity>
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  boxDark: {
    borderRadius: 8,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: "#334155",
    backgroundColor: "#0f172a",
  },
  boxLight: {
    borderRadius: 8,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: "#cbd5e1",
    backgroundColor: "#f8fafc",
  },
  pad: { padding: 10 },
  pre: {},
  txtDark: { color: "#f8fafc" },
  txtLight: { color: "#020617" },
  pagerRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingHorizontal: 10,
    paddingVertical: 8,
    borderTopWidth: StyleSheet.hairlineWidth,
  },
  pagerRowDark: { borderTopColor: "#334155" },
  pagerRowLight: { borderTopColor: "#cbd5e1" },
  pagerBtn: { paddingVertical: 4, paddingHorizontal: 4 },
  pagerBtnTxt: { fontSize: 13, fontWeight: "600" },
  pagerBtnTxtDisabledDark: { color: "#475569" },
  pagerBtnTxtDisabledLight: { color: "#94a3b8" },
  pagerMeta: { fontSize: 12, fontVariant: ["tabular-nums"] },
});
