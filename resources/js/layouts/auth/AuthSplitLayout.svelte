<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import Building2 from '@lucide/svelte/icons/building-2';
    import CalendarCheck from '@lucide/svelte/icons/calendar-check';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import Sparkles from '@lucide/svelte/icons/sparkles';
    import type { Snippet } from 'svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import { Toaster } from '@/components/ui/sonner';

    let {
        title = '',
        description = '',
        children,
    }: {
        title?: string;
        description?: string;
        children?: Snippet;
    } = $props();

    interface CompanyProps {
        name?: string;
        logo?: string;
        logo_url?: string;
    }

    const company = $derived(page.props.company as CompanyProps | undefined);
    const name = $derived(company?.name || (page.props.name as string) || 'SIMPEG Presensi');
</script>

<div class="relative min-h-screen grid flex-col items-center justify-center lg:max-w-none lg:grid-cols-2 lg:px-0 bg-background">
    <!-- Left Hero Side (Visible on desktop) -->
    <div class="relative hidden h-full flex-col justify-between overflow-hidden bg-slate-950 p-12 text-white lg:flex border-r border-slate-800 selection:bg-primary selection:text-white">
        <!-- Ambient Decorative Background Gradients & Grid -->
        <div class="absolute inset-0 bg-[radial-gradient(#38bdf812_1px,transparent_1px)] [background-size:28px_28px] opacity-70"></div>
        <div class="absolute -left-32 -top-32 size-96 rounded-full bg-blue-600/20 blur-3xl pointer-events-none"></div>
        <div class="absolute right-0 top-1/2 size-96 -translate-y-1/2 rounded-full bg-indigo-600/15 blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-32 left-1/4 size-96 rounded-full bg-emerald-600/15 blur-3xl pointer-events-none"></div>

        <!-- Top Brand Header -->
        <div class="relative z-10 flex items-center justify-between">
            <div class="flex items-center gap-3">
                {#if company?.logo_url}
                    <div class="flex size-11 items-center justify-center rounded-xl bg-white/10 p-1 backdrop-blur-sm shadow-lg ring-1 ring-white/20">
                        <img src={company.logo_url} alt={name} class="size-full object-contain" />
                    </div>
                {:else}
                    <div class="flex size-11 items-center justify-center rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-500 shadow-lg shadow-blue-500/25 ring-1 ring-white/20">
                        <AppLogoIcon class="size-6 fill-current text-white" />
                    </div>
                {/if}
                <div>
                    <div class="text-base font-bold tracking-tight text-white">{name}</div>
                    <div class="text-xs text-slate-400 font-medium">Sistem Kepegawaian & Presensi Terpadu</div>
                </div>
            </div>

            <div class="inline-flex items-center gap-1.5 rounded-full border border-slate-700/80 bg-slate-900/80 px-3 py-1 text-xs text-slate-300 backdrop-blur-sm shadow-inner">
                <span class="size-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>Server Aktif</span>
            </div>
        </div>

        <!-- Center Value Proposition -->
        <div class="relative z-10 my-auto py-10 max-w-lg space-y-8">
            <div class="space-y-4">
                <div class="inline-flex items-center gap-2 rounded-lg bg-blue-500/10 border border-blue-500/20 px-3 py-1 text-xs font-semibold text-blue-300">
                    <Sparkles class="size-3.5" />
                    <span>Portal Resmi Kehadiran Aparatur</span>
                </div>
                <h1 class="text-3xl font-extrabold tracking-tight text-white sm:text-4xl leading-tight">
                    Disiplin, Akurat, dan Transparan dalam Satu Ekosistem.
                </h1>
                <p class="text-sm leading-relaxed text-slate-300 font-normal">
                    Kelola data presensi pegawai harian, pembagian departemen berjenjang, dan monitoring kehadiran real-time dengan efisiensi tinggi.
                </p>
            </div>

            <!-- Feature Pills / Highlights -->
            <div class="grid gap-3">
                <div class="flex items-start gap-3.5 rounded-xl border border-white/10 bg-white/[0.03] p-3.5 backdrop-blur-sm transition-colors hover:bg-white/[0.06]">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-500/20 text-blue-400">
                        <CalendarCheck class="size-4.5" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-white">Presensi Digital Presisi</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Check-in dan check-out mandiri dengan validasi jam masuk, pulang, dan status tepat waktu otomatis.</p>
                    </div>
                </div>

                <div class="flex items-start gap-3.5 rounded-xl border border-white/10 bg-white/[0.03] p-3.5 backdrop-blur-sm transition-colors hover:bg-white/[0.06]">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-indigo-500/20 text-indigo-400">
                        <Building2 class="size-4.5" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-white">Master Organisasi Dinamis</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Struktur bertingkat antara Bagian & Sub Bagian yang otomatis tersinkronisasi pada data pegawai.</p>
                    </div>
                </div>

                <div class="flex items-start gap-3.5 rounded-xl border border-white/10 bg-white/[0.03] p-3.5 backdrop-blur-sm transition-colors hover:bg-white/[0.06]">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-500/20 text-emerald-400">
                        <ShieldCheck class="size-4.5" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-white">Role-Based Access Control</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Pengelompokan hak wewenang terstruktur: Super Admin, Admin Bagian, dan Staf Pegawai.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Footer Info -->
        <div class="relative z-10 flex items-center justify-between text-xs text-slate-500 border-t border-slate-800/80 pt-6">
            <span>&copy; 2026 {name}. Seluruh Hak Cipta Dilindungi.</span>
            <span class="flex items-center gap-1 text-slate-400">
                <ShieldCheck class="size-3.5 text-emerald-400" />
                <span>Enkripsi 256-bit SSL</span>
            </span>
        </div>
    </div>

    <!-- Right Form Side -->
    <div class="flex min-h-screen flex-col items-center justify-center p-6 sm:p-10 lg:p-12">
        <div class="w-full max-w-[420px] space-y-7">
            <!-- Mobile Brand Header (< lg) -->
            <div class="flex flex-col items-center text-center lg:hidden space-y-2 mb-2">
                {#if company?.logo_url}
                    <div class="flex size-14 items-center justify-center rounded-xl bg-background border p-1.5 shadow-sm">
                        <img src={company.logo_url} alt={name} class="size-full object-contain" />
                    </div>
                {:else}
                    <div class="flex size-12 items-center justify-center rounded-xl bg-primary shadow-lg shadow-primary/30">
                        <AppLogoIcon class="size-7 fill-current text-white dark:text-black" />
                    </div>
                {/if}
                <div>
                    <h2 class="text-lg font-bold tracking-tight text-foreground">{name}</h2>
                    <p class="text-xs text-muted-foreground">Sistem Manajemen Kehadiran Pegawai</p>
                </div>
            </div>

            <!-- Page Title & Description -->
            <div class="space-y-1.5 text-left">
                {#if title}
                    <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">{title}</h1>
                {/if}
                {#if description}
                    <p class="text-sm text-muted-foreground leading-relaxed">{description}</p>
                {/if}
            </div>

            <!-- Form Content -->
            <div class="rounded-2xl border border-border/80 bg-card p-6 sm:p-7 shadow-sm text-card-foreground">
                {@render children?.()}
            </div>

            <!-- Bottom Disclaimer -->
            <p class="text-center text-xs text-muted-foreground">
                Sistem ini hanya diperuntukkan bagi pegawai terdaftar.
                <br />
                Hubungi administrator jika Anda mengalami kendala login.
            </p>
        </div>
    </div>

    <Toaster />
</div>
