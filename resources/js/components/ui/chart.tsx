"use client";

import * as React from "react";
import * as RechartsPrimitive from "recharts";
import type { TooltipProps } from "recharts";
import { cn } from "./utils";

/* =========================
   Theme config
========================= */

const THEMES = { light: "", dark: ".dark" } as const;

export type ChartConfig = {
  [k in string]: {
    label?: React.ReactNode;
    icon?: React.ComponentType;
  } & (
    | { color?: string; theme?: never }
    | { color?: never; theme: Record<keyof typeof THEMES, string> }
  );
};

/* =========================
   Context
========================= */

type ChartContextProps = {
  config: ChartConfig;
};

const ChartContext = React.createContext<ChartContextProps | null>(null);

function useChart() {
  const context = React.useContext(ChartContext);
  if (!context) {
    throw new Error("useChart must be used within a <ChartContainer />");
  }
  return context;
}

/* =========================
   Chart Container
========================= */

function ChartContainer({
  id,
  className,
  children,
  config,
  ...props
}: React.ComponentProps<"div"> & {
  config: ChartConfig;
  children: React.ComponentProps<
    typeof RechartsPrimitive.ResponsiveContainer
  >["children"];
}) {
  const uniqueId = React.useId();
  const chartId = `chart-${id || uniqueId.replace(/:/g, "")}`;

  return (
    <ChartContext.Provider value={{ config }}>
      <div
        data-chart={chartId}
        className={cn(
          "flex aspect-video justify-center text-xs [&_.recharts-layer]:outline-hidden",
          className,
        )}
        {...props}
      >
        <ChartStyle id={chartId} config={config} />
        <RechartsPrimitive.ResponsiveContainer>
          {children}
        </RechartsPrimitive.ResponsiveContainer>
      </div>
    </ChartContext.Provider>
  );
}

/* =========================
   Dynamic CSS Style
========================= */

const ChartStyle = ({ id, config }: { id: string; config: ChartConfig }) => {
  const colorConfig = Object.entries(config).filter(
    ([, cfg]) => cfg.color || cfg.theme,
  );

  if (!colorConfig.length) return null;

  return (
    <style
      dangerouslySetInnerHTML={{
        __html: Object.entries(THEMES)
          .map(
            ([theme, prefix]) => `
${prefix} [data-chart=${id}] {
${colorConfig
  .map(([key, cfg]) => {
    const color =
      cfg.theme?.[theme as keyof typeof cfg.theme] || cfg.color;
    return color ? `  --color-${key}: ${color};` : "";
  })
  .join("\n")}
}`,
          )
          .join("\n"),
      }}
    />
  );
};

/* =========================
   Tooltip
========================= */

const ChartTooltip = RechartsPrimitive.Tooltip;

function ChartTooltipContent<
  TValue extends number = number,
  TName extends string = string,
>(
  props: TooltipProps<TValue, TName> &
    React.ComponentProps<"div"> & {
      hideLabel?: boolean
      hideIndicator?: boolean
      indicator?: "line" | "dot" | "dashed"
      nameKey?: string
      labelKey?: string
      payload?: any[]
      label?: any
      labelFormatter?: (value: any, payload: any[]) => React.ReactNode
      labelClassName?: string
      color?: string
    }
) {
  const {
    active,
    payload,
    className,
    hideLabel = false,
    hideIndicator = false,
    label,
    labelFormatter,
    labelClassName,
    color,
    nameKey,
    labelKey,
  } = props

  const { config } = useChart();

  if (!active || !payload || payload.length === 0) return null;

  const tooltipLabel = !hideLabel
    ? (() => {
        const item = payload[0];
        const key = `${labelKey || item.dataKey || item.name || "value"}`;
        const itemConfig = getPayloadConfigFromPayload(config, item, key);

        const value =
          typeof label === "string"
            ? config[label]?.label || label
            : itemConfig?.label;

        if (!value) return null;

        return (
          <div className={cn("font-medium", labelClassName)}>
            {labelFormatter ? labelFormatter(value, payload) : value}
          </div>
        );
      })()
    : null;

  return (
    <div
      className={cn(
        "grid min-w-[8rem] gap-1.5 rounded-lg border bg-background px-2.5 py-1.5 text-xs shadow-xl",
        className,
      )}
    >
      {tooltipLabel}
      {payload.map((item: any, index: number) => {
        const key = `${nameKey || item.name || item.dataKey || "value"}`;
        const itemConfig = getPayloadConfigFromPayload(config, item, key);
        const indicatorColor = color || item.color;

        return (
          <div
            key={index}
            className="flex items-center justify-between gap-2"
          >
            <div className="flex items-center gap-1.5">
              {!hideIndicator && (
                <div
                  className="h-2.5 w-2.5 rounded-sm"
                  style={{ backgroundColor: indicatorColor }}
                />
              )}
              <span className="text-muted-foreground">
                {itemConfig?.label || item.name}
              </span>
            </div>
            {typeof item.value === "number" && (
              <span className="font-mono font-medium tabular-nums">
                {item.value.toLocaleString()}
              </span>
            )}
          </div>
        );
      })}
    </div>
  );
}

/* =========================
   Legend
========================= */

const ChartLegend = RechartsPrimitive.Legend;

type LegendPayloadItem = {
  value?: string;
  dataKey?: string;
  color?: string;
};

function ChartLegendContent({
  className,
  hideIcon = false,
  payload,
  verticalAlign = "bottom",
  nameKey,
}: React.ComponentProps<"div"> & {
  payload?: LegendPayloadItem[];
  verticalAlign?: "top" | "bottom";
  hideIcon?: boolean;
  nameKey?: string;
}) {
  const { config } = useChart();

  if (!payload || payload.length === 0) return null;

  return (
    <div
      className={cn(
        "flex items-center justify-center gap-4",
        verticalAlign === "top" ? "pb-3" : "pt-3",
        className,
      )}
    >
      {payload.map((item, index) => {
        const key = `${nameKey || item.dataKey || "value"}`;
        const itemConfig = config[key];

        return (
          <div key={index} className="flex items-center gap-1.5">
            {!hideIcon ? (
              itemConfig?.icon ? (
                <itemConfig.icon />
              ) : (
                <div
                  className="h-2 w-2 rounded-sm"
                  style={{ backgroundColor: item.color }}
                />
              )
            ) : null}
            <span>{itemConfig?.label || item.value}</span>
          </div>
        );
      })}
    </div>
  );
}

/* =========================
   Helper
========================= */

function getPayloadConfigFromPayload(
  config: ChartConfig,
  payload: unknown,
  key: string,
) {
  if (typeof payload !== "object" || payload === null) return undefined;

  const p = payload as Record<string, unknown>;
  const nested =
    typeof p.payload === "object" && p.payload !== null
      ? (p.payload as Record<string, unknown>)
      : undefined;

  const resolvedKey =
    typeof p[key] === "string"
      ? (p[key] as string)
      : nested && typeof nested[key] === "string"
        ? (nested[key] as string)
        : key;

  return config[resolvedKey] || config[key];
}

export {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
  ChartLegend,
  ChartLegendContent,
  ChartStyle,
};