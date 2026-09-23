<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';

    interface CompanyProps {
        name?: string;
        logo?: string;
        logo_url?: string;
    }

    const company = $derived(page.props.company as CompanyProps | undefined);
    const companyName = $derived(company?.name || (page.props.name as string) || 'SIMPEG Presensi');
</script>

{#if company?.logo_url}
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-lg overflow-hidden border border-border/60 bg-background shadow-xs shrink-0 p-0.5"
    >
        <img src={company.logo_url} alt={companyName} class="size-full object-contain" />
    </div>
{:else}
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-xs shrink-0"
    >
        <AppLogoIcon class="size-5 fill-current text-primary-foreground" />
    </div>
{/if}
<div class="ml-2 grid flex-1 text-left min-w-0">
    <span class="truncate leading-tight font-bold text-sm tracking-tight">{companyName}</span>
    <span class="truncate text-[10px] text-muted-foreground font-medium uppercase tracking-wider">Kehadiran Pegawai</span>
</div>
