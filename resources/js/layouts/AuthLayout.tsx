import { type CSSProperties, type ReactNode } from "react";
import { usePage } from "@inertiajs/react";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { cn } from "@/lib/utils";

type EyebrowAccent = "purple" | "indigo" | "emerald" | "gradient";

interface AuthLayoutProps {
    title: string;
    subtitle?: string;
    tag?: string;
    eyebrowAccent?: EyebrowAccent;
    sectionIndex?: string;
    children: ReactNode;
    footer?: ReactNode;
    className?: string;
}

interface SharedProps extends Record<string, unknown> {
    status?: string;
    errors?: Record<string, string>;
}

function rise(delay: number): CSSProperties {
    return { animationDelay: `${delay}ms` };
}

function ArrowUpRight({ className }: { className?: string }) {
    return (
        <svg
            className={className}
            viewBox="0 0 16 16"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.25"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            <path d="M5 11 L11 5" />
            <path d="M6.5 5 L11 5 L11 9.5" />
        </svg>
    );
}

export function AuthLayout({
    title,
    subtitle,
    tag,
    eyebrowAccent = "purple",
    sectionIndex = "01",
    children,
    footer,
    className,
}: AuthLayoutProps) {
    const { props } = usePage<SharedProps>();
    const status = props.status;
    const generalError = props.errors?.general || props.errors?._;
    const year = new Date().getFullYear();

    return (
        <div className="auth-theme relative flex min-h-screen flex-col bg-background text-foreground antialiased">
            <div className="auth-mesh" aria-hidden />
            <div className="auth-grain" aria-hidden />

            {/* Top bar — 3-column grid, uppercase micro meta. */}
            <header className="relative z-10 mx-auto grid w-full max-w-310 grid-cols-3 items-center gap-4 px-6 py-5 sm:px-10">
                <span className="auth-caption text-foreground">
                    osTicket
                    <span className="text-muted-foreground">
                        {" "}
                        · Staff Console
                    </span>
                </span>
                <span className="auth-caption hidden justify-self-center text-muted-foreground sm:inline-flex">
                    Secure Session · Encrypted
                </span>
                <a
                    href="mailto:support@osticket.com"
                    className="auth-link-btn justify-self-end text-foreground"
                >
                    Need help
                    <ArrowUpRight className="h-3 w-3" />
                </a>
            </header>

            <div className="relative z-10 mx-auto w-full max-w-310 px-6 sm:px-10">
                <div className="h-px w-full bg-border" />
            </div>

            {/* Editorial grid: 12-col on desktop, stacked on mobile. */}
            <main className="relative z-10 mx-auto grid w-full max-w-310 flex-1 place-content-center grid-cols-12 gap-6 px-6 py-12 sm:px-10 sm:py-16 lg:gap-10 lg:py-20">
                {/* Left rail — section number + meta caption. */}
                <aside className="col-span-12 lg:col-span-3 lg:pt-4">
                    <div
                        className="auth-rise flex items-start gap-3"
                        style={rise(0)}
                    >
                        <span className="font-sans text-[10px] font-medium leading-3.75 tracking-widest text-muted-foreground">
                            {sectionIndex}
                        </span>
                        <div className="mt-1.5 h-px w-6 bg-foreground" />
                        <span className="auth-caption text-muted-foreground">
                            {tag ?? "Authentication"}
                        </span>
                    </div>
                </aside>

                {/* Main column — headline, card, footer. */}
                <section className="col-span-12 lg:col-span-9">
                    {tag && (
                        <span
                            className="auth-eyebrow auth-rise"
                            data-accent={eyebrowAccent}
                            style={rise(60)}
                        >
                            {tag}
                        </span>
                    )}

                    <h1
                        className={cn(
                            "auth-rise mt-5 font-sans font-medium text-foreground",
                            "text-[44px] leading-none tracking-[-0.04em]",
                            "sm:text-[64px] sm:leading-none",
                            "lg:text-[80px] lg:leading-[0.96] lg:tracking-[-0.05em]",
                        )}
                        style={rise(120)}
                    >
                        {title}
                    </h1>

                    {subtitle && (
                        <p
                            className="auth-rise mt-6 max-w-140 font-sans text-sm leading-[22.75px] text-muted-foreground"
                            style={rise(200)}
                        >
                            {subtitle}
                        </p>
                    )}

                    <div className="mt-8 w-full max-w-140 space-y-3">
                        {status && (
                            <Alert
                                variant="success"
                                className="auth-rise border-border bg-background text-foreground"
                                style={rise(280)}
                            >
                                <AlertDescription className="text-foreground/80">
                                    {status}
                                </AlertDescription>
                            </Alert>
                        )}
                        {generalError && (
                            <Alert
                                variant="destructive"
                                className="auth-rise border-[#fecaca] bg-[#fef2f2]"
                                style={rise(280)}
                            >
                                <AlertDescription>
                                    {generalError}
                                </AlertDescription>
                            </Alert>
                        )}
                    </div>

                    {/* Gradient border shell card */}
                    <div
                        className="auth-rise mt-6 w-full max-w-140"
                        style={rise(340)}
                    >
                        <div className="auth-shell">
                            <div
                                className={cn(
                                    "auth-shell-inner p-8 sm:p-10",
                                    className,
                                )}
                            >
                                {children}
                            </div>
                        </div>
                    </div>

                    {footer && (
                        <div
                            className="auth-rise mt-6 max-w-140"
                            style={rise(440)}
                        >
                            {footer}
                        </div>
                    )}
                </section>
            </main>

            <div className="relative z-10 mx-auto w-full max-w-310 px-6 sm:px-10">
                <div className="h-px w-full bg-border" />
            </div>

            <footer className="relative z-10 mx-auto grid w-full max-w-310 grid-cols-3 items-center gap-4 px-6 py-5 sm:px-10">
                <span className="auth-caption text-muted-foreground">
                    © {year} · osticket.com
                </span>
                <span className="auth-caption hidden justify-self-center text-muted-foreground sm:inline-flex">
                    v2.0 · Support Suite
                </span>
                <span className="auth-caption justify-self-end text-muted-foreground">
                    Rate-limited
                </span>
            </footer>
        </div>
    );
}
