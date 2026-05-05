import { useMemo } from "react";

import { cn } from "@/lib/utils";

interface PasswordStrengthProps {
    password: string;
    minLength?: number;
    className?: string;
    id?: string;
}

interface Rule {
    key: string;
    label: string;
    test: (password: string) => boolean;
}

interface RuleResult {
    rule: Rule;
    ok: boolean;
}

function buildRules(minLength: number): Rule[] {
    return [
        {
            key: "len",
            label: `At least ${minLength} characters`,
            test: (password) => password.length >= minLength,
        },
        {
            key: "upper",
            label: "Includes an uppercase letter",
            test: (password) => /[A-Z]/.test(password),
        },
        {
            key: "lower",
            label: "Includes a lowercase letter",
            test: (password) => /[a-z]/.test(password),
        },
        {
            key: "digit",
            label: "Includes a number",
            test: (password) => /[0-9]/.test(password),
        },
        {
            key: "symbol",
            label: "Includes a symbol",
            test: (password) => /[^A-Za-z0-9]/.test(password),
        },
    ];
}

function PasswordStrength({
    password,
    minLength = 8,
    className,
    id,
}: PasswordStrengthProps) {
    const rules = useMemo(() => buildRules(minLength), [minLength]);
    const results = useMemo<RuleResult[]>(
        () => rules.map((rule) => ({ rule, ok: rule.test(password) })),
        [password, rules],
    );
    const passed = results.filter((result) => result.ok).length;
    const ratio = passed / results.length;

    let label = "";
    if (ratio > 0 && ratio < 0.4) {
        label = "Weak";
    } else if (ratio < 0.8) {
        label = ratio === 0 ? "" : "Okay";
    } else {
        label = "Strong";
    }

    const barColor =
        ratio < 0.4
            ? "bg-red-500"
            : ratio < 0.8
              ? "bg-amber-500"
              : "bg-green-500";
    const liveSummary =
        passed === 0 && !label
            ? "No password requirements met yet."
            : `${passed} of ${results.length} password requirements met${label ? `, ${label}` : ""}.`;

    return (
        <div className={cn("space-y-2", className)} id={id}>
            <div className="flex items-center gap-2">
                <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-200">
                    <div
                        className={cn("h-full transition-all", barColor)}
                        style={{ width: `${ratio * 100}%` }}
                        aria-hidden="true"
                    />
                </div>
                <span
                    className="sr-only"
                    aria-live="polite"
                    aria-atomic="true"
                >
                    {liveSummary}
                </span>
                {label && (
                    <span className="text-xs font-medium text-gray-600">
                        {label}
                    </span>
                )}
            </div>
            <ul className="space-y-1 text-xs" aria-live="off">
                {results.map(({ rule, ok }) => (
                    <li
                        key={rule.key}
                        className={cn(
                            "flex items-center gap-1.5",
                            ok ? "text-green-700" : "text-gray-500",
                        )}
                    >
                        <span aria-hidden="true">{ok ? "✓" : "○"}</span>
                        <span>{rule.label}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

export default PasswordStrength;
