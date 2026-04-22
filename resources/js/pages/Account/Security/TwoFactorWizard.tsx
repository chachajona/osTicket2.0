import { Link, router, useForm } from "@inertiajs/react";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from "@/components/ui/input-otp";
import { Label } from "@/components/ui/label";
import { StepPanel, Stepper } from "@/components/ui/stepper";
import { useClipboard } from "@/hooks/use-clipboard";

interface PageProps {
    step: number;
    twoFactor: {
        enabled: boolean;
        pending: boolean;
        method: "app" | null;
        qrCodeSvg: string | null;
        qrCodeUrl: string | null;
        setupKey: string | null;
        recoveryCodes: string[];
    };
}

const STEPS = [
    { key: "method", label: "Choose method" },
    { key: "setup", label: "Set up" },
    { key: "verify", label: "Verify" },
    { key: "recovery", label: "Recovery codes" },
    { key: "done", label: "Done" },
];

function go(step: number) {
    router.get(
        "/scp/account/security/two-factor",
        { step },
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

export default function TwoFactorWizard({ step, twoFactor }: PageProps) {
    return (
        <div className="min-h-screen bg-gray-50 px-4 py-10">
            <div className="mx-auto max-w-4xl space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-gray-900">
                            Two-factor authentication
                        </h1>
                        <p className="mt-2 text-sm text-gray-500">
                            Add an authenticator app to harden your account and finish the migration.
                        </p>
                    </div>
                    <Link
                        href="/scp/account/security"
                        className="text-sm font-medium text-blue-600 hover:underline"
                    >
                        Back to security settings
                    </Link>
                </div>

                <section className="rounded-2xl bg-white p-6 shadow-sm sm:p-8">
                    <Stepper steps={STEPS} current={step - 1} className="flex-wrap" />

                    <StepPanel>
                        {step === 1 && <ChooseMethod />}
                        {step === 2 && <SetUp twoFactor={twoFactor} />}
                        {step === 3 && <Verify />}
                        {step === 4 && <Recovery codes={twoFactor.recoveryCodes} />}
                        {step === 5 && <Done />}
                    </StepPanel>
                </section>
            </div>
        </div>
    );
}

function ChooseMethod() {
    const form = useForm({ force: true, return_to_wizard: true });

    return (
        <div className="space-y-4">
            <p className="text-sm text-gray-600">
                Choose how you want to receive verification codes. We recommend an authenticator app for the strongest protection.
            </p>

            <div className="grid gap-3">
                <button
                    type="button"
                    onClick={() =>
                        form.post("/scp/account/security/two-factor/enable", {
                            preserveScroll: true,
                        })
                    }
                    disabled={form.processing}
                    className="flex items-start gap-3 rounded-lg border border-gray-300 p-4 text-left transition hover:border-blue-500 hover:bg-blue-50 disabled:opacity-50"
                >
                    <span className="text-xl" aria-hidden="true">
                        📱
                    </span>
                    <span>
                        <span className="block font-medium text-gray-900">
                            Authenticator app
                        </span>
                        <span className="block text-sm text-gray-500">
                            Use Google Authenticator, 1Password, Authy, or another TOTP app.
                        </span>
                    </span>
                </button>

                <div className="rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-500">
                    Email login codes remain available for the legacy sign-in path while you finish this upgrade.
                </div>
            </div>
        </div>
    );
}

function SetUp({ twoFactor }: { twoFactor: PageProps["twoFactor"] }) {
    const { copied, copy } = useClipboard();

    if (!twoFactor.pending || !twoFactor.qrCodeSvg) {
        return (
            <Alert variant="warning">
                <AlertDescription>
                    Setup has not started yet. Go back to step 1 and begin the authenticator app flow.
                </AlertDescription>
            </Alert>
        );
    }

    return (
        <div className="space-y-4">
            <p className="text-sm text-gray-600">
                Scan this QR code with your authenticator app, or enter the setup key manually if scanning is unavailable.
            </p>

            <div className="flex justify-center rounded-lg border border-gray-200 bg-gray-50 p-4">
                <div dangerouslySetInnerHTML={{ __html: twoFactor.qrCodeSvg }} />
            </div>

            {twoFactor.setupKey && (
                <div className="space-y-2">
                    <Label htmlFor="setup-key">Setup key</Label>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Input id="setup-key" value={twoFactor.setupKey} readOnly />
                        <Button
                            variant="outline"
                            type="button"
                            onClick={() => copy(twoFactor.setupKey ?? "")}
                        >
                            {copied ? "Copied!" : "Copy"}
                        </Button>
                    </div>
                </div>
            )}

            <div className="flex justify-end gap-2">
                <Button variant="ghost" type="button" onClick={() => go(1)}>
                    Back
                </Button>
                <Button type="button" onClick={() => go(3)}>
                    Next: verify
                </Button>
            </div>
        </div>
    );
}

function Verify() {
    const { data, setData, post, processing, errors } = useForm({
        code: "",
        return_to_wizard: true,
    });

    const submit = (code: string) => {
        setData("code", code);
        setData("return_to_wizard", true);
        post("/scp/account/security/two-factor/confirm", {
            preserveScroll: true,
        });
    };

    return (
        <div className="space-y-4">
            <p className="text-sm text-gray-600">
                Enter the 6-digit code from your authenticator app to confirm setup.
            </p>

            <InputOTP
                maxLength={6}
                value={data.code}
                onChange={(value) => setData("code", value)}
                disabled={processing}
                autoFocus
                aria-invalid={!!errors.code}
                containerClassName="justify-center"
            >
                <InputOTPGroup className="gap-1.5">
                    {[0, 1, 2, 3, 4, 5].map((index) => (
                        <InputOTPSlot
                            key={index}
                            index={index}
                            className="size-12 rounded border border-border bg-background text-lg text-foreground first:rounded-l last:rounded-r"
                        />
                    ))}
                </InputOTPGroup>
            </InputOTP>

            {errors.code && (
                <p className="text-center text-xs text-red-600" role="alert">
                    {errors.code}
                </p>
            )}

            <div className="flex justify-end gap-2">
                <Button variant="ghost" type="button" onClick={() => go(2)}>
                    Back
                </Button>
                <Button
                    type="button"
                    disabled={processing || data.code.length < 6}
                    onClick={() => submit(data.code)}
                >
                    {processing ? "Verifying…" : "Verify"}
                </Button>
            </div>
        </div>
    );
}

function Recovery({ codes }: { codes: string[] }) {
    const { copy, copied } = useClipboard();
    const text = codes.join("\n");

    function download() {
        const blob = new Blob([text], { type: "text/plain" });
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement("a");

        anchor.href = url;
        anchor.download = "osticket-recovery-codes.txt";
        anchor.click();
        URL.revokeObjectURL(url);
    }

    if (codes.length === 0) {
        return (
            <Alert variant="warning">
                <AlertDescription>
                    Recovery codes were not revealed in this session. Return to the security page to regenerate them.
                </AlertDescription>
            </Alert>
        );
    }

    return (
        <div className="space-y-4">
            <Alert variant="warning">
                <AlertDescription>
                    Save these recovery codes somewhere safe. Each code can be used once if you lose access to your authenticator app.
                </AlertDescription>
            </Alert>

            <ul className="grid gap-2 rounded-lg border border-gray-200 bg-gray-50 p-4 font-mono text-sm text-gray-900 sm:grid-cols-2">
                {codes.map((code) => (
                    <li key={code} className="select-all rounded bg-white px-3 py-2">
                        {code}
                    </li>
                ))}
            </ul>

            <div className="flex flex-wrap gap-2">
                <Button variant="outline" type="button" onClick={() => copy(text)}>
                    {copied ? "Copied!" : "Copy all"}
                </Button>
                <Button variant="outline" type="button" onClick={download}>
                    Download .txt
                </Button>
                <Button variant="outline" type="button" onClick={() => window.print()}>
                    Print
                </Button>
            </div>

            <div className="flex justify-end">
                <Button type="button" onClick={() => go(5)}>
                    I&apos;ve saved them
                </Button>
            </div>
        </div>
    );
}

function Done() {
    return (
        <div className="space-y-4 text-center">
            <p className="text-4xl" aria-hidden="true">
                ✅
            </p>
            <p className="text-sm text-gray-700">
                Two-factor authentication is now enabled for your staff account.
            </p>
            <Link
                href="/scp/account/security"
                className="inline-block text-sm font-medium text-blue-600 hover:underline"
            >
                Return to security settings
            </Link>
        </div>
    );
}
