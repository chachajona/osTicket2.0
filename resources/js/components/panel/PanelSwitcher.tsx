import { router, usePage } from '@inertiajs/react';
import { HugeiconsIcon } from '@hugeicons/react';
import { CheckmarkBadge01Icon, UnfoldMoreIcon } from '@hugeicons/core-free-icons';
import { useTranslation } from 'react-i18next';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';

type PanelKey = 'scp' | 'admin';

interface PanelMeta {
    nameKey: string;
    nameDefault: string;
    captionKey: string;
    captionDefault: string;
    monogram: string;
    plateClass: string;
}

const PANELS: Record<PanelKey, PanelMeta> = {
    scp: {
        nameKey: 'dashboard.layout.agent_panel',
        nameDefault: 'Agent Panel',
        captionKey: 'dashboard.layout.agent_panel_caption',
        captionDefault: 'Support Suite',
        monogram: 'A',
        plateClass: 'bg-gradient-to-br from-[#FB923C] via-[#EC4899] to-[#6366F1] text-white',
    },
    admin: {
        nameKey: 'dashboard.layout.admin_panel',
        nameDefault: 'Admin Panel',
        captionKey: 'dashboard.layout.admin_panel_caption',
        captionDefault: 'System Settings',
        monogram: 'A',
        plateClass: 'bg-[#5B619D] text-white',
    },
};

interface PanelPlateProps {
    panel: PanelKey;
    size?: number;
}

function PanelPlate({ panel, size = 32 }: PanelPlateProps) {
    const { plateClass, monogram } = PANELS[panel];
    return (
        <span
            aria-hidden
            className={cn(
                'grid shrink-0 place-items-center rounded-[8px] font-medium tracking-[0.02em] shadow-[0_2px_6px_-3px_rgba(24,24,27,0.35)]',
                plateClass,
            )}
            style={{ width: size, height: size, fontSize: Math.round(size * 0.45) }}
        >
            {monogram}
        </span>
    );
}

interface PanelSwitcherProps {
    collapsed?: boolean;
}

export function PanelSwitcher({ collapsed = false }: PanelSwitcherProps) {
    const { t } = useTranslation();
    const { props } = usePage<{
        currentPanel?: PanelKey;
        auth?: { staff?: { isAdmin?: boolean; canAccessAdminPanel?: boolean } | null };
    }>();

    const currentPanel: PanelKey = props.currentPanel ?? 'scp';
    const meta = PANELS[currentPanel];
    const canAccessAdminPanel =
        props.auth?.staff?.canAccessAdminPanel ?? props.auth?.staff?.isAdmin === true;

    const switchPanel = (panel: PanelKey) => {
        if (panel === currentPanel) return;
        router.post('/panel/switch', { panel });
    };

    const name = t(meta.nameKey, { defaultValue: meta.nameDefault });
    const caption = t(meta.captionKey, { defaultValue: meta.captionDefault });

    if (!canAccessAdminPanel) {
        return (
            <div
                className={cn(
                    'flex w-full items-center rounded-[8px]',
                    collapsed ? 'justify-center p-1' : 'gap-2.5 px-2 py-1.5',
                )}
                aria-label={name}
            >
                <PanelPlate panel={currentPanel} size={collapsed ? 32 : 32} />
                {!collapsed && (
                    <span className="min-w-0 flex-1 overflow-hidden">
                        <span className="block truncate text-[13px] font-medium leading-[18px] text-[#18181B]">
                            {name}
                        </span>
                        <span className="block truncate text-[10px] font-medium uppercase tracking-[0.1em] text-[#A1A1AA]">
                            {caption}
                        </span>
                    </span>
                )}
            </div>
        );
    }

    return (
        <Popover>
            <PopoverTrigger
                render={
                    <button
                        type="button"
                        aria-label={name}
                        className={cn(
                            'flex w-full items-center rounded-[8px] transition-colors hover:bg-[#F4F2EB] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5B619D]/40',
                            collapsed ? 'justify-center p-1' : 'gap-2.5 px-2 py-1.5',
                        )}
                    >
                        <PanelPlate panel={currentPanel} size={32} />
                        {!collapsed && (
                            <>
                                <span className="min-w-0 flex-1 overflow-hidden text-left">
                                    <span className="block truncate text-[13px] font-medium leading-[18px] text-[#18181B]">
                                        {name}
                                    </span>
                                    <span className="block truncate text-[10px] font-medium uppercase tracking-[0.1em] text-[#A1A1AA]">
                                        {caption}
                                    </span>
                                </span>
                                <span aria-hidden className="shrink-0 text-[#A1A1AA]">
                                    <HugeiconsIcon icon={UnfoldMoreIcon} size={14} strokeWidth={1.75} />
                                </span>
                            </>
                        )}
                    </button>
                }
            />
            <PopoverContent
                side={collapsed ? 'right' : 'bottom'}
                align={collapsed ? 'start' : 'start'}
                sideOffset={collapsed ? 10 : 6}
                className="w-56 p-1.5"
            >
                <div className="px-2 pt-1 pb-1.5 text-[10px] font-medium uppercase tracking-[0.1em] text-[#A1A1AA]">
                    {t('dashboard.layout.panels', { defaultValue: 'Panels' })}
                </div>
                <ul className="flex flex-col gap-0.5">
                    {(['scp', 'admin'] as PanelKey[]).map((panel) => {
                        const m = PANELS[panel];
                        const isActive = panel === currentPanel;
                        const label = t(m.nameKey, { defaultValue: m.nameDefault });
                        return (
                            <li key={panel}>
                                <button
                                    type="button"
                                    onClick={() => switchPanel(panel)}
                                    aria-current={isActive ? 'true' : undefined}
                                    className={cn(
                                        'flex w-full items-center gap-2.5 rounded-[6px] px-2.5 py-2 text-left text-[13px] transition-colors',
                                        isActive
                                            ? 'bg-[#F4F2EB] font-medium text-[#18181B]'
                                            : 'text-[#71717A] hover:bg-[#F4F2EB]/80 hover:text-[#18181B]',
                                    )}
                                >
                                    <PanelPlate panel={panel} size={22} />
                                    <span className="min-w-0 flex-1 truncate">{label}</span>
                                    {isActive && (
                                        <HugeiconsIcon
                                            icon={CheckmarkBadge01Icon}
                                            size={14}
                                            className="shrink-0 text-[#5B619D]"
                                        />
                                    )}
                                </button>
                            </li>
                        );
                    })}
                </ul>
            </PopoverContent>
        </Popover>
    );
}
