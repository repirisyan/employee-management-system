<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import Calendar from '@lucide/svelte/icons/calendar';
    import Moon from '@lucide/svelte/icons/moon';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import Sun from '@lucide/svelte/icons/sun';
    import Breadcrumbs from '@/components/Breadcrumbs.svelte';
    import { SidebarTrigger } from '@/components/ui/sidebar';
    import { themeState } from '@/lib/theme.svelte';
    import type { BreadcrumbItem } from '@/types';

    let {
        breadcrumbs = [],
    }: {
        breadcrumbs?: BreadcrumbItem[];
    } = $props();

    interface UserData {
        employee?: {
            role?: { name: string; slug: string };
            department?: { name: string };
        };
    }

    const user = $derived(page.props.auth?.user as UserData | undefined);
    const roleName = $derived(user?.employee?.role?.name ?? 'Super Admin');
    const deptName = $derived(user?.employee?.department?.name ?? null);

    const theme = themeState();
    const isDark = $derived(theme.resolvedAppearance() === 'dark');

    function toggleTheme() {
        theme.updateAppearance(isDark ? 'light' : 'dark');
    }

    const currentDate = new Intl.DateTimeFormat('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date());
</script>

<header
    class="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/70 px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-6 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 sticky top-0 z-10"
>
    <div class="flex items-center gap-2">
        <SidebarTrigger class="-ml-1" />
        {#if breadcrumbs && breadcrumbs.length > 0}
            <Breadcrumbs {breadcrumbs} />
        {/if}
    </div>

    <!-- Right Topbar Controls -->
    <div class="flex items-center gap-2.5">
        <!-- Date display -->
        <div class="hidden md:flex items-center gap-1.5 text-xs text-muted-foreground px-2.5 py-1 rounded-md bg-muted/50 border">
            <Calendar class="size-3.5 text-primary/70" />
            <span class="font-medium">{currentDate}</span>
        </div>

        <!-- System live indicator -->
        <div class="hidden sm:flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 text-[11px] font-medium">
            <span class="relative flex size-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full size-2 bg-emerald-500"></span>
            </span>
            <span>Online</span>
        </div>

        <!-- Role Badge -->
        <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-primary/10 text-primary border border-primary/20 text-xs font-semibold">
            <ShieldCheck class="size-3.5" />
            <span>{roleName}</span>
            {#if deptName}
                <span class="text-muted-foreground font-normal hidden lg:inline">• {deptName}</span>
            {/if}
        </div>

        <!-- Light / Dark Mode Toggle Button -->
        <button
            type="button"
            onclick={toggleTheme}
            class="flex size-9 items-center justify-center rounded-lg border border-border/80 bg-background hover:bg-muted text-foreground transition-all duration-200 cursor-pointer shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            title={isDark ? 'Beralih ke Mode Terang (Light Mode)' : 'Beralih ke Mode Gelap (Dark Mode)'}
            aria-label="Ganti Tema"
        >
            {#if isDark}
                <Sun class="size-4 text-amber-500 transition-transform duration-200 rotate-0 hover:rotate-45" />
            {:else}
                <Moon class="size-4 text-indigo-500 transition-transform duration-200 -rotate-12 hover:rotate-0" />
            {/if}
            <span class="sr-only">Ganti Tema</span>
        </button>
    </div>
</header>
