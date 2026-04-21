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

interface Props {
    token: string;
}

type FormSubmitHandler = NonNullable<React.ComponentProps<"form">["onSubmit"]>;

export default function ResetPassword({ token }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        password: "",
        password_confirmation: "",
    });

    const submit: FormSubmitHandler = (event) => {
        event.preventDefault();
        post("/scp/password/reset");
    };

    return (
        <AuthLayout
            title="Set a new password."
            subtitle="Choose something long, unique, and memorable. You'll be signed back in right after."
            tag="Recovery · New credential"
            eyebrowAccent="indigo"
            sectionIndex="03"
            footer={
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Link href="/scp/login" className="auth-link-btn">
                        ← Back to login
                    </Link>
                    <span className="auth-caption text-muted-foreground">
                        Credential update
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
                            New password
                        </FieldLabel>
                        <FieldContent>
                            <Input
                                id="password"
                                name="password"
                                type="password"
                                autoComplete="new-password"
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

                    <Field
                        data-invalid={!!errors.password_confirmation}
                        data-disabled={processing}
                    >
                        <FieldLabel
                            htmlFor="password_confirmation"
                            className="auth-caption mb-2 text-muted-foreground"
                        >
                            Confirm password
                        </FieldLabel>
                        <FieldContent>
                            <Input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                autoComplete="new-password"
                                aria-invalid={!!errors.password_confirmation}
                                value={data.password_confirmation}
                                onChange={(event) =>
                                    setData(
                                        "password_confirmation",
                                        event.target.value,
                                    )
                                }
                                disabled={processing}
                            />
                            <FieldError
                                errors={errors.password_confirmation}
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
                        {processing ? "Resetting…" : "Set New Password →"}
                    </button>
                </div>
            </form>
        </AuthLayout>
    );
}
