import { cn } from "@/lib/utils";
import { useMemo } from "react";
interface PasswordStregthProps {
    password: string;
    minLength?: number;
    className?: string;
    id?: string;
}

interface Rule {
    key: string;
    label: string;
    test: (pw: string) => boolean;
}

function buildRules(minLength: number): Rule[] {
    return [
        {
            key: "len",
            label: `At least ${minLength} characters`,
            test: (pw) => pw.length >= minLength,
        },
        {
            key: "upper",
            label: "Includes an uppercase letter",
            test: (pw) => /[A-Z]/.test(pw),
        },
        {
            key: "lower",
            label: "Includes a lowercase letter",
            test: (pw) => /[a-z]/.test(pw),
        },
        {
            key: "digit",
            label: "Includes a number",
            test: (pw) => /[0-9]/.test(pw),
        },
        {
            key: "symbol",
            label: "Includes a symbol",
            test: (pw) => /[^A-Za-z0-9]/.test(pw),
        },
    ];
}

function PasswordStregth({
    password,
    minLength = 8,
    className,
    id,
}: PasswordStregthProps) {
    const rules = useMemo(() => buildRules(minLength), [minLength]);
    const passed = rules.filter((r) => r.test(password)).length;
    const ratio = passed / rules.length;
    const label =
        ratio === 0
            ? ""
            : ratio < 0.4
              ? "Weak"
              : ratio < 0.8
                ? "Okay"
                : "Strong";
    const barColor =
        ratio < 0.4
            ? "bg-red-500"
            : ratio < 0.8
              ? "bg-amber-500"
              : "bg-green-500";

    return (
        <div className={cn("space-y-2", className)} id={id} aria-live="polite">
            <div className="flex items-center gap-2">
                <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-200">
                    <div
                        className={cn("h-full transition-all", barColor)}
                        style={{ width: `${ratio * 100}%` }}
                        aria-hidden="true"
                    />
                </div>
                {label && (
                    <span className="text-xs font-medium text-gray-600">
                        {label}
                    </span>
                )}
            </div>
            <ul className="space-y-1 text-xs">
                {rules.map((rule) => {
                    const ok = rule.test(password);
                    return (
                        <li
                            key={rule.key}
                            className={cn(
                                "flex items-center gap-1.5",
                                ok ? "text-green -700" : "text-gray-500",
                            )}
                        >
                            <span aria-hidden="true">{ok ? "✓" : "○"}</span>
                            <span>{rule.label}</span>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}

export default PasswordStregth;
