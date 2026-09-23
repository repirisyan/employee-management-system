<script lang="ts">
    import { onMount } from 'svelte';
    import Calendar from '@lucide/svelte/icons/calendar';
    import ChevronDown from '@lucide/svelte/icons/chevron-down';
    import ChevronLeft from '@lucide/svelte/icons/chevron-left';
    import ChevronRight from '@lucide/svelte/icons/chevron-right';
    import Check from '@lucide/svelte/icons/check';
    import RotateCcw from '@lucide/svelte/icons/rotate-ccw';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';

    interface Props {
        startDate: string;
        endDate: string;
        today?: string;
        onchange?: (range: { startDate: string; endDate: string }) => void;
    }

    let {
        startDate = $bindable(),
        endDate = $bindable(),
        today = new Date().toISOString().slice(0, 10),
        onchange,
    }: Props = $props();

    let isOpen = $state(false);
    let dropdownRef: HTMLDivElement | null = $state(null);

    // Internal selection state while modal/popover is open
    let tempStart = $state(startDate || today);
    let tempEnd = $state(endDate || today);
    let hoverDate = $state<string | null>(null);

    // Current calendar view month & year
    const initialDateObj = startDate ? new Date(startDate + 'T00:00:00') : new Date();
    let viewYear = $state(initialDateObj.getFullYear());
    let viewMonth = $state(initialDateObj.getMonth()); // 0-indexed

    const monthNames = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    const shortMonthNames = [
        'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des',
    ];

    const dayHeaders = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

    function padZero(n: number): string {
        return n < 10 ? '0' + n : String(n);
    }

    function formatDateToYMD(d: Date): string {
        const y = d.getFullYear();
        const m = padZero(d.getMonth() + 1);
        const day = padZero(d.getDate());
        return `${y}-${m}-${day}`;
    }

    function parseYMD(ymd: string): Date {
        const [y, m, d] = ymd.split('-').map(Number);
        return new Date(y, m - 1, d);
    }

    function formatShortDisplay(ymd: string): string {
        if (!ymd) return '';
        try {
            const [y, m, d] = ymd.split('-').map(Number);
            return `${d} ${shortMonthNames[m - 1]} ${y}`;
        } catch {
            return ymd;
        }
    }

    const displayLabel = $derived.by(() => {
        if (!startDate && !endDate) return 'Pilih Rentang Tanggal';
        if (startDate === endDate) return formatShortDisplay(startDate);
        if (startDate && endDate) {
            const [y1, m1, d1] = startDate.split('-').map(Number);
            const [y2, m2, d2] = endDate.split('-').map(Number);
            if (y1 === y2) {
                if (m1 === m2) {
                    return `${d1} - ${d2} ${shortMonthNames[m1 - 1]} ${y1}`;
                }
                return `${d1} ${shortMonthNames[m1 - 1]} - ${d2} ${shortMonthNames[m2 - 1]} ${y1}`;
            }
            return `${formatShortDisplay(startDate)} - ${formatShortDisplay(endDate)}`;
        }
        return formatShortDisplay(startDate || endDate);
    });

    // Compute calendar grid days for viewYear & viewMonth
    interface DayCell {
        dateStr: string;
        dayNum: number;
        isCurrentMonth: boolean;
        isToday: boolean;
    }

    const calendarDays = $derived.by(() => {
        const days: DayCell[] = [];
        const firstDayOfMonth = new Date(viewYear, viewMonth, 1);
        const lastDayOfMonth = new Date(viewYear, viewMonth + 1, 0);

        // Day of week: 0 = Sun, 1 = Mon, ..., 6 = Sat
        // We want Monday = 0, ..., Sunday = 6
        let startDay = firstDayOfMonth.getDay() - 1;
        if (startDay === -1) startDay = 6;

        // Days from previous month
        const prevMonthLastDay = new Date(viewYear, viewMonth, 0).getDate();
        for (let i = startDay - 1; i >= 0; i--) {
            const d = prevMonthLastDay - i;
            const prevDate = new Date(viewYear, viewMonth - 1, d);
            const dateStr = formatDateToYMD(prevDate);
            days.push({
                dateStr,
                dayNum: d,
                isCurrentMonth: false,
                isToday: dateStr === today,
            });
        }

        // Days of current month
        for (let d = 1; d <= lastDayOfMonth.getDate(); d++) {
            const curDate = new Date(viewYear, viewMonth, d);
            const dateStr = formatDateToYMD(curDate);
            days.push({
                dateStr,
                dayNum: d,
                isCurrentMonth: true,
                isToday: dateStr === today,
            });
        }

        // Fill trailing days to complete full weeks (multiples of 7)
        const remaining = 7 - (days.length % 7);
        if (remaining > 0 && remaining < 7) {
            for (let d = 1; d <= remaining; d++) {
                const nextDate = new Date(viewYear, viewMonth + 1, d);
                const dateStr = formatDateToYMD(nextDate);
                days.push({
                    dateStr,
                    dayNum: d,
                    isCurrentMonth: false,
                    isToday: dateStr === today,
                });
            }
        }

        return days;
    });

    function prevMonth() {
        if (viewMonth === 0) {
            viewMonth = 11;
            viewYear--;
        } else {
            viewMonth--;
        }
    }

    function nextMonth() {
        if (viewMonth === 11) {
            viewMonth = 0;
            viewYear++;
        } else {
            viewMonth++;
        }
    }

    function toggleOpen() {
        isOpen = !isOpen;
        if (isOpen) {
            tempStart = startDate || today;
            tempEnd = endDate || today;
            if (tempStart) {
                const d = parseYMD(tempStart);
                viewYear = d.getFullYear();
                viewMonth = d.getMonth();
            }
        }
    }

    function applyRange(s: string, e: string) {
        let finalStart = s;
        let finalEnd = e;
        if (finalStart && finalEnd && finalStart > finalEnd) {
            [finalStart, finalEnd] = [finalEnd, finalStart];
        }
        startDate = finalStart;
        endDate = finalEnd;
        isOpen = false;
        onchange?.({ startDate: finalStart, endDate: finalEnd });
    }

    function handleApply() {
        applyRange(tempStart, tempEnd || tempStart);
    }

    function handleCancel() {
        isOpen = false;
    }

    function onDayClick(dateStr: string) {
        // If we already have a complete range or no start date, start new range
        if ((tempStart && tempEnd) || (!tempStart && !tempEnd)) {
            tempStart = dateStr;
            tempEnd = '';
        } else if (tempStart && !tempEnd) {
            if (dateStr < tempStart) {
                tempEnd = tempStart;
                tempStart = dateStr;
            } else {
                tempEnd = dateStr;
            }
        }
    }

    // Quick presets
    interface Preset {
        id: string;
        label: string;
        getRange: () => { start: string; end: string };
    }

    const presets: Preset[] = [
        {
            id: 'today',
            label: 'Hari Ini',
            getRange: () => ({ start: today, end: today }),
        },
        {
            id: 'yesterday',
            label: 'Kemarin',
            getRange: () => {
                const d = parseYMD(today);
                d.setDate(d.getDate() - 1);
                const s = formatDateToYMD(d);
                return { start: s, end: s };
            },
        },
        {
            id: 'last7',
            label: '7 Hari Terakhir',
            getRange: () => {
                const d = parseYMD(today);
                d.setDate(d.getDate() - 6);
                return { start: formatDateToYMD(d), end: today };
            },
        },
        {
            id: 'last30',
            label: '30 Hari Terakhir',
            getRange: () => {
                const d = parseYMD(today);
                d.setDate(d.getDate() - 29);
                return { start: formatDateToYMD(d), end: today };
            },
        },
        {
            id: 'thisMonth',
            label: 'Bulan Ini',
            getRange: () => {
                const now = parseYMD(today);
                const start = new Date(now.getFullYear(), now.getMonth(), 1);
                const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                return { start: formatDateToYMD(start), end: formatDateToYMD(end) };
            },
        },
        {
            id: 'lastMonth',
            label: 'Bulan Lalu',
            getRange: () => {
                const now = parseYMD(today);
                const start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                const end = new Date(now.getFullYear(), now.getMonth(), 0);
                return { start: formatDateToYMD(start), end: formatDateToYMD(end) };
            },
        },
    ];

    function selectPreset(preset: Preset) {
        const { start, end } = preset.getRange();
        tempStart = start;
        tempEnd = end;
        const d = parseYMD(start);
        viewYear = d.getFullYear();
        viewMonth = d.getMonth();
        applyRange(start, end);
    }

    function isPresetActive(preset: Preset): boolean {
        const { start, end } = preset.getRange();
        return startDate === start && endDate === end;
    }

    // Determine day styling in calendar
    function getDayState(dateStr: string) {
        const effectiveEnd = tempEnd || (hoverDate && tempStart ? hoverDate : tempStart);
        const minD = tempStart && effectiveEnd ? (tempStart < effectiveEnd ? tempStart : effectiveEnd) : tempStart;
        const maxD = tempStart && effectiveEnd ? (tempStart > effectiveEnd ? tempStart : effectiveEnd) : tempStart;

        const isStart = dateStr === tempStart;
        const isEnd = dateStr === tempEnd;
        const isInRange = Boolean(minD && maxD && dateStr >= minD && dateStr <= maxD);
        const isSelected = isStart || isEnd;

        return { isStart, isEnd, isInRange, isSelected };
    }

    onMount(() => {
        function handleClickOutside(event: MouseEvent) {
            if (isOpen && dropdownRef && !dropdownRef.contains(event.target as Node)) {
                isOpen = false;
            }
        }
        document.addEventListener('click', handleClickOutside);
        return () => document.removeEventListener('click', handleClickOutside);
    });
