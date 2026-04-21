import { Link, useForm, usePage } from "@inertiajs/react";

import { AuthLayout } from "@/layouts/AuthLayout";
import { Alert, AlertDescription } from "@/components/ui/alert";
import {
    Field,
    FieldContent,
    FieldError,
    FieldGroup,
    FieldLabel,
} from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { useCountdown, formatCountdown } from "@/hooks/use-countdown";

interface SharedProps extends Record<string, unknown> {
    auth?: {
        throttle?: {
            secondsUntilRetry?: number | null;
            attemptsRemaining?: number | null;
        };
    };
}

type FormSubmitHandler = NonNullable<React.ComponentProps<"form">["onSubmit"]>;

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        username: "",
        password: "",
        remember: false,
    });
    const { props } = usePage<SharedProps>();
    const lockedFor = props.auth?.throttle?.secondsUntilRetry ?? 0;
    const attemptsRemaining = props.auth?.throttle?.attemptsRemaining;
    const remaining = useCountdown(lockedFor);
    const locked = remaining > 0;

    const submit: FormSubmitHandler = (event) => {
        event.preventDefault();
        post("/scp/login");
    };

    return (
        <AuthLayout
            title="Sign in to the console."
            subtitle="Access the osTicket staff support panel with your credentials. Two-factor authentication may be required."
            tag="Login · Staff"
            eyebrowAccent="orange"
            sectionIndex="01"
            footer={
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Link
                        href="/scp/password/forgot"
                        className="auth-link-btn"
                    >
                        Forgot password
                    </Link>
                    <span className="auth-caption text-muted-foreground">
                        Session · Restricted
                    </span>
                </div>
            }
        >
            {locked && (
                <Alert
                    variant="warning"
                    className="mb-6 border-[#fcd34d] bg-[#fffbeb]"
                >
                    <AlertDescription className="auth-caption text-[#92400e]">
                        Rate limit reached. Retry in{" "}
                        {formatCountdown(remaining)}.
                    </AlertDescription>
                </Alert>
            )}
            <form onSubmit={submit} noValidate>
                <FieldGroup className="gap-6">
                    <Field
                        data-invalid={!!errors.username}
                        data-disabled={processing || locked}
                    >
                        <FieldLabel
                            htmlFor="username"
                            className="auth-caption mb-2 text-muted-foreground"
                        >
                            Username
                        </FieldLabel>
                        <FieldContent>
                            <Input
                                id="username"
                                name="username"
                                type="text"
                                autoComplete="username"
                                autoFocus
                                aria-invalid={!!errors.username}
                                value={data.username}
                                onChange={(e) =>
                                    setData("username", e.target.value)
                                }
                                disabled={processing || locked}
                            />
                            <FieldError
                                errors={
                                    errors.username
                                        ? [{ message: errors.username }]
                                        : undefined
                                }
                            />
                        </FieldContent>
                    </Field>

                    <Field
                        data-invalid={!!errors.password}
                        data-disabled={processing || locked}
                    >
                        <div className="flex items-center justify-between">
                            <FieldLabel
                                htmlFor="password"
                                className="auth-caption text-muted-foreground"
                            >
                                Password
                            </FieldLabel>
                            <Link
                                href="/scp/password/forgot"
                                className="auth-caption text-muted-foreground transition-colors hover:text-[#ec4899]"
                            >
                                Forgot →
                            </Link>
                        </div>
                        <FieldContent className="mt-2">
                            <Input
                                id="password"
                                name="password"
                                type="password"
                                autoComplete="current-password"
                                aria-invalid={!!errors.password}
                                value={data.password}
                                onChange={(e) =>
                                    setData("password", e.target.value)
                                }
                                disabled={processing || locked}
                            />
                            <FieldError
                                errors={
                                    errors.password
                                        ? [{ message: errors.password }]
                                        : undefined
                                }
                            />
                            {!locked &&
                                typeof attemptsRemaining === "number" &&
                                attemptsRemaining < 5 &&
                                attemptsRemaining > 0 && (
                                    <p className="auth-caption mt-1 text-[#b45309]">
                                        {attemptsRemaining} attempt
                                        {attemptsRemaining === 1 ? "" : "s"}{" "}
                                        remaining before lockout
                                    </p>
                                )}
                        </FieldContent>
                    </Field>

                    <Field
                        orientation="horizontal"
                        className="items-center gap-3"
                        data-disabled={processing || locked}
                    >
                        <label className="relative flex cursor-pointer items-center">
                            <input
                                id="remember"
                                name="remember"
                                type="checkbox"
                                checked={data.remember}
                                onChange={(e) =>
                                    setData("remember", e.target.checked)
                                }
                                disabled={processing || locked}
                                className="peer sr-only"
                            />
                            <span
                                className="flex h-[18px] w-[18px] items-center justify-center rounded-[3px] border border-border bg-background text-transparent transition-all peer-checked:border-foreground peer-checked:bg-foreground peer-checked:text-background peer-focus-visible:ring-2 peer-focus-visible:ring-[#f97316]/40 peer-disabled:opacity-50"
                                aria-hidden
                            >
                                <svg
                                    className="h-3 w-3"
                                    viewBox="0 0 12 12"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="2.5"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                >
                                    <path d="M2.5 6.5 L5 9 L9.5 3.5" />
                                </svg>
                            </span>
                        </label>
                        <FieldLabel
                            htmlFor="remember"
                            className="cursor-pointer text-sm font-normal text-foreground"
                        >
                            Keep me signed in on this device
                        </FieldLabel>
                    </Field>
                </FieldGroup>

                <div className="mt-8">
                    <button
                        type="submit"
                        disabled={processing || locked}
                        className="auth-submit"
                    >
                        {processing ? "Authenticating…" : "Enter Console →"}
                    </button>
                </div>
            </form>
        </AuthLayout>
    );
}
