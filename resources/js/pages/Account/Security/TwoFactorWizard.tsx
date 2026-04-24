import { Link, router, useForm } from "@inertiajs/react";
import { type ReactElement, type ReactNode } from 'react';

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import DashboardLayout from '@/layouts/DashboardLayout';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from "@/components/ui/input-otp";
import { Label } from "@/components/ui/label";
import { StepPanel, Stepper } from "@/components/ui/stepper";
import { useClipboard } from "@/hooks/use-clipboard";
import { HugeiconsIcon } from "@hugeicons/react";
import { ShieldCheck } from "@hugeicons/core-free-icons";

interface PageProps {
    step: number;
    twoFactor: {
        enabled: boolean;
        pending: boolean;
        method: "app" | null;
        qrCodeSvg: string | null;
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
        { preserveScroll: true, preserveState: true },
    );
}

function getAllowedStep(step: number, twoFactor: PageProps["twoFactor"]): number {
    let maxStep = 1;

    if (twoFactor.enabled) {
        maxStep = 5;
    } else if (twoFactor.pending) {
        maxStep = 3;
    }

    const requestedStep = Number.isFinite(step) ? step : 1;

    return Math.min(Math.max(requestedStep, 1), maxStep);
}

function getQrCodeImageSrc(svg: string): string {
    return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
}

export default function TwoFactorWizard({ step, twoFactor }: PageProps) {
    const currentStep = getAllowedStep(step, twoFactor);

    return (
        <section className="auth-shell mt-2">
            <div className="auth-shell-inner p-6 sm:p-8">
                <Stepper steps={STEPS} current={currentStep - 1} className="flex-wrap mb-8" />

                <StepPanel>
                    {currentStep === 1 && <ChooseMethod />}
                    {currentStep === 2 && <SetUp twoFactor={twoFactor} />}
                    {currentStep === 3 && <Verify />}
                    {currentStep === 4 && <Recovery codes={twoFactor.recoveryCodes} />}
                    {currentStep === 5 && <Done />}
                </StepPanel>
            </div>
        </section>
    );
}

type TwoFactorWizardPageComponent = typeof TwoFactorWizard & {
    layout?: (page: ReactElement) => ReactNode;
};

(TwoFactorWizard as TwoFactorWizardPageComponent).layout = (page: ReactElement) => (
    <DashboardLayout
        title="Secure Your Account"
        subtitle="Add an authenticator app to complete your migration."
        eyebrow="Two-Factor Authentication"
        activeNav="security"
        contentClassName="max-w-4xl mx-auto"
        headerActions={null}
    >
        {page}
    </DashboardLayout>
);

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
                    className="flex items-start gap-3 rounded-md border border-[#E2E8F0] bg-[#F8FAFC] p-4 text-left transition duration-150 hover:border-[#C4A5F3] hover:bg-white cursor-pointer disabled:opacity-50"
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

                <div className="rounded-md border border-dashed border-[#E2E8F0] bg-white p-4 text-xs text-[#94A3B8]">
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

            <div className="rounded-md border border-[#E2E8F0] bg-[#F8FAFC] p-6 flex justify-center">
                <img
                    src={getQrCodeImageSrc(twoFactor.qrCodeSvg)}
                    alt="Two-factor authentication QR code"
                    width={220}
                    height={220}
                    loading="lazy"
                    className="h-auto w-[220px]"
                />
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

    const submit = () => {
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
                    onClick={submit}
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
        anchor.rel = "noopener";
        document.body.append(anchor);
        anchor.click();
        anchor.remove();
        setTimeout(() => URL.revokeObjectURL(url), 0);
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

            <ul className="grid gap-2 rounded-md border border-[#E2E8F0] bg-[#F8FAFC] p-4 font-mono text-sm text-[#0F172A] sm:grid-cols-2">
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
            <div className="w-10 h-10 rounded-full bg-emerald-50 border border-emerald-200 flex items-center justify-center mx-auto mb-4">
                <HugeiconsIcon icon={ShieldCheck} size={20} color="#059669" />
            </div>
            <p className="text-sm text-gray-700">
                Two-factor authentication is now enabled for your staff account.
            </p>
            <Link
                href="/scp/account/security"
                className="inline-block text-sm font-medium text-[#5B619D] hover:underline mt-2"
            >
                Return to security settings
            </Link>
        </div>
    );
}
