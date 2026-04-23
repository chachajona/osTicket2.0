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

export default function ForgotPassword() {
    const { data, setData, post, processing, errors } = useForm({
        email: "",
    });

    const submit: FormSubmitHandler = (event) => {
        event.preventDefault();
        post("/scp/password/forgot");
    };

    return (
        <AuthLayout
            title="Recover your access."
            subtitle="Enter the email on file and we'll send a secure, single-use link to reset your password."
            tag="Recovery · Email"
            eyebrowAccent="emerald"
            sectionIndex="02"
            footer={
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Link href="/scp/login" className="auth-link-btn">
                        ← Back to login
                    </Link>
                    <span className="auth-caption text-muted-foreground">
                        One-time link
                    </span>
                </div>
            }
        >
            <form onSubmit={submit} noValidate>
                <FieldGroup className="gap-6">
                    <Field
                        data-invalid={!!errors.email}
                        data-disabled={processing}
                    >
                        <FieldLabel
                            htmlFor="email"
                            className="auth-caption mb-2 text-muted-foreground"
                        >
                            Email address
                        </FieldLabel>
                        <FieldContent>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                autoComplete="email"
                                autoFocus
                                placeholder="you@company.com"
                                aria-invalid={!!errors.email}
                                value={data.email}
                                onChange={(event) =>
                                    setData("email", event.target.value)
                                }
                                disabled={processing}
                            />
                            <FieldError
                                errors={errors.email}
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
                        {processing ? "Sending link…" : "Send Reset Link →"}
                    </button>
                </div>
            </form>
        </AuthLayout>
    );
}
