<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import { ChevronDown } from '@lucide/svelte';
    import { cn } from '@/lib/utils';

    interface PaginationLink {
        url: string | null;
        label: string;
        active: boolean;
    }

    let {
        links = [],
        from = 0,
        to = 0,
        total = 0,
        perPage,
        perPageOptions = [10, 25, 50, 100],
        showPageSize = true,
        onPerPageChange,
    }: {
        links?: PaginationLink[];
        from?: number | null;
        to?: number | null;
        total?: number;
        perPage?: number;
        perPageOptions?: number[];
        showPageSize?: boolean;
        onPerPageChange?: (size: number) => void;
    } = $props();

    const currentPerPage = $derived(
        perPage ||
        (typeof window !== 'undefined'
            ? Number(new URLSearchParams(window.location.search).get('per_page')) || 10
            : 10)
    );

    const availableOptions = $derived(
        Array.from(new Set([...perPageOptions, currentPerPage])).sort((a, b) => a - b)
    );

    function formatLabel(label: string): string {
        if (label.includes('&laquo;') || label.toLowerCase().includes('previous')) {
            return '« Sebelumnya';
        }
        if (label.includes('&raquo;') || label.toLowerCase().includes('next')) {
            return 'Berikutnya »';
        }
        return label;
    }

    function handlePageSizeChange(e: Event) {
        const select = e.currentTarget as HTMLSelectElement;
        const newSize = Number(select.value);
        if (onPerPageChange) {
            onPerPageChange(newSize);
        } else {
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', String(newSize));
            url.searchParams.set('page', '1');
            router.get(
                url.pathname + url.search,
                {},
                { preserveState: true, replace: true, preserveScroll: true }
            );
        }
    }
</script>

{#if total > 0}
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 px-4 py-3 text-xs text-muted-foreground border-t border-border/80 bg-muted/20">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                Menampilkan <span class="font-medium text-foreground">{from ?? 0}</span> sampai <span class="font-medium text-foreground">{to ?? 0}</span> dari <span class="font-medium text-foreground">{total}</span> data
            </div>

            {#if showPageSize}
                <div class="flex items-center gap-1.5 pl-2 sm:border-l border-border">
                    <span>Tampilkan</span>
                    <div class="relative inline-flex items-center">
                        <select
                            value={currentPerPage}
                            onchange={handlePageSizeChange}
                            class="h-7 rounded-md border border-input bg-background pl-2 pr-6 text-xs font-medium text-foreground cursor-pointer shadow-2xs appearance-none focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            {#each availableOptions as opt}
                                <option value={opt}>{opt}</option>
                            {/each}
                        </select>
                        <ChevronDown class="absolute right-1.5 size-3.5 text-muted-foreground pointer-events-none" />
                    </div>
                    <span>baris</span>
                </div>
            {/if}
        </div>

        {#if links.length > 3}
            <div class="flex flex-wrap items-center gap-1">
                {#each links as link}
                    {#if link.url}
                        <Link
                            href={link.url}
                            preserveScroll
                            preserveState
                            class={cn(
                                'inline-flex items-center justify-center rounded-md text-xs font-medium h-7 px-2.5 transition-colors border',
                                link.active
                                    ? 'bg-primary text-primary-foreground border-primary pointer-events-none shadow-2xs'
                                    : 'bg-background hover:bg-muted text-foreground border-input'
                            )}
                        >
                            {formatLabel(link.label)}
                        </Link>
                    {:else}
                        <span
                            class="inline-flex items-center justify-center rounded-md text-xs font-medium h-7 px-2.5 text-muted-foreground border border-input/40 opacity-50 cursor-not-allowed"
                        >
                            {formatLabel(link.label)}
                        </span>
                    {/if}
                {/each}
            </div>
        {/if}
    </div>
{/if}

