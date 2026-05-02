import { Link, router, useForm } from "@inertiajs/react";
import { useState } from "react";
import { useTranslation } from "react-i18next";

import { AuthLayout } from "@/layouts/AuthLayout";
import {
    Field,
    FieldContent,
    FieldError,
    FieldGroup,
    FieldLabel,
} from "@/components/ui/field";
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from "@/components/ui/input-otp";

type FormSubmitHandler = NonNullable<React.ComponentProps<"form">["onSubmit"]>;

export default function TwoFactor() {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        code: "",
    });
    const [isResending, setIsResending] = useState(false);

    const submit: FormSubmitHandler = (event) => {
        event.preventDefault();
        post("/scp/2fa");
    };

    function resend() {
        router.post(
            "/scp/2fa/resend",
            {},
            {
                preserveScroll: true,
                onStart: () => setIsResending(true),
                onFinish: () => setIsResending(false),
            },
        );
    }

    return (
        <AuthLayout
            title={t("auth.two_factor.title")}
            subtitle={t("auth.two_factor.description")}
            tag="2FA · Email code"
            eyebrowAccent="indigo"
            sectionIndex="05"
            footer={
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Link href="/scp/login" className="auth-link-btn">
                        ← {t("auth.two_factor.back_to_login")}
                    </Link>
                    <button
                        type="button"
                        onClick={resend}
                        disabled={processing || isResending}
                        className="auth-link-btn disabled:opacity-50"
                    >
                        {isResending
                            ? t("auth.two_factor.resending")
                            : t("auth.two_factor.resend")}
                    </button>
                </div>
            }
        >
            <form onSubmit={submit} noValidate>
                <FieldGroup className="gap-6">
                    <Field
                        data-invalid={!!errors.code}
                        data-disabled={processing}
                    >
                        <FieldLabel
                            htmlFor="code"
                            className="auth-caption mb-2 text-muted-foreground"
                        >
                            {t("auth.two_factor.code_label")}
                        </FieldLabel>
                        <FieldContent className="items-center">
                            <InputOTP
                                id="code"
                                maxLength={6}
                                value={data.code}
                                onChange={(value) => setData("code", value)}
                                disabled={processing}
                                autoFocus
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
                                errors={errors.code}
                            />
                        </FieldContent>
                    </Field>
                </FieldGroup>

                <div className="mt-8">
                    <button
                        type="submit"
                        disabled={processing || data.code.length < 6}
                        className="auth-submit"
                    >
                        {processing
                            ? t("auth.two_factor.verifying")
                            : `${t("auth.two_factor.verify")} →`}
                    </button>
                </div>
            </form>
        </AuthLayout>
    );
}
