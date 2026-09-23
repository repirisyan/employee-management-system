<script lang="ts">
    import Check from '@lucide/svelte/icons/check';
    import ChevronDown from '@lucide/svelte/icons/chevron-down';
    import Search from '@lucide/svelte/icons/search';
    import X from '@lucide/svelte/icons/x';

    type ItemId = number | string;

    interface OptionItem {
        id: ItemId;
        name: string;
        code?: string | null;
        subtitle?: string | null;
    }

    let {
        title = 'Opsi',
        placeholder = 'Semua',
        items = [],
        selected = $bindable<ItemId[]>([]),
        icon: IconComponent = null,
        iconColor = 'text-primary',
        disabled = false,
        onchange,
    }: {
        title?: string;
        placeholder?: string;
        items: OptionItem[];
        selected?: any[];
        icon?: any;
        iconColor?: string;
        disabled?: boolean;
        onchange?: (selected: any[]) => void;
    } = $props();

    let isOpen = $state(false);
    let searchTerm = $state('');
    let containerRef: HTMLDivElement | null = null;

    const filteredItems = $derived(
        searchTerm.trim()
            ? items.filter((item) =>
                  item.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                  (item.code && item.code.toLowerCase().includes(searchTerm.toLowerCase())) ||
                  (item.subtitle && item.subtitle.toLowerCase().includes(searchTerm.toLowerCase()))
              )
            : items
    );

    const labelText = $derived.by(() => {
        if (selected.length === 0) return placeholder;
        if (selected.length === 1) {
            const found = items.find((i) => i.id === selected[0]);
            return found ? found.name : `1 ${title} Terpilih`;
        }
        return `${selected.length} ${title} Terpilih`;
    });

    function toggleOpen(e: MouseEvent) {
        e.stopPropagation();
        if (disabled) return;
        isOpen = !isOpen;
        if (isOpen) {
            searchTerm = '';
        }
    }

    function toggleItem(id: ItemId, e: MouseEvent) {
        e.stopPropagation();
        if (disabled) return;
        let next: ItemId[];
        if (selected.includes(id)) {
            next = selected.filter((x) => x !== id);
        } else {
            next = [...selected, id];
        }
        selected = next;
        onchange?.(next);
    }

    function selectAll(e: MouseEvent) {
        e.stopPropagation();
        if (disabled) return;
        const allIds = items.map((i) => i.id);
        selected = allIds;
        onchange?.(allIds);
    }

    function clearAll(e?: MouseEvent) {
        e?.stopPropagation();
        if (disabled) return;
        selected = [];
        onchange?.([]);
    }

    function handleDocumentClick(e: MouseEvent) {
        if (isOpen && containerRef && !containerRef.contains(e.target as Node)) {
            isOpen = false;
        }
    }

    function handleKeyDown(e: KeyboardEvent) {
        if (e.key === 'Escape' && isOpen) {
            isOpen = false;
        }
    }
</script>

<svelte:window onclick={handleDocumentClick} onkeydown={handleKeyDown} />

