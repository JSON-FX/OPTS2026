import { Input } from "@/Components/ui/input";
import type { ComponentProps } from "react";

// Keep the submitted decimal as a string; grouping never rounds an amount.
export function formatPhpAmount(value: string): string {
    const [whole, fraction] = value.split(".");
    const grouped = (whole || "0").replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    return value === "" ? "" : grouped + (fraction === undefined ? "" : "." + fraction);
}

export function parsePhpAmount(value: string): string | null {
    const clean = value.replace(/[₱,\s]/g, "");
    if (!/^\d*(?:\.\d{0,2})?$/.test(clean)) return null;
    return clean.replace(/^0+(?=\d)/, "");
}

type Props = Omit<ComponentProps<typeof Input>, "value" | "onChange" | "type"> & {
    value: string;
    onValueChange: (value: string) => void;
};

export function PhpAmountInput({ value, onValueChange, className = "", onBlur, ...props }: Props) {
    return <div className="op-php-input">
        <span aria-hidden="true">₱</span>
        <Input {...props} type="text" inputMode="decimal" placeholder="0.00"
            className={className} value={formatPhpAmount(value)}
            onChange={(event) => {
                const input = event.currentTarget;
                const raw = input.value;
                const parsed = parsePhpAmount(raw);
                if (parsed === null) return;
                const significantBefore = raw.slice(0, input.selectionStart ?? raw.length).replace(/[^\d.]/g, "").length;
                onValueChange(parsed);
                queueMicrotask(() => {
                    if (document.activeElement !== input) return;
                    let seen = 0;
                    let cursor = 0;
                    while (cursor < input.value.length && seen < significantBefore) {
                        if (/[\d.]/.test(input.value[cursor])) seen++;
                        cursor++;
                    }
                    input.setSelectionRange(cursor, cursor);
                });
            }}
            onBlur={(event) => {
                if (value !== "") {
                    const [whole, fraction = ""] = value.split(".");
                    onValueChange((whole || "0") + "." + fraction.padEnd(2, "0"));
                }
                onBlur?.(event);
            }} />
    </div>;
}
