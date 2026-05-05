import { Skeleton } from '@/components/ui/skeleton';

import { getStatCardBorderClass } from './helpers';

export function DashboardSkeleton() {
    return (
        <div className="overflow-hidden rounded-[8px] border border-[#E2E0D8] bg-white shadow-sm shadow-[#18181B]/[0.03]">
            <div className="border-b border-[#E2E0D8]">
                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4">
                    {Array.from({ length: 4 }).map((_, i) => (
                        <div key={i} className={getStatCardBorderClass(i)}>
                            <div className="flex min-h-[132px] flex-col px-7 py-6">
                                <Skeleton className="h-3 w-24" />
                                <div className="mt-3 flex items-center gap-2">
                                    <Skeleton className="h-8 w-16" />
                                    <Skeleton className="h-5 w-12 rounded-[4px]" />
                                </div>
                                <Skeleton className="mt-auto h-3 w-32 pt-2" />
                            </div>
                        </div>
                    ))}
                </div>
            </div>
            <div className="grid grid-cols-1 border-b border-[#E2E0D8] xl:grid-cols-3">
                <div className="px-7 py-7 xl:col-span-2">
                    <Skeleton className="mb-2 h-4 w-40" />
                    <Skeleton className="mb-6 h-3 w-60" />
                    <Skeleton className="h-[310px] w-full rounded-lg" />
                </div>
                <div className="border-t border-[#E2E0D8] px-7 py-7 xl:border-l xl:border-t-0">
                    <Skeleton className="mb-2 h-4 w-32" />
                    <Skeleton className="mb-6 h-3 w-48" />
                    <Skeleton className="mx-auto aspect-square w-[220px] rounded-full" />
                </div>
            </div>
            <div className="grid grid-cols-1 xl:grid-cols-2">
                <div className="px-7 py-7">
                    <Skeleton className="mb-2 h-4 w-36" />
                    <Skeleton className="mb-4 h-3 w-52" />
                    <Skeleton className="h-[180px] w-full rounded-lg" />
                </div>
                <div className="border-t border-[#E2E0D8] px-7 py-7 xl:border-l xl:border-t-0">
                    <Skeleton className="mb-2 h-4 w-32" />
                    <Skeleton className="mb-4 h-3 w-48" />
                    {Array.from({ length: 5 }).map((_, i) => (
                        <div key={i} className="mb-3 grid grid-cols-[22px_1fr] gap-3">
                            <Skeleton className="mt-3 h-[22px] w-[22px] rounded-full" />
                            <Skeleton className="h-[72px] rounded-[6px]" />
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