<div class="relative w-full" bind:this={containerRef}>
    <!-- Trigger Button -->
    <button
        type="button"
        onclick={toggleOpen}
        {disabled}
        class="h-9 w-full rounded-md border border-input bg-background pl-9 pr-8 py-2 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring flex items-center justify-between cursor-pointer transition-colors text-left relative {disabled ? 'opacity-50 cursor-not-allowed' : 'hover:bg-muted/30'} {selected.length > 0 ? 'border-primary/50' : ''}"
        title="{title}: {labelText}"
    >
        <!-- Left Icon -->
        {#if IconComponent}
            <div class="absolute left-3 top-1/2 -translate-y-1/2 size-4 {iconColor} pointer-events-none flex items-center justify-center">
                <IconComponent class="size-4" />
            </div>
        {/if}

        <!-- Label / Selection preview -->
        <span class="truncate block pr-2 {selected.length === 0 ? 'text-muted-foreground' : 'text-foreground font-medium'}">
            {labelText}
        </span>

        <!-- Right Side: Count Badge + Chevron/Clear -->
        <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1">
            {#if selected.length > 0 && !disabled}
                <button
                    type="button"
                    onclick={clearAll}
                    class="size-4 rounded-full text-muted-foreground hover:text-foreground hover:bg-muted inline-flex items-center justify-center cursor-pointer transition-colors"
                    title="Hapus semua pilihan"
                >
                    <X class="size-3" />
                </button>
            {/if}
            <ChevronDown class="size-4 text-muted-foreground transition-transform {isOpen ? 'rotate-180' : ''}" />
        </div>
    </button>

    <!-- Dropdown Menu -->
    {#if isOpen}
        <div
            class="absolute left-0 top-full z-50 mt-1 w-full min-w-[240px] max-w-[340px] rounded-xl border border-border bg-popover p-2 text-popover-foreground shadow-lg animate-in fade-in-0 zoom-in-95"
            role="menu"
            tabindex="-1"
        >
            <!-- Dropdown Header: Title & Actions -->
            <div class="flex items-center justify-between pb-2 mb-1.5 border-b border-border/60 text-xs">
                <span class="font-semibold text-foreground flex items-center gap-1.5">
                    {#if IconComponent}
                        <IconComponent class="size-3.5 {iconColor}" />
                    {/if}
                    <span>Pilih {title}</span>
                </span>
                <div class="flex items-center gap-2">
                    {#if selected.length < items.length}
                        <button
                            type="button"
                            onclick={selectAll}
                            class="text-[11px] font-medium text-primary hover:underline cursor-pointer"
                        >
                            Pilih Semua
                        </button>
                    {/if}
                    {#if selected.length > 0}
                        <button
                            type="button"
                            onclick={clearAll}
                            class="text-[11px] font-medium text-muted-foreground hover:text-destructive cursor-pointer"
                        >
                            Reset
                        </button>
                    {/if}
                </div>
            </div>

            <!-- Optional Search Input inside dropdown -->
            {#if items.length > 4}
                <div class="relative mb-2">
                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground pointer-events-none" />
                    <input
                        type="text"
                        placeholder="Cari {title.toLowerCase()}..."
                        bind:value={searchTerm}
                        class="h-8 w-full rounded-md border border-input bg-background pl-8 pr-7 text-xs shadow-2xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    />
                    {#if searchTerm}
                        <button
                            type="button"
                            onclick={() => (searchTerm = '')}
                            class="absolute right-2 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground hover:text-foreground inline-flex items-center justify-center cursor-pointer"
                        >
                            <X class="size-3" />
                        </button>
                    {/if}
                </div>
            {/if}

            <!-- Items List -->
            <div class="max-h-56 overflow-y-auto space-y-0.5 scrollbar-thin">
                {#if filteredItems.length === 0}
                    <div class="py-4 text-center text-xs text-muted-foreground">
                        {searchTerm ? 'Tidak ada hasil yang cocok.' : `Tidak ada daftar ${title.toLowerCase()}.`}
                    </div>
                {:else}
                    {#each filteredItems as item (item.id)}
                        {@const isChecked = selected.includes(item.id)}
                        <button
                            type="button"
                            role="checkbox"
                            aria-checked={isChecked}
                            onclick={(e) => toggleItem(item.id, e)}
                            class="w-full flex items-center gap-2.5 px-2.5 py-1.5 rounded-lg text-xs transition-colors text-left cursor-pointer {isChecked ? 'bg-primary/10 text-primary font-medium' : 'hover:bg-muted text-foreground'}"
                        >
                            <!-- Checkbox icon -->
                            <div class="flex size-4 shrink-0 items-center justify-center rounded border {isChecked ? 'bg-primary border-primary text-primary-foreground' : 'border-input bg-background'} transition-colors">
                                {#if isChecked}
                                    <Check class="size-3 stroke-[3]" />
                                {/if}
                            </div>

                            <!-- Name & Code -->
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-xs {isChecked ? 'font-semibold text-primary' : 'text-foreground'}">
                                    {item.name}
                                </div>
                                {#if item.subtitle}
                                    <div class="text-[10px] text-muted-foreground truncate">
                                        {item.subtitle}
                                    </div>
                                {/if}
                            </div>

                            {#if item.code}
                                <span class="shrink-0 px-1.5 py-0.5 rounded text-[10px] font-mono bg-muted text-muted-foreground border">
                                    {item.code}
                                </span>
                            {/if}
                        </button>
                    {/each}
                {/if}
            </div>

            <!-- Footer: Summary of selection -->
            {#if items.length > 0}
                <div class="mt-1.5 pt-1.5 border-t border-border/60 flex items-center justify-between text-[11px] text-muted-foreground px-1">
                    <span>{selected.length} dari {items.length} dipilih</span>
                    <button
                        type="button"
                        onclick={() => (isOpen = false)}
                        class="font-semibold text-primary hover:underline cursor-pointer"
                    >
                        Selesai
                    </button>
                </div>
            {/if}
        </div>
    {/if}
</div>

