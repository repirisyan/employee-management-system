<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: '/dashboard',
            },
            {
                title: 'Pengaturan Jam Kerja',
                href: '/work-hours',
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import AlertCircle from '@lucide/svelte/icons/alert-circle';
    import CheckCircle2 from '@lucide/svelte/icons/check-circle-2';
    import Clock from '@lucide/svelte/icons/clock';
    import HelpCircle from '@lucide/svelte/icons/help-circle';
    import LogIn from '@lucide/svelte/icons/log-in';
    import LogOut from '@lucide/svelte/icons/log-out';
    import Sparkles from '@lucide/svelte/icons/sparkles';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    interface ScheduleProps {
        work_start_time: string;
        work_end_time: string;
        late_tolerance_minutes: number;
    }

    interface CompanyInfo {
        name: string;
        logo_url?: string | null;
    }

    let {
        schedule,
        company,
    }: {
        schedule: ScheduleProps;
        company: CompanyInfo;
    } = $props();

    let workStartTime = $state(schedule.work_start_time || '08:00');
    let workEndTime = $state(schedule.work_end_time || '17:00');
    let lateToleranceMinutes = $state(schedule.late_tolerance_minutes ?? 0);
    let isSubmitting = $state(false);
    let errors = $state<Record<string, string>>({});

    // Calculate total hours duration
    const durationHours = $derived.by(() => {
        if (!workStartTime || !workEndTime) return null;
        const [hStart, mStart] = workStartTime.split(':').map(Number);
        const [hEnd, mEnd] = workEndTime.split(':').map(Number);
        if (isNaN(hStart) || isNaN(mStart) || isNaN(hEnd) || isNaN(mEnd)) return null;

        const totalMinutes = hEnd * 60 + mEnd - (hStart * 60 + mStart);
        if (totalMinutes <= 0) return null;

        const hours = Math.floor(totalMinutes / 60);
        const minutes = totalMinutes % 60;
        return minutes > 0 ? `${hours} Jam ${minutes} Menit` : `${hours} Jam`;
    });

    // Calculate cutoff late time
    const lateThreshold = $derived.by(() => {
        if (!workStartTime) return '08:00';
        const [h, m] = workStartTime.split(':').map(Number);
        if (isNaN(h) || isNaN(m)) return '08:00';

        const total = h * 60 + m + Number(lateToleranceMinutes || 0);
        const hours = Math.floor(total / 60) % 24;
        const minutes = total % 60;
        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
    });

    function submitForm(e: SubmitEvent) {
        e.preventDefault();
        isSubmitting = true;
        errors = {};

        router.post(
            '/work-hours',
            {
                work_start_time: workStartTime,
                work_end_time: workEndTime,
                late_tolerance_minutes: Number(lateToleranceMinutes),
            },
            {
                onError: (err) => {
                    errors = err;
                },
                onFinish: () => {
                    isSubmitting = false;
                },
            }
        );
    }
</script>

<AppHead title="Pengaturan Jam Kerja" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8 max-w-6xl mx-auto w-full">
    <!-- Header -->
    <div class="flex flex-col gap-1">
        <div class="flex items-center gap-2">
            <div class="p-2 bg-primary/10 text-primary rounded-lg">
                <Clock class="size-6 text-primary" />
            </div>
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">
                    Pengaturan Jam Kerja
                </h1>
                <p class="text-sm text-muted-foreground">
                    Atur jadwal jam masuk, jam pulang, dan toleransi keterlambatan untuk kehadiran pegawai {company.name}.
                </p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        <!-- Form Pengaturan -->
        <div class="lg:col-span-7 space-y-6">
            <div class="rounded-xl border border-border/80 bg-card p-6 shadow-xs">
                <form onsubmit={submitForm} class="space-y-6">
                    <!-- Jam Masuk & Jam Pulang -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="work_start_time" class="text-sm font-semibold flex items-center gap-1.5">
                                <LogIn class="size-4 text-emerald-600 dark:text-emerald-400" />
                                Jam Masuk Kerja <span class="text-red-500">*</span>
                            </Label>
                            <div class="relative">
                                <Input
                                    id="work_start_time"
                                    type="time"
                                    bind:value={workStartTime}
                                    class={`text-base font-medium ${errors.work_start_time ? 'border-red-500' : ''}`}
                                    required
                                />
                            </div>
                            {#if errors.work_start_time}
                                <p class="text-xs text-red-500 font-medium">{errors.work_start_time}</p>
                            {:else}
                                <p class="text-xs text-muted-foreground">Waktu standar pegawai mulai masuk kerja.</p>
                            {/if}
                        </div>

                        <div class="space-y-2">
                            <Label for="work_end_time" class="text-sm font-semibold flex items-center gap-1.5">
                                <LogOut class="size-4 text-blue-600 dark:text-blue-400" />
                                Jam Pulang Kerja <span class="text-red-500">*</span>
                            </Label>
                            <div class="relative">
                                <Input
                                    id="work_end_time"
                                    type="time"
                                    bind:value={workEndTime}
                                    class={`text-base font-medium ${errors.work_end_time ? 'border-red-500' : ''}`}
                                    required
                                />
                            </div>
                            {#if errors.work_end_time}
                                <p class="text-xs text-red-500 font-medium">{errors.work_end_time}</p>
                            {:else}
                                <p class="text-xs text-muted-foreground">Waktu standar pegawai selesai jam kerja.</p>
                            {/if}
                        </div>
                    </div>

                    <!-- Toleransi Keterlambatan -->
                    <div class="space-y-2 pt-2 border-t border-border/60">
                        <Label for="late_tolerance_minutes" class="text-sm font-semibold flex items-center gap-1.5">
                            <Clock class="size-4 text-amber-600 dark:text-amber-400" />
                            Toleransi Keterlambatan (Menit)
                        </Label>
                        <div class="relative">
                            <Input
                                id="late_tolerance_minutes"
                                type="number"
                                min="0"
                                max="120"
                                bind:value={lateToleranceMinutes}
                                placeholder="0"
                                class={`text-base font-medium ${errors.late_tolerance_minutes ? 'border-red-500' : ''}`}
                            />
                        </div>
                        {#if errors.late_tolerance_minutes}
                            <p class="text-xs text-red-500 font-medium">{errors.late_tolerance_minutes}</p>
                        {:else}
                            <p class="text-xs text-muted-foreground">
                                Berikan kelonggaran menit setelah jam masuk sebelum status presensi otomatis ditandai sebagai <strong>Terlambat</strong>. Masukkan <strong>0</strong> jika tidak ada toleransi.
                            </p>
                        {/if}
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-4 flex items-center justify-end border-t border-border/60">
                        <Button
                            type="submit"
                            disabled={isSubmitting}
                            class="min-w-[150px] shadow-sm"
                        >
                            {#if isSubmitting}
                                <span class="flex items-center gap-2">
                                    <span class="size-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                                    Menyimpan...
                                </span>
                            {:else}
                                Simpan Pengaturan
                            {/if}
                        </Button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right Side: Preview & Attendance Rules Summary -->
        <div class="lg:col-span-5 space-y-6">
            <!-- Live Preview Card -->
            <div class="rounded-xl border border-border/80 bg-card p-6 shadow-xs space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-border/60">
                    <div class="flex items-center gap-2">
                        <Sparkles class="size-5 text-amber-500" />
                        <h2 class="text-sm font-semibold text-foreground">
                            Pratinjau Aturan Jam Kerja
                        </h2>
                    </div>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                        Aktif
                    </span>
                </div>

                <!-- Shift Overview -->
                <div class="rounded-lg bg-muted/50 p-4 border border-border/80 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-muted-foreground">Jadwal Harian</span>
                        {#if durationHours}
                            <span class="text-xs font-semibold text-primary px-2 py-0.5 rounded bg-primary/10">
                                {durationHours} / Hari
                            </span>
                        {/if}
                    </div>

                    <div class="flex items-center justify-between pt-1">
                        <div class="flex flex-col">
                            <span class="text-xs text-muted-foreground">Jam Masuk</span>
                            <span class="text-xl font-bold tracking-tight text-foreground">
                                {workStartTime || '--:--'} <span class="text-xs font-normal text-muted-foreground">WIB</span>
                            </span>
                        </div>
                        <div class="h-8 w-px bg-border"></div>
                        <div class="flex flex-col text-right">
                            <span class="text-xs text-muted-foreground">Jam Pulang</span>
                            <span class="text-xl font-bold tracking-tight text-foreground">
                                {workEndTime || '--:--'} <span class="text-xs font-normal text-muted-foreground">WIB</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Rules breakdown -->
                <div class="space-y-3 text-xs">
                    <div class="flex items-start gap-2.5 p-3 rounded-lg bg-emerald-50/70 text-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800/40">
                        <CheckCircle2 class="size-4 shrink-0 text-emerald-600 dark:text-emerald-400 mt-0.5" />
                        <div>
                            <span class="font-semibold">Presensi Tepat Waktu:</span>
                            <p class="text-emerald-800 dark:text-emerald-300/90 mt-0.5">
                                Pegawai yang check-in sebelum atau sama dengan pukul <strong>{lateThreshold} WIB</strong> otomatis berstatus <strong>Hadir Tepat Waktu</strong>.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-2.5 p-3 rounded-lg bg-amber-50/70 text-amber-900 dark:bg-amber-950/30 dark:text-amber-300 border border-amber-200/60 dark:border-amber-800/40">
                        <AlertCircle class="size-4 shrink-0 text-amber-600 dark:text-amber-400 mt-0.5" />
                        <div>
                            <span class="font-semibold">Batas Keterlambatan:</span>
                            <p class="text-amber-800 dark:text-amber-300/90 mt-0.5">
                                Pegawai yang check-in lewat pukul <strong>{lateThreshold} WIB</strong> otomatis berstatus <strong>Terlambat</strong>.
                                {#if Number(lateToleranceMinutes) > 0}
                                    <span class="block mt-0.5 text-muted-foreground">
                                        (Termasuk toleransi {lateToleranceMinutes} menit dari jam masuk {workStartTime}).
                                    </span>
                                {/if}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-2.5 p-3 rounded-lg bg-blue-50/70 text-blue-900 dark:bg-blue-950/30 dark:text-blue-300 border border-blue-200/60 dark:border-blue-800/40">
                        <LogOut class="size-4 shrink-0 text-blue-600 dark:text-blue-400 mt-0.5" />
                        <div>
                            <span class="font-semibold">Presensi Pulang:</span>
                            <p class="text-blue-800 dark:text-blue-300/90 mt-0.5">
                                Presensi pulang hanya dapat dilakukan mulai pukul <strong>{workEndTime || '17:00'} WIB</strong>. Pegawai tidak dapat check-out sebelum jam pulang.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