</script>

<div class="relative w-full" bind:this={dropdownRef}>
    <!-- Trigger Button -->
    <button
        type="button"
        onclick={toggleOpen}
        class="w-full flex items-center justify-between gap-2 rounded-md border border-input bg-background pl-3 pr-2.5 py-2 text-sm shadow-xs hover:bg-accent/40 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors cursor-pointer text-left font-medium"
        aria-expanded={isOpen}
    >
        <div class="flex items-center gap-2 min-w-0">
            <Calendar class="size-4 shrink-0 text-blue-500" />
            <span class="truncate text-foreground text-xs sm:text-sm">
                {displayLabel}
            </span>
        </div>

        <ChevronDown class="size-4 shrink-0 text-muted-foreground transition-transform duration-200 {isOpen ? 'rotate-180' : ''}" />
    </button>

    <!-- Popover Panel -->
    {#if isOpen}
        <div
            class="absolute left-0 top-full mt-1.5 z-50 rounded-xl border bg-popover text-popover-foreground shadow-xl outline-none animate-in fade-in-0 zoom-in-95 p-3 sm:p-4 w-[320px] sm:w-[560px] max-w-[95vw] right-auto"
        >
            <div class="flex flex-col sm:flex-row gap-4">
                <!-- Sidebar: Presets -->
                <div class="flex flex-row sm:flex-col gap-1 overflow-x-auto sm:overflow-visible pb-2 sm:pb-0 sm:border-r border-border/60 sm:pr-3.5 sm:min-w-[140px] shrink-0">
                    <span class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider mb-1 hidden sm:block">
                        Rentang Cepat
                    </span>

                    {#each presets as preset}
                        {@const active = isPresetActive(preset)}
                        <button
                            type="button"
                            onclick={() => selectPreset(preset)}
                            class="shrink-0 text-left px-2.5 py-1.5 rounded-lg text-xs font-medium transition-colors flex items-center justify-between {active ? 'bg-primary text-primary-foreground font-semibold shadow-2xs' : 'text-foreground hover:bg-muted'}"
                        >
                            <span>{preset.label}</span>
                            {#if active}
                                <Check class="size-3.5 ml-1 hidden sm:inline" />
                            {/if}
                        </button>
                    {/each}
                </div>

                <!-- Main Content: Calendar + Custom Inputs -->
                <div class="flex-1 flex flex-col gap-3 min-w-0">
                    <!-- Calendar Header: Month Navigator -->
                    <div class="flex items-center justify-between pb-1 border-b border-border/60">
                        <button
                            type="button"
                            onclick={prevMonth}
                            class="p-1 rounded-md text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer"
                            title="Bulan sebelumnya"
                        >
                            <ChevronLeft class="size-4" />
                        </button>

                        <span class="text-xs sm:text-sm font-semibold text-foreground">
                            {monthNames[viewMonth]} {viewYear}
                        </span>

                        <button
                            type="button"
                            onclick={nextMonth}
                            class="p-1 rounded-md text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer"
                            title="Bulan berikutnya"
                        >
                            <ChevronRight class="size-4" />
                        </button>
                    </div>

                    <!-- Day of Week Headers -->
                    <div class="grid grid-cols-7 gap-1 text-center text-[11px] font-medium text-muted-foreground">
                        {#each dayHeaders as dh}
                            <span class="py-0.5">{dh}</span>
                        {/each}
                    </div>

                    <!-- Days Grid -->
                    <div
                        class="grid grid-cols-7 gap-0.5 text-center text-xs"
                        onmouseleave={() => (hoverDate = null)}
                        role="grid"
                    >
                        {#each calendarDays as day}
                            {@const { isStart, isEnd, isInRange, isSelected } = getDayState(day.dateStr)}
                            <button
                                type="button"
                                onclick={() => onDayClick(day.dateStr)}
                                onmouseenter={() => {
                                    if (tempStart && !tempEnd) {
                                        hoverDate = day.dateStr;
                                    }
                                }}
                                class="h-8 w-full flex items-center justify-center text-xs transition-colors cursor-pointer relative
                                    {isSelected ? 'bg-primary text-primary-foreground font-bold z-10' : ''}
                                    {isStart ? 'rounded-l-md' : ''}
                                    {isEnd || (isStart && !tempEnd) ? 'rounded-r-md' : ''}
                                    {isInRange && !isSelected ? 'bg-primary/10 text-primary dark:bg-primary/20 font-medium' : ''}
                                    {!isInRange && !isSelected && day.isCurrentMonth ? 'text-foreground hover:bg-muted rounded-md' : ''}
                                    {!isInRange && !isSelected && !day.isCurrentMonth ? 'text-muted-foreground/40 hover:bg-muted rounded-md' : ''}
                                "
                            >
                                <span>{day.dayNum}</span>
                                {#if day.isToday && !isSelected}
                                    <span class="absolute bottom-1 size-1 rounded-full bg-primary"></span>
                                {/if}
                            </button>
                        {/each}
                    </div>

                    <!-- Custom Inputs & Actions -->
                    <div class="pt-2 border-t border-border/60 flex flex-col gap-2">
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <label for="filter-start-date" class="text-[10px] font-medium text-muted-foreground block mb-1">
                                    Dari Tanggal
                                </label>
                                <Input
                                    id="filter-start-date"
                                    type="date"
                                    bind:value={tempStart}
                                    class="h-8 text-xs bg-background"
                                />
                            </div>
                            <div>
                                <label for="filter-end-date" class="text-[10px] font-medium text-muted-foreground block mb-1">
                                    Sampai Tanggal
                                </label>
                                <Input
                                    id="filter-end-date"
                                    type="date"
                                    bind:value={tempEnd}
                                    class="h-8 text-xs bg-background"
                                />
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center justify-between pt-1">
                            <span class="text-[11px] text-muted-foreground truncate">
                                {#if tempStart && tempEnd}
                                    {formatShortDisplay(tempStart)} - {formatShortDisplay(tempEnd)}
                                {:else if tempStart}
                                    Pilih tanggal akhir
                                {:else}
                                    Pilih tanggal
                                {/if}
                            </span>

                            <div class="flex items-center gap-1.5 shrink-0">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 text-xs px-2"
                                    onclick={handleCancel}
                                >
                                    Batal
                                </Button>
                                <Button
                                    type="button"
                                    variant="default"
                                    size="sm"
                                    class="h-7 text-xs px-2.5 font-semibold"
                                    onclick={handleApply}
                                    disabled={!tempStart}
                                >
                                    Terapkan
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    {/if}
</div>

