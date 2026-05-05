import { router } from '@inertiajs/react';
import type { ReactElement, ReactNode } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { PageHeader } from '@/components/layout/PageHeader';
import { appShellLayout } from '@/layouts/AppShell';

interface SearchIndexProps {
    query: string;
    results: Array<{
        id: number;
        number: string;
        title: string | null;
        subject: string | null;
        requester: string | null;
        score: number;
        created: string | null;
    }>;
}

export default function SearchIndex({ query, results }: SearchIndexProps) {
    return (
        <>
            <PageHeader title="Search" subtitle="Find tickets, threads, and users across the workspace." eyebrow="Workspace" headerActions={null} />
            <div className="space-y-6">
                <form
                    role="search"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const value = new FormData(event.currentTarget).get('q')?.toString().trim() ?? '';
                        router.get('/scp/search', value === '' ? {} : { q: value });
                    }}
                    className="rounded-[18px] border border-[#E2E0D8] bg-white p-6 shadow-sm shadow-[#18181B]/[0.03] xl:p-8"
                >
                    <label htmlFor="search-query" className="text-xs font-medium uppercase tracking-[0.14em] text-[#A1A1AA]">
                        Query
                    </label>
                    <div className="mt-3 flex flex-col gap-3 sm:flex-row">
                        <Input
                            id="search-query"
                            name="q"
                            defaultValue={query}
                            placeholder="Search tickets, users, or threads"
                            autoFocus
                        />
                        <Button type="submit">Search</Button>
                    </div>
                </form>

                <section className="rounded-[18px] border border-[#E2E0D8] bg-white shadow-sm shadow-[#18181B]/[0.03]">
                    {results.length === 0 ? (
                        <div className="px-6 py-12 text-center text-sm text-[#71717A]">
                            {query ? `No tickets matched "${query}".` : 'Enter a query above to search across tickets and threads.'}
                        </div>
                    ) : (
                        <div className="divide-y divide-[#F4F2EB]">
                            {results.map((result) => (
                                <a key={result.id} href={`/scp/tickets/${result.id}`} className="block px-6 py-4 hover:bg-[#FAFAF8]">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <h2 className="font-display text-base font-medium text-[#18181B]">
                                            #{result.number} · {result.title ?? result.subject ?? 'Untitled ticket'}
                                        </h2>
                                        <span className="text-xs text-[#A1A1AA]">{result.created ?? '—'}</span>
                                    </div>
                                    {result.requester && <p className="mt-1 text-sm text-[#71717A]">{result.requester}</p>}
                                </a>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

type SearchIndexComponent = typeof SearchIndex & {
    layout?: (page: ReactElement) => ReactNode;
};

(SearchIndex as SearchIndexComponent).layout = appShellLayout;
