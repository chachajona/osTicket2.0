import { router, usePage } from '@inertiajs/react';
import { HugeiconsIcon } from '@hugeicons/react';
import { CheckmarkBadge01Icon, ArrowDown01Icon, ShieldCheck } from '@hugeicons/core-free-icons';
import { useTranslation } from 'react-i18next';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';

interface PanelSwitcherProps {
    collapsed?: boolean;
}

export function PanelSwitcher({ collapsed }: PanelSwitcherProps) {
    const { t } = useTranslation();
    const { props } = usePage<{
        currentPanel?: 'scp' | 'admin';
        auth?: { staff?: { isAdmin?: boolean; canAccessAdminPanel?: boolean } | null };
    }>();

    const currentPanel = props.currentPanel || 'scp';
    const canAccessAdminPanel = props.auth?.staff?.canAccessAdminPanel ?? (props.auth?.staff?.isAdmin === true);

    const switchPanel = (panel: 'scp' | 'admin') => {
        router.post('/panel/switch', { panel });
    };

    const triggerContent = (
        <div className={cn('flex items-center gap-3', collapsed ? 'justify-center w-full' : 'justify-between w-full')}>
            <div className={cn('flex items-center gap-3', collapsed && 'justify-center')}>
                <div
                    className={cn(
                        'flex h-9 w-9 items-center justify-center rounded-full text-[11px] font-semibold tracking-[0.02em] text-white shadow-[0_12px_24px_-18px_rgba(91,97,157,0.9)]',
                        currentPanel === 'admin' ? 'bg-[#5B619D]' : 'auth-gradient',
                    )}
                >
                    {currentPanel === 'admin' ? 'ADM' : 'SCP'}
                </div>
                {!collapsed && (
                    <div className="text-left">
                        <div className="text-sm font-medium leading-none text-[#18181B]">
                            {currentPanel === 'admin' ? 'osTicket Admin' : 'osTicket SCP'}
                        </div>
                        <div className="mt-1 text-[10px] font-medium uppercase tracking-[0.1em] text-[#A1A1AA]">
                            {currentPanel === 'admin' ? 'System Settings' : 'Agent Panel'}
                        </div>
                    </div>
                )}
            </div>
            {!collapsed && canAccessAdminPanel && (
                <HugeiconsIcon icon={ArrowDown01Icon} size={16} className="text-[#A1A1AA]" />
            )}
        </div>
    );

    if (!canAccessAdminPanel) {
        return (
            <div className={cn('flex items-center', collapsed ? 'justify-center w-full' : 'justify-between w-full')}>
                {triggerContent}
            </div>
        );
    }

    return (
        <Popover>
            <PopoverTrigger
                render={
                    <button
                        type="button"
                        className="flex w-full items-center rounded-lg p-1.5 transition-colors hover:bg-white/50 focus:outline-none focus:ring-2 focus:ring-[#5B619D] focus:ring-offset-2"
                    >
                        {triggerContent}
                    </button>
                }
            />
            <PopoverContent align={collapsed ? 'center' : 'start'} sideOffset={4} className="w-56 p-1.5">
                <div className="flex flex-col gap-1">
                    <button
                        type="button"
                        onClick={() => switchPanel('scp')}
                        className={cn(
                            'flex w-full items-center justify-between rounded-md px-2.5 py-2 text-sm font-medium transition-colors',
                            currentPanel === 'scp'
                                ? 'bg-[#F4F2EB] text-[#18181B]'
                                : 'text-[#71717A] hover:bg-[#F4F2EB]/80 hover:text-[#18181B]',
                        )}
                    >
                        <span>{t('dashboard.layout.agent_panel', { defaultValue: 'Agent Panel' })}</span>
                        {currentPanel === 'scp' && <HugeiconsIcon icon={CheckmarkBadge01Icon} size={16} className="text-[#5B619D]" />}
                    </button>
                    <button
                        type="button"
                        onClick={() => switchPanel('admin')}
                        className={cn(
                            'flex w-full items-center justify-between rounded-md px-2.5 py-2 text-sm font-medium transition-colors',
                            currentPanel === 'admin'
                                ? 'bg-[#F4F2EB] text-[#18181B]'
                                : 'text-[#71717A] hover:bg-[#F4F2EB]/80 hover:text-[#18181B]',
                        )}
                    >
                        <div className="flex items-center gap-2">
                            <HugeiconsIcon
                                icon={ShieldCheck}
                                size={16}
                                className={currentPanel === 'admin' ? 'text-[#5B619D]' : 'text-[#A1A1AA]'}
                            />
                            <span>{t('dashboard.layout.admin_panel', { defaultValue: 'Admin Panel' })}</span>
                        </div>
                        {currentPanel === 'admin' && <HugeiconsIcon icon={CheckmarkBadge01Icon} size={16} className="text-[#5B619D]" />}
                    </button>
                </div>
            </PopoverContent>
        </Popover>
    );
}
