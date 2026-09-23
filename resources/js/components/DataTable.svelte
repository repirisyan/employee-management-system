<script lang="ts">
    import type { Snippet } from 'svelte';
    import {
        createTable,
        FlexRender,
        tableFeatures,
        rowSortingFeature,
        createSortedRowModel,
        sortFns,
    } from '@tanstack/svelte-table';
    import { ArrowUpDown, ArrowUp, ArrowDown, SearchX } from '@lucide/svelte';

    interface Props {
        data: any[];
        columns: any[];
        emptyMessage?: string;
        class?: string;
        tableClass?: string;
        enableSorting?: boolean;
        manualSorting?: boolean;
        sortField?: string | null;
        sortDirection?: 'asc' | 'desc' | null;
        onSortChange?: (field: string, direction: 'asc' | 'desc' | null) => void;
        children?: Snippet;
    }

    let {
        data,
        columns,
        emptyMessage = 'Tidak ada data yang sesuai filter.',
        class: containerClass = '',
        tableClass = '',
        enableSorting = true,
        manualSorting = false,
        sortField = null,
        sortDirection = null,
        onSortChange,
        children,
    }: Props = $props();

    const features = tableFeatures({
        rowSortingFeature,
        sortedRowModel: createSortedRowModel(),
        sortFns,
    });

    const table = createTable({
        features,
        get columns() {
            return columns as any;
        },
        get data() {
            return data as any;
        },
        get enableSorting() {
            return enableSorting;
        },
        get manualSorting() {
            return manualSorting;
        },
    });

    function handleHeaderClick(header: any) {
        const canSort = header.column.getCanSort();
        if (!canSort) return;

        if (manualSorting && onSortChange) {
            const colId = header.column.id;
            if (sortField === colId) {
                if (sortDirection === 'asc') {
                    onSortChange(colId, 'desc');
                } else if (sortDirection === 'desc') {
                    onSortChange(colId, null);
                } else {
                    onSortChange(colId, 'asc');
                }
            } else {
                onSortChange(colId, 'asc');
            }
        } else {
            header.column.getToggleSortingHandler()?.();
        }
    }
</script>

<div class="rounded-xl border border-border/80 bg-card text-card-foreground shadow-xs overflow-hidden {containerClass}">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm {tableClass}">
            <thead class="border-b border-border/80 bg-muted/40 font-semibold text-xs text-muted-foreground">
                {#each table.getHeaderGroups() as headerGroup (headerGroup.id)}
                    <tr>
                        {#each headerGroup.headers as header (header.id)}
                            {@const canSort = header.column.getCanSort()}
                            {@const isColSorted = manualSorting
                                ? (sortField === header.column.id ? sortDirection : false)
                                : header.column.getIsSorted()}
                            {@const meta = header.column.columnDef.meta as Record<string, any> | undefined}
                            <th
                                class="px-4 py-3.5 font-semibold text-xs text-muted-foreground whitespace-nowrap group select-none transition-colors border-b border-border/80 {meta?.headerClass ?? ''} {canSort ? 'cursor-pointer hover:bg-muted/70 hover:text-foreground' : ''}"
                                onclick={() => handleHeaderClick(header)}
                                title={canSort ? 'Klik untuk mengurutkan data' : undefined}
                            >
                                <div class="flex items-center gap-1.5 {meta?.align === 'center' ? 'justify-center' : meta?.align === 'right' ? 'justify-end' : ''}">
                                    {#if !header.isPlaceholder}
                                        <FlexRender {header} />
                                    {/if}
                                    {#if canSort}
                                        <span class="inline-flex shrink-0">
                                            {#if isColSorted === 'asc'}
                                                <ArrowUp class="size-3.5 text-primary stroke-[2.5]" />
                                            {:else if isColSorted === 'desc'}
                                                <ArrowDown class="size-3.5 text-primary stroke-[2.5]" />
                                            {:else}
                                                <ArrowUpDown class="size-3 text-muted-foreground/30 group-hover:text-foreground transition-colors" />
                                            {/if}
                                        </span>
                                    {/if}
                                </div>
                            </th>
                        {/each}
                    </tr>
                {/each}
            </thead>
            <tbody class="divide-y divide-border/60 text-xs">
                {#if table.getRowModel().rows.length === 0}
                    <tr>
                        <td colspan={columns.length} class="py-12 px-4 text-center">
                            <div class="flex flex-col items-center justify-center gap-2 max-w-sm mx-auto text-muted-foreground">
                                <div class="flex size-10 items-center justify-center rounded-full bg-muted/60 text-muted-foreground/70">
                                    <SearchX class="size-5" />
                                </div>
                                <p class="font-medium text-sm text-foreground">Data Tidak Ditemukan</p>
                                <p class="text-xs text-muted-foreground">{emptyMessage}</p>
                            </div>
                        </td>
                    </tr>
                {:else}
                    {#each table.getRowModel().rows as row, idx (row.id)}
                        <tr class="hover:bg-muted/30 transition-colors {idx % 2 === 1 ? 'bg-muted/10' : ''}">
                            {#each row.getAllCells() as cell (cell.id)}
                                {@const meta = cell.column.columnDef.meta as Record<string, any> | undefined}
                                <td class="px-4 py-3 align-middle {meta?.class ?? ''}">
                                    <FlexRender {cell} />
                                </td>
                            {/each}
                        </tr>
                    {/each}
                {/if}
            </tbody>
        </table>
    </div>
    {#if children}
        {@render children()}
    {/if}
</div>
