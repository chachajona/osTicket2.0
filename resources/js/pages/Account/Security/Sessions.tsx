import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";

export default function Sessions() {
    return (
        <Alert className="rounded-2xl border-dashed border-border bg-muted/30">
            <AlertTitle>Sessions are coming next</AlertTitle>
            <AlertDescription>
                Phase 3 adds active session visibility, single-session revocation, and a sign-out-everywhere flow.
            </AlertDescription>
        </Alert>
    );
}
