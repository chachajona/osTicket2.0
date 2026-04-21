import { Link, useForm } from "@inertiajs/react";

import { AuthLayout } from "@/layouts/AuthLayout";
import {
    Field,
    FieldContent,
    FieldError,
    FieldGroup,
    FieldLabel,
} from "@/components/ui/field";
import { Input } from "@/components/ui/input";

type FormSubmitHandler = NonNullable<React.ComponentProps<"form">["onSubmit"]>;

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors } = useForm({
        password: "",
    });

    const submit: FormSubmitHandler = (event) => {
        event.preventDefault();
        post("/scp/account/security/confirm-password");
    };

    return (
        <AuthLayout
            title="Confirm your password."
            subtitle="Re-enter your password to continue to two-factor authentication settings."
            tag="Security · Re-auth"
            eyebrowAccent="orange"
            sectionIndex="04"
            footer={
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Link
                        href="/scp/account/security"
                        className="auth-link-btn"
                    >
                        ← Back to security settings
                    </Link>
                    <span className="auth-caption text-muted-foreground">
                        Elevated access
                    </span>
                </div>
            }
        >
            <form onSubmit={submit} noValidate>
                <FieldGroup className="gap-6">
                    <Field
                        data-invalid={!!errors.password}
                        data-disabled={processing}
                    >
                        <FieldLabel
                            htmlFor="password"
                            className="auth-caption mb-2 text-muted-foreground"
                        >
                            Password
                        </FieldLabel>
                        <FieldContent>
                            <Input
                                id="password"
                                name="password"
                                type="password"
                                autoComplete="current-password"
                                autoFocus
                                aria-invalid={!!errors.password}
                                value={data.password}
                                onChange={(event) =>
                                    setData("password", event.target.value)
                                }
                                disabled={processing}
                            />
                            <FieldError
                                errors={errors.password}
                            />
                        </FieldContent>
                    </Field>
                </FieldGroup>

                <div className="mt-8">
                    <button
                        type="submit"
                        disabled={processing}
                        className="auth-submit"
                    >
                        {processing ? "Confirming…" : "Confirm and Continue →"}
                    </button>
                </div>
            </form>
        </AuthLayout>
    );
}
