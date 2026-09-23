<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: '/dashboard',
            },
            {
                title: 'Profil Perusahaan',
                href: '/company-profile',
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Building from '@lucide/svelte/icons/building';
    import Camera from '@lucide/svelte/icons/camera';
    import CheckCircle2 from '@lucide/svelte/icons/check-circle-2';
    import Eye from '@lucide/svelte/icons/eye';
    import Sparkles from '@lucide/svelte/icons/sparkles';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import Upload from '@lucide/svelte/icons/upload';
    import AppHead from '@/components/AppHead.svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    interface Company {
        id: number;
        name: string;
        logo: string | null;
        logo_url: string | null;
    }

    let {
        company,
    }: {
        company: Company;
    } = $props();

    let formName = $state(company.name || '');
    let logoFile = $state<File | null>(null);
    let logoPreview = $state<string | null>(company.logo_url || null);
    let removeLogo = $state(false);
    let isSubmitting = $state(false);
    let errors = $state<Record<string, string>>({});

    function handleLogoChange(e: Event) {
        const target = e.target as HTMLInputElement;
        const file = target.files?.[0];
        if (!file) return;

        logoFile = file;
        removeLogo = false;

        const reader = new FileReader();
        reader.onload = (event) => {
            logoPreview = event.target?.result as string;
        };
        reader.readAsDataURL(file);
    }

    function handleRemoveLogo() {
        logoFile = null;
        logoPreview = null;
        removeLogo = true;
    }

    function submitForm(e: SubmitEvent) {
        e.preventDefault();
        isSubmitting = true;
        errors = {};

        const formData = new FormData();
        formData.append('name', formName);

        if (logoFile) {
            formData.append('logo', logoFile);
        }

        if (removeLogo) {
            formData.append('remove_logo', '1');
        }

        router.post('/company-profile', formData, {
            forceFormData: true,
            onError: (err) => {
                errors = err;
            },
            onFinish: () => {
                isSubmitting = false;
            },
        });
    }
</script>

<AppHead title="Profil Perusahaan" />

