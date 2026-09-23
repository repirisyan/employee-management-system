<script lang="ts">
    import { themeState } from '@/lib/theme.svelte';
    import { Toaster as SonnerPrimitive, type ToasterProps } from 'svelte-sonner';

    let { position = 'top-right', ...restProps }: ToasterProps = $props();

    const { resolvedAppearance } = themeState();
</script>

<SonnerPrimitive
    theme={resolvedAppearance()}
    class="toaster group"
    {position}
    richColors={true}
    closeButton={true}
    style="--normal-bg: var(--popover); --normal-text: var(--popover-foreground); --normal-border: var(--border);"
    toastOptions={{
        classes: {
            toast: 'group shadow-lg rounded-xl border text-sm font-sans font-medium',
            title: 'font-semibold tracking-tight text-sm',
            description: 'text-xs opacity-90 font-normal mt-0.5',
            closeButton: '!border !border-current/20 !bg-transparent hover:!bg-black/5 dark:hover:!bg-white/10 !text-inherit',
        },
    }}
    {...restProps}
/>

<style>
    /* Mode Terang (Light Mode) */
    :global([data-sonner-toaster][data-theme='light']),
    :global(html:not(.dark) [data-sonner-toaster]) {
        /* Sukses: Hijau */
        --success-bg: #ecfdf5;
        --success-border: #86efac;
        --success-text: #065f46;

        /* Info: Biru */
        --info-bg: #eff6ff;
        --info-border: #93c5fd;
        --info-text: #1e40af;

        /* Warning: Kuning / Amber */
        --warning-bg: #fffbeb;
        --warning-border: #fde68a;
        --warning-text: #92400e;

        /* Error: Merah */
        --error-bg: #fef2f2;
        --error-border: #fca5a5;
        --error-text: #991b1b;
    }

    /* Mode Gelap (Dark Mode) */
    :global([data-sonner-toaster][data-theme='dark']),
    :global(html.dark [data-sonner-toaster]) {
        /* Sukses: Hijau Gelap */
        --success-bg: #064e3b;
        --success-border: #059669;
        --success-text: #a7f3d0;

        /* Info: Biru Gelap */
        --info-bg: #1e3a8a;
        --info-border: #2563eb;
        --info-text: #bfdbfe;

        /* Warning: Kuning / Amber Gelap */
        --warning-bg: #78350f;
        --warning-border: #d97706;
        --warning-text: #fde68a;

        /* Error: Merah Gelap */
        --error-bg: #881337;
        --error-border: #e11d48;
        --error-text: #fecdd3;
    }
</style>

