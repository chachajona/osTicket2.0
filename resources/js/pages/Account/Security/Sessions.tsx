import { Button } from "@/components/ui/button";
import { HugeiconsIcon } from "@hugeicons/react";
import {
    ComputerIcon,
    MapPinIcon,
} from "@hugeicons/core-free-icons";

export default function Sessions() {
    return (
        <div className="space-y-6">
            <div className="space-y-4">
                <h3 className="auth-eyebrow text-muted-foreground">Current Session</h3>

                <div className="flex flex-col sm:flex-row items-start sm:items-center gap-4 rounded-md border border-[#C4A5F3]/30 bg-[#F5F0FE] p-4 relative overflow-hidden">
                    <div className="absolute left-0 top-0 bottom-0 w-1 bg-[#C4A5F3]"></div>
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white border border-[#C4A5F3]/20 text-[#5B619D]">
                        <HugeiconsIcon icon={ComputerIcon} className="size-5" />
                    </div>

                    <div className="flex-1 space-y-1">
                        <div className="flex items-center gap-2">
                            <span className="font-display font-medium text-foreground">Current browser session</span>
                            <span className="inline-flex items-center rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">
                                Details unavailable
                            </span>
                        </div>
                        <div className="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 font-mono text-xs text-muted-foreground">
                            <span>Session metadata is not connected yet.</span>
                            <span className="hidden sm:inline">•</span>
                            <span className="flex items-center gap-1 font-body">
                                <HugeiconsIcon icon={MapPinIcon} className="size-3" />
                                Location unavailable
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div className="space-y-4">
                <h3 className="auth-eyebrow text-muted-foreground">Other Sessions</h3>

                <div className="rounded-md border border-dashed border-[#E2E8F0] bg-white p-5 text-sm text-muted-foreground">
                    Session listing and revocation require backend session-management endpoints before they can be enabled.
                </div>
            </div>

            <div className="pt-2 border-t border-[#E2E8F0]">
                <Button variant="destructive" disabled className="font-body w-full sm:w-auto">
                    Sign out of all other sessions
                </Button>
            </div>
        </div>
    );
}
