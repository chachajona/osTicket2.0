import { router, usePage } from "@inertiajs/react";

import {
    Alert,
    AlertAction,
    AlertDescription,
    AlertTitle,
} from "@/components/ui/alert";
import { Button } from "@/components/ui/button";

interface SharedProps extends Record<string, unknown> {
    auth?: {
        staff?: {
            migrationBanner?: boolean;
        } | null;
    };
}

export function MigrationBanner() {
    const { props } = usePage<SharedProps>();
    const visible = props.auth?.staff?.migrationBanner;

    if (!visible) {
        return null;
    }

    return (
        <Alert className="mb-6 rounded-2xl border-blue-200 bg-blue-50 text-blue-900">
            <AlertTitle>Your account moved to the new authentication system</AlertTitle>
            <AlertDescription>
                Add an authenticator app now to harden your staff account and complete the migration.
            </AlertDescription>
            <AlertAction className="static mt-4 flex flex-wrap gap-2 sm:absolute sm:top-3 sm:right-3 sm:mt-0">
                <Button
                    size="sm"
                    onClick={() =>
                        router.get(
                            "/scp/account/security/two-factor",
                            { step: 1 },
                            { preserveScroll: true },
                        )
                    }
                >
                    Enable 2FA
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() =>
                        router.post(
                            "/scp/account/migration-banner/dismiss",
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    Dismiss
                </Button>
            </AlertAction>
        </Alert>
    );
}
