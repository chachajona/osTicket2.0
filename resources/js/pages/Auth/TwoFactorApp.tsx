import { Link, useForm } from "@inertiajs/react";
import { useState } from "react";

import { AuthLayout } from "@/layouts/AuthLayout";
import {
    Field,
    FieldContent,
    FieldError,
    FieldGroup,
    FieldLabel,
} from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from "@/components/ui/input-otp";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";

type FormSubmitHandler = NonNullable<React.ComponentProps<"form">["onSubmit"]>;

type Mode = "app" | "recovery";

export default function TwoFactorApp() {
    const { data, setData, post, processing, errors } = useForm({
        code: "",
    });
    const [mode, setMode] = useState<Mode>("app");

    const submit: FormSubmitHandler = (event) => {
        event.preventDefault();
        post("/scp/2fa-app");
    };

    const canSubmit =
        !processing &&
        (mode === "app" ? data.code.length === 6 : data.code.length >= 8);

    return (
        <AuthLayout
            title="Authenticator required."
            subtitle="Enter the 6-digit code from your authenticator app, or use a single-use recovery code."
            tag="2FA · Authenticator"
            eyebrowAccent="gradient"
            sectionIndex="06"
            footer={
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Link href="/scp/login" className="auth-link-btn">
                        ← Back to login
                    </Link>
                    <span className="auth-caption text-muted-foreground">
                        Second factor
                    </span>
                </div>
            }
        >
            <Tabs
                value={mode}
                onValueChange={(value) => {
                    setMode(value as Mode);
                    setData("code", "");
                }}
                className="gap-6"
            >
                <TabsList
                    variant="line"
                    className="w-full justify-start gap-6 border-b border-border"
                >
                    <TabsTrigger
                        value="app"
                        className="auth-caption px-0 pb-3 text-muted-foreground data-active:text-foreground"
                    >
                        App code
                    </TabsTrigger>
                    <TabsTrigger
                        value="recovery"
                        className="auth-caption px-0 pb-3 text-muted-foreground data-active:text-foreground"
                    >
                        Recovery code
                    </TabsTrigger>
                </TabsList>

                <form onSubmit={submit} noValidate>
                    <TabsContent value="app" className="mt-0">
                        <FieldGroup className="gap-6">
                            <Field
                                data-invalid={!!errors.code}
                                data-disabled={processing}
                            >
                                <FieldLabel
                                    htmlFor="app-code"
                                    className="auth-caption mb-2 text-muted-foreground"
                                >
                                    6-digit code
                                </FieldLabel>
                                <FieldContent className="items-center">
                                    <InputOTP
                                        id="app-code"
                                        maxLength={6}
                                        value={mode === "app" ? data.code : ""}
                                        onChange={(value) =>
                                            setData("code", value)
                                        }
                                        disabled={processing}
                                        autoFocus={mode === "app"}
                                        containerClassName="justify-center"
                                        aria-invalid={!!errors.code}
                                    >
                                        <InputOTPGroup className="gap-1.5">
                                            {[0, 1, 2, 3, 4, 5].map((i) => (
                                                <InputOTPSlot
                                                    key={i}
                                                    index={i}
                                                    className="size-12 rounded border border-border bg-background text-lg text-foreground first:rounded-l last:rounded-r"
                                                />
                                            ))}
                                        </InputOTPGroup>
                                    </InputOTP>
                                    <FieldError
                                        errors={
                                            errors.code && mode === "app"
                                                ? [{ message: errors.code }]
                                                : undefined
                                        }
                                    />
                                </FieldContent>
                            </Field>
                        </FieldGroup>
                    </TabsContent>

                    <TabsContent value="recovery" className="mt-0">
                        <FieldGroup className="gap-6">
                            <Field
                                data-invalid={!!errors.code}
                                data-disabled={processing}
                            >
                                <FieldLabel
                                    htmlFor="recovery-code"
                                    className="auth-caption mb-2 text-muted-foreground"
                                >
                                    Recovery code
                                </FieldLabel>
                                <FieldContent>
                                    <Input
                                        id="recovery-code"
                                        name="code"
                                        type="text"
                                        autoComplete="one-time-code"
                                        autoFocus={mode === "recovery"}
                                        placeholder="xxxx-xxxx-xxxx"
                                        aria-invalid={!!errors.code}
                                        value={
                                            mode === "recovery" ? data.code : ""
                                        }
                                        onChange={(event) =>
                                            setData("code", event.target.value)
                                        }
                                        disabled={processing}
                                        className="tracking-[0.12em]"
                                    />
                                    <FieldError
                                        errors={
                                            errors.code && mode === "recovery"
                                                ? [{ message: errors.code }]
                                                : undefined
                                        }
                                    />
                                </FieldContent>
                            </Field>
                        </FieldGroup>
                    </TabsContent>

                    <div className="mt-8">
                        <button
                            type="submit"
                            disabled={!canSubmit}
                            className="auth-submit"
                        >
                            {processing ? "Verifying…" : "Verify and Continue →"}
                        </button>
                    </div>
                </form>
            </Tabs>
        </AuthLayout>
    );
}
