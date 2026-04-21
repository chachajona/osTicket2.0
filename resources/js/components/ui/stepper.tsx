import { type ReactNode } from "react";

import { cn } from "@/lib/utils";

interface StepperStep {
    key: string;
    label: string;
}

interface StepperProps {
    steps: StepperStep[];
    current: number; // 0-indexed
    className?: string;
}
function Stepper({ steps, current, className }: StepperProps) {
    return (
        <ol
            className={cn("flex items-center gap-2", className)}
            aria-label="Progress"
        >
            {steps.map((step, index) => {
                const status: "done" | "current" | "upcoming" =
                    index < current
                        ? "done"
                        : index === current
                          ? "current"
                          : "upcoming";
                return (
                    <li key={step.key} className="flex items-center gap-2">
                        <span
                            aria-current={
                                status === "current" ? "step" : undefined
                            }
                            className={cn(
                                "flex size-7 items-center justify-center rounded-full text-xs font-semibold",
                                status === "done" && "bg-blue-600 text-white",
                                status === "current" &&
                                    "bg-blue-100 text-blue-700 ring-2 ring-blue-600",
                                status === "upcoming" &&
                                    "bg-gray-100 text-gray-500",
                            )}
                        >
                            {status === "done" ? "✓" : index + 1}
                        </span>
                        <span
                            className={cn(
                                "hidden text-sm sm:inline",
                                status === "current"
                                    ? "font-medium text-gray-900"
                                    : "text-gray-500",
                            )}
                        >
                            {step.label}
                        </span>
                        {index < steps.length - 1 && (
                            <span
                                aria-hidden="true"
                                className={cn(
                                    "h-px w-8 sm:w-12",
                                    index < current
                                        ? "bg-blue-600"
                                        : "bg-gray-200",
                                )}
                            />
                        )}
                    </li>
                );
            })}
        </ol>
    );
}

export { Stepper, type StepperStep };

export function StepPanel({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return <div className={cn("mt-6", className)}>{children}</div>;
}