<div class="flex flex-col gap-6 p-4 md:p-6 max-w-5xl">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <div class="flex size-9 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                    <Building class="size-5" />
                </div>
                <h1 class="text-2xl font-bold tracking-tight">Profil Perusahaan</h1>
            </div>
            <p class="text-sm text-muted-foreground mt-1">
                Kelola identitas perusahaan, logo resmi, dan nama instansi yang tampil di seluruh sistem presensi.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Form (2 cols) -->
        <div class="lg:col-span-2">
            <form onsubmit={submitForm} class="rounded-xl border bg-card p-6 shadow-xs space-y-6">
                <!-- Nama Perusahaan -->
                <div class="space-y-2">
                    <Label for="company-name" class="text-sm font-semibold">
                        Nama Perusahaan / Instansi <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="company-name"
                        placeholder="Contoh: PT Pratama Nusantara / Dinas Komunikasi & Informatika"
                        bind:value={formName}
                        required
                        maxlength={100}
                    />
                    <p class="text-xs text-muted-foreground">
                        Nama ini akan ditampilkan pada header portal, sidebar aplikasi, dan halaman login.
                    </p>
                    {#if errors.name}
                        <p class="text-xs text-destructive">{errors.name}</p>
                    {/if}
                </div>

                <!-- Logo Perusahaan -->
                <div class="space-y-3">
                    <Label class="text-sm font-semibold">
                        Logo Resmi Perusahaan
                    </Label>

                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5 p-4 rounded-xl border bg-muted/20">
                        <!-- Preview Box -->
                        <div class="relative size-24 shrink-0 rounded-2xl overflow-hidden border-2 border-border shadow-xs bg-muted flex items-center justify-center p-2">
                            {#if logoPreview}
                                <img src={logoPreview} alt="Preview Logo" class="size-full object-contain" />
                            {:else}
                                <div class="size-full flex flex-col items-center justify-center text-muted-foreground bg-primary/5">
                                    <AppLogoIcon class="size-10 fill-current text-primary" />
                                </div>
                            {/if}
                        </div>

                        <!-- Upload Actions & Details -->
                        <div class="flex-1 space-y-2">
                            <div class="text-sm font-medium">Unggah Berkas Logo</div>
                            <p class="text-xs text-muted-foreground">
                                Format gambar PNG, JPG, JPEG, WEBP, atau SVG (maksimal 2MB). Disarankan berlatar belakang transparan (rasio 1:1 persegi).
                            </p>

                            <div class="flex flex-wrap items-center gap-2 pt-1">
                                <label class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border bg-background hover:bg-muted text-xs font-medium text-foreground transition-colors shadow-xs">
                                    <Upload class="size-3.5" />
                                    <span>{logoPreview ? 'Ganti Logo' : 'Pilih Logo'}</span>
                                    <input
                                        type="file"
                                        accept="image/png,image/jpeg,image/jpg,image/webp,image/svg+xml"
                                        class="hidden"
                                        onchange={handleLogoChange}
                                    />
                                </label>

                                {#if logoPreview}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="h-8 text-xs text-destructive hover:bg-destructive/10 border-destructive/20"
                                        onclick={handleRemoveLogo}
                                    >
                                        <Trash2 class="size-3.5 mr-1" />
                                        Gunakan Default
                                    </Button>
                                {/if}
                            </div>
                            {#if errors.logo}
                                <p class="text-xs text-destructive mt-1">{errors.logo}</p>
                            {/if}
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="pt-4 border-t flex justify-end">
                    <Button type="submit" disabled={isSubmitting} class="gap-2 min-w-36">
                        {#if isSubmitting}
                            <span>Menyimpan...</span>
                        {:else}
                            <CheckCircle2 class="size-4" />
                            <span>Simpan Perubahan</span>
                        {/if}
                    </Button>
                </div>
            </form>
        </div>

        <!-- Live Preview Panel (1 col) -->
        <div class="space-y-4">
            <div class="rounded-xl border bg-card p-5 shadow-xs space-y-4">
                <div class="flex items-center gap-2 pb-3 border-b">
                    <Eye class="size-4 text-primary" />
                    <h2 class="text-sm font-semibold">Pratinjau Langsung (Live Preview)</h2>
                </div>

                <!-- Preview di Sidebar -->
                <div class="space-y-2">
                    <span class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Tampilan Sidebar</span>
                    <div class="p-3 rounded-lg border bg-sidebar text-sidebar-foreground shadow-xs flex items-center gap-3">
                        {#if logoPreview}
                            <div class="flex aspect-square size-9 items-center justify-center rounded-lg overflow-hidden border border-border/60 bg-background shadow-xs shrink-0 p-1">
                                <img src={logoPreview} alt="Logo" class="size-full object-contain" />
                            </div>
                        {:else}
                            <div class="flex aspect-square size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-xs shrink-0">
                                <AppLogoIcon class="size-5 fill-current text-primary-foreground" />
                            </div>
                        {/if}
                        <div class="min-w-0 flex-1">
                            <div class="font-bold text-sm truncate leading-tight">{formName || 'Nama Perusahaan'}</div>
                            <div class="text-[10px] text-muted-foreground font-medium uppercase tracking-wider">Kehadiran Pegawai</div>
                        </div>
                    </div>
                </div>

                <!-- Preview di Halaman Login -->
                <div class="space-y-2 pt-2">
                    <span class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Tampilan Halaman Login</span>
                    <div class="p-4 rounded-lg border bg-slate-950 text-white shadow-xs flex items-center gap-3">
                        {#if logoPreview}
                            <div class="flex size-11 items-center justify-center rounded-xl bg-white/10 p-1 backdrop-blur-sm shadow-md ring-1 ring-white/20 shrink-0">
                                <img src={logoPreview} alt="Logo" class="size-full object-contain" />
                            </div>
                        {:else}
                            <div class="flex size-11 items-center justify-center rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-500 shadow-lg shadow-blue-500/25 ring-1 ring-white/20 shrink-0">
                                <AppLogoIcon class="size-6 fill-current text-white" />
                            </div>
                        {/if}
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-bold truncate leading-tight text-white">{formName || 'Nama Perusahaan'}</div>
                            <div class="text-[10px] text-slate-400 font-medium truncate">Sistem Kepegawaian & Presensi Terpadu</div>
                        </div>
                    </div>
                </div>

                <div class="text-xs text-muted-foreground bg-muted/40 p-3 rounded-lg flex items-start gap-2">
                    <Sparkles class="size-4 text-emerald-500 shrink-0 mt-0.5" />
                    <span>Perubahan nama dan logo akan langsung terlihat di seluruh antarmuka setelah disimpan.</span>
                </div>
            </div>
        </div>
    </div>
</div>

