import { Button } from "@/components/ui/button";
import { HugeiconsIcon } from "@hugeicons/react";
import {
    ComputerIcon,
    SmartPhone01Icon,
    MapPinIcon,
} from "@hugeicons/core-free-icons";

type DeviceIcon = typeof ComputerIcon | typeof SmartPhone01Icon;

interface OtherSession {
    icon: DeviceIcon;
    device: string;
    ip: string;
    location: string;
    lastSeen: string;
}

const OTHER_SESSIONS: OtherSession[] = [
    { icon: SmartPhone01Icon, device: 'iOS • Chrome', ip: '10.0.0.25', location: 'Tokyo, JP', lastSeen: '3 days ago' },
    { icon: ComputerIcon, device: 'Windows 11 • Edge', ip: '172.16.0.4', location: 'London, UK', lastSeen: '1 week ago' },
];

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
                            <span className="font-display font-medium text-foreground">Mac OS • Safari</span>
                            <span className="inline-flex items-center rounded-full bg-green-500/10 px-2 py-0.5 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                                Active now
                            </span>
                        </div>
                        <div className="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 font-mono text-xs text-muted-foreground">
                            <span>192.168.1.1</span>
                            <span className="hidden sm:inline">•</span>
                            <span className="flex items-center gap-1 font-body">
                                <HugeiconsIcon icon={MapPinIcon} className="size-3" />
                                Ho Chi Minh City, VN
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div className="space-y-4">
                <h3 className="auth-eyebrow text-muted-foreground">Other Sessions</h3>

                <div className="space-y-3">
                    {OTHER_SESSIONS.map(({ icon, device, ip, location, lastSeen }) => (
                        <div key={device} className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 rounded-md border border-[#E2E8F0] bg-white p-4">
                            <div className="flex items-center gap-4">
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-muted/50 border border-border text-muted-foreground">
                                    <HugeiconsIcon icon={icon} className="size-5" />
                                </div>
                                <div className="space-y-1">
                                    <div className="font-display font-medium text-foreground">{device}</div>
                                    <div className="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 font-mono text-xs text-muted-foreground">
                                        <span>{ip}</span>
                                        <span className="hidden sm:inline">•</span>
                                        <span className="flex items-center gap-1 font-body">
                                            <HugeiconsIcon icon={MapPinIcon} className="size-3" />
                                            {location}
                                        </span>
                                        <span className="hidden sm:inline">•</span>
                                        <span className="font-body text-muted-foreground/80">{lastSeen}</span>
                                    </div>
                                </div>
                            </div>
                            <Button variant="outline" size="sm" onClick={() => console.log("revoke")} className="w-full sm:w-auto shrink-0 font-body">
                                Revoke
                            </Button>
                        </div>
                    ))}
                </div>
            </div>

            <div className="pt-2 border-t border-[#E2E8F0]">
                <Button variant="destructive" onClick={() => console.log("sign out all")} className="font-body w-full sm:w-auto">
                    Sign out of all other sessions
                </Button>
            </div>
        </div>
    );
}
