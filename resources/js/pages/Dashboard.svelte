<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: '/dashboard',
            },
        ],
    };
</script>

<script lang="ts">
    import { Link, page, router } from '@inertiajs/svelte';
    import AlertCircle from '@lucide/svelte/icons/alert-circle';
    import ArrowRight from '@lucide/svelte/icons/arrow-right';
    import Briefcase from '@lucide/svelte/icons/briefcase';
    import Building2 from '@lucide/svelte/icons/building-2';
    import CalendarCheck from '@lucide/svelte/icons/calendar-check';
    import CheckCircle2 from '@lucide/svelte/icons/check-circle-2';
    import Clock from '@lucide/svelte/icons/clock';
    import ExternalLink from '@lucide/svelte/icons/external-link';
    import GitFork from '@lucide/svelte/icons/git-fork';
    import LocateFixed from '@lucide/svelte/icons/locate-fixed';
    import LogIn from '@lucide/svelte/icons/log-in';
    import LogOut from '@lucide/svelte/icons/log-out';
    import Mail from '@lucide/svelte/icons/mail';
    import MapPin from '@lucide/svelte/icons/map-pin';
    import Navigation from '@lucide/svelte/icons/navigation';
    import Phone from '@lucide/svelte/icons/phone';
    import RefreshCw from '@lucide/svelte/icons/refresh-cw';
    import ShieldAlert from '@lucide/svelte/icons/shield-alert';
    import TrendingUp from '@lucide/svelte/icons/trending-up';
    import Users from '@lucide/svelte/icons/users';
    import XCircle from '@lucide/svelte/icons/x-circle';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogClose,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { notify } from '@/lib/flash-toast';
    import { getInitials } from '@/lib/initials';

    interface DepartmentRef {
        id: number;
        name: string;
        code: string | null;
    }

    interface EmployeeInfo {
        id: number;
        nip: string;
        name: string;
        email?: string | null;
        phone?: string | null;
        status?: string;
        avatar?: string | null;
        avatar_url?: string | null;
        department?: DepartmentRef;
        sub_department?: { id: number; name: string; code: string | null } | null;
        subDepartment?: { id: number; name: string; code: string | null } | null;
        role?: { id: number; name: string };
    }

    interface AttendanceRecord {
        id: number;
        employee_id: number;
        date: string;
        check_in: string | null;
        check_in_latitude?: number | null;
        check_in_longitude?: number | null;
        check_in_map_url?: string | null;
        check_out: string | null;
        check_out_latitude?: number | null;
        check_out_longitude?: number | null;
        check_out_map_url?: string | null;
        status: 'hadir' | 'terlambat' | 'izin' | 'sakit' | 'alpa' | 'dinas_pagi' | 'dinas_sore' | 'cuti' | string;
        notes: string | null;
        employee?: {
            id: number;
            nip: string;
            name: string;
            avatar?: string | null;
            avatar_url?: string | null;
            department?: DepartmentRef;
            role?: { id: number; name: string };
        };
    }

    interface DashboardSummary {
        total_employees: number;
        total_departments: number;
        total_sub_departments: number;
        hadir: number;
        terlambat: number;
        izin: number;
        sakit: number;
        alpa: number;
        dinas_pagi?: number;
        dinas_sore?: number;
        cuti?: number;
    }

    interface MonthStats {
        month_name: string;
        hadir: number;
        terlambat: number;
        total_present: number;
        izin: number;
        sakit: number;
        cuti: number;
        dinas: number;
        alpa: number;
        total_records: number;
    }

    let {
        role = 'super-admin',
        today = '',
        today_formatted = '',
        myEmployee,
        myAttendance,
        department,
        summary,
        monthStats,
        recentAttendances,
    }: {
        role?: string;
        today?: string;
        today_formatted?: string;
        myEmployee: EmployeeInfo | null;
        myAttendance: AttendanceRecord | null;
        department?: DepartmentRef | null;
        summary: DashboardSummary;
        monthStats?: MonthStats | null;
        recentAttendances: AttendanceRecord[];
    } = $props();

    interface CompanyShared {
        name?: string;
        work_start_time?: string;
        work_end_time?: string;
        late_tolerance_minutes?: number;
    }

    const companyData = $derived((page.props.company || {}) as CompanyShared);
    const workStartTime = $derived(companyData.work_start_time || '08:00');
    const workEndTime = $derived(companyData.work_end_time || '17:00');

    let currentTime = $state('00:00');

    $effect(() => {
        const updateNow = () => {
            const now = new Date();
            const h = String(now.getHours()).padStart(2, '0');
            const m = String(now.getMinutes()).padStart(2, '0');
            currentTime = `${h}:${m}`;
        };
        updateNow();
        const interval = setInterval(updateNow, 10000);
        return () => clearInterval(interval);
    });

    const isBeforeWorkEnd = $derived(currentTime < workEndTime);

    let isClockingIn = $state(false);
    let isClockingOut = $state(false);
    let isGpsModalOpen = $state(false);
    let gpsAction = $state<'check-in' | 'check-out'>('check-in');
    let gpsState = $state<'idle' | 'requesting' | 'success' | 'error'>('idle');
    let detectedCoords = $state<{ latitude: number; longitude: number; accuracy?: number } | null>(null);
    let gpsErrorMessage = $state('');

    function openGpsModal(action: 'check-in' | 'check-out') {
        if (action === 'check-out' && isBeforeWorkEnd) {
            notify.error(`Presensi pulang belum dibuka. Anda baru dapat melakukan presensi pulang mulai pukul ${workEndTime} WIB.`);
            return;
        }

        gpsAction = action;
        gpsState = 'idle';
        detectedCoords = null;
        gpsErrorMessage = '';
        isGpsModalOpen = true;
        startGpsRequest();
    }

    function startGpsRequest() {
        if (typeof window === 'undefined') return;

        gpsState = 'requesting';
        gpsErrorMessage = '';
        detectedCoords = null;

        if (!navigator.geolocation) {
            gpsState = 'error';
            if (typeof window !== 'undefined' && !window.isSecureContext) {
                gpsErrorMessage = 'Akses GPS diblokir oleh browser karena situs diakses melalui koneksi HTTP (bukan HTTPS atau localhost). Silakan amankan domain dengan menjalankan "herd secure" di terminal atau akses melalui http://localhost agar perizinan GPS dapat berfungsi.';
            } else {
                gpsErrorMessage = 'Perangkat atau browser Anda tidak mendukung fitur geolokasi (GPS). Presensi tidak dapat dilanjutkan.';
            }
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                detectedCoords = {
                    latitude: pos.coords.latitude,
                    longitude: pos.coords.longitude,
                    accuracy: Math.round(pos.coords.accuracy),
                };
                gpsState = 'success';
                setTimeout(() => {
                    if (detectedCoords) {
                        submitAttendanceWithGps(detectedCoords);
                    }
                }, 800);
            },
            (err) => {
                console.warn('Geolocation error:', err);
                gpsState = 'error';
                if (err.code === 1) {
                    gpsErrorMessage = 'Izin akses lokasi (GPS) ditolak browser. Mohon klik ikon gembok / pengaturan di sebelah kiri address bar browser Anda, ubah izin "Lokasi" menjadi "Izinkan" (Allow), lalu klik tombol "Coba Minta Akses Lagi" di bawah.';
                } else if (err.code === 2) {
                    gpsErrorMessage = 'Sinyal GPS tidak aktif atau lokasi perangkat tidak dapat ditentukan. Pastikan GPS / Layanan Lokasi di sistem operasi perangkat Anda sudah dihidupkan.';
                } else if (err.code === 3) {
                    gpsErrorMessage = 'Pencarian koordinat GPS melebihi batas waktu (timeout). Pastikan Anda berada di area dengan penerimaan GPS yang baik lalu coba lagi.';
                } else {
                    gpsErrorMessage = 'Gagal mendeteksi lokasi GPS. Pastikan GPS aktif pada perangkat Anda.';
                }
            },
            {
                enableHighAccuracy: true,
                timeout: 12000,
                maximumAge: 0,
            }
        );
    }

    function submitAttendanceWithGps(coords: { latitude: number; longitude: number }) {
        const endpoint = gpsAction === 'check-in' ? '/attendances/check-in' : '/attendances/check-out';

        if (gpsAction === 'check-in') {
            isClockingIn = true;
        } else {
            isClockingOut = true;
        }

        router.post(
            endpoint,
            { latitude: coords.latitude, longitude: coords.longitude },
            {
                preserveScroll: true,
                onSuccess: () => {
                    isGpsModalOpen = false;
                },
                onError: (errors) => {
                    gpsState = 'error';
                    gpsErrorMessage = Object.values(errors).join(', ');
                },
                onFinish: () => {
                    isClockingIn = false;
                    isClockingOut = false;
                },
            }
        );
    }

    const authUser = $derived(page.props.auth?.user as { employee?: { role?: { slug: string } } } | undefined);
    const effectiveRole = $derived(role || authUser?.employee?.role?.slug || 'super-admin');
    const isSuperAdmin = $derived(effectiveRole === 'super-admin');
    const isAdminBagian = $derived(effectiveRole === 'admin-bagian');
    const isPegawai = $derived(effectiveRole === 'pegawai');

    const attendanceRate = $derived(
        summary.total_employees > 0
            ? Math.round(((summary.hadir + summary.terlambat) / summary.total_employees) * 100)
            : 0
    );

    const totalEmps = $derived(summary.total_employees);
    const hadirCount = $derived(summary.hadir);
    const terlambatCount = $derived(summary.terlambat);
    const izinCount = $derived(summary.izin);
    const sakitCount = $derived(summary.sakit);
    const dinasCount = $derived((summary.dinas_pagi ?? 0) + (summary.dinas_sore ?? 0));
    const cutiCount = $derived(summary.cuti ?? 0);
    const alpaCount = $derived(summary.alpa);

    const hadirPct = $derived(totalEmps > 0 ? ((hadirCount / totalEmps) * 100) : 0);
    const terlambatPct = $derived(totalEmps > 0 ? ((terlambatCount / totalEmps) * 100) : 0);
    const izinSakitPct = $derived(totalEmps > 0 ? (((izinCount + sakitCount) / totalEmps) * 100) : 0);
    const dinasCutiPct = $derived(totalEmps > 0 ? (((dinasCount + cutiCount) / totalEmps) * 100) : 0);
    const alpaPct = $derived(totalEmps > 0 ? ((alpaCount / totalEmps) * 100) : 0);
    const unrecordedCount = $derived(
        Math.max(0, totalEmps - (hadirCount + terlambatCount + izinCount + sakitCount + dinasCount + cutiCount + alpaCount))
    );

    function getStatusBadge(status: string) {
        switch (status) {
            case 'hadir':
                return {
                    label: 'Hadir',
                    class: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
                };
            case 'izin':
                return {
                    label: 'Izin',
                    class: 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300 border-blue-200 dark:border-blue-800',
                };
            case 'terlambat':
                return {
                    label: 'Terlambat',
                    class: 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300 border-amber-200 dark:border-amber-800',
                };
            case 'sakit':
                return {
                    label: 'Sakit',
                    class: 'bg-purple-50 text-purple-700 dark:bg-purple-950 dark:text-purple-300 border-purple-200 dark:border-purple-800',
                };
            case 'alpa':
                return {
                    label: 'Alpa',
                    class: 'bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300 border-rose-200 dark:border-rose-800',
                };
            case 'dinas_pagi':
            case 'dinas pagi':
                return {
                    label: 'Dinas Pagi',
                    class: 'bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-300 border-sky-200 dark:border-sky-800',
                };
            case 'dinas_sore':
            case 'dinas sore':
                return {
                    label: 'Dinas Sore',
                    class: 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800',
                };
            case 'cuti':
                return {
                    label: 'Cuti',
                    class: 'bg-teal-50 text-teal-700 dark:bg-teal-950 dark:text-teal-300 border-teal-200 dark:border-teal-800',
                };
            default:
                return {
                    label: status,
                    class: 'bg-muted text-muted-foreground border-border',
                };
        }
    }
</script>

<AppHead title={isPegawai ? 'Portal Presensi Mandiri' : 'Dashboard Kehadiran Pegawai'} />

<div class="flex flex-col gap-6 p-4 md:p-6">
    {#if isPegawai}
        <!-- ======================================================== -->
        <!-- PEGAWAI DASHBOARD: PORTAL PRESENSI MANDIRI & STATISTIK PRIBADI -->
        <!-- ======================================================== -->

        <!-- Hero Card Presensi Mandiri Hari Ini -->
        <div class="rounded-2xl border bg-gradient-to-br from-emerald-500/10 via-card to-card p-6 shadow-sm">
            <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
                <div class="flex items-center gap-4">
                    {#if myEmployee?.avatar_url}
                        <img
                            src={myEmployee.avatar_url}
                            alt={myEmployee.name}
                            class="size-16 rounded-2xl object-cover border-2 border-emerald-500/30 shadow-xs hidden sm:block"
                        />
                    {/if}
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 text-xs font-semibold">
                                <CalendarCheck class="size-3.5" />
                                <span>Portal Presensi Mandiri Pegawai</span>
                                {#if today_formatted}
                                    <span class="opacity-40">•</span>
                                    <span class="font-normal">{today_formatted}</span>
                                {/if}
                            </div>
                            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-muted text-muted-foreground text-xs font-medium border border-border/80">
                                <Clock class="size-3.5 text-emerald-600 dark:text-emerald-400" />
                                <span>Jam Kerja: <strong class="text-foreground">{workStartTime} - {workEndTime} WIB</strong></span>
                            </div>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                            Halo, {myEmployee ? myEmployee.name : 'Pegawai'}!
                        </h1>
                        {#if myEmployee}
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                <span class="font-mono font-medium bg-muted px-2 py-0.5 rounded">NIP: {myEmployee.nip}</span>
                                <span>•</span>
                                <span class="flex items-center gap-1">
                                    <Building2 class="size-3 text-indigo-500" />
                                    <span>Bagian: <strong class="text-foreground">{myEmployee.department?.name ?? '-'}</strong></span>
                                </span>
                                {#if myEmployee.sub_department || myEmployee.subDepartment}
                                    <span>•</span>
                                    <span class="flex items-center gap-1">
                                        <GitFork class="size-3 text-purple-500" />
                                        <span>Sub: <strong class="text-foreground">{(myEmployee.sub_department ?? myEmployee.subDepartment)?.name}</strong></span>
                                    </span>
                                {/if}
                            </div>
                        {/if}
                    </div>
                </div>

                <!-- Clock-In / Clock-Out Action Box -->
                <div class="w-full lg:w-auto flex flex-col sm:flex-row items-stretch sm:items-center gap-4 bg-background/90 p-4 rounded-xl border shadow-sm">
                    <div class="flex flex-col pr-0 sm:pr-4 sm:border-r border-border min-w-[200px]">
                        <div class="flex items-center justify-between gap-3 text-xs text-muted-foreground">
                            <span>Status Presensi</span>
                            {#if today_formatted}
                                <span class="text-[11px] font-medium text-emerald-600 dark:text-emerald-400">{today_formatted}</span>
                            {/if}
                        </div>
                        <div class="flex items-center gap-2 mt-1">
                            {#if !myAttendance}
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                    <Clock class="size-3.5 text-amber-500" />
                                    Belum Presensi Hari Ini
                                </span>
                            {:else}
                                {@const myBadge = getStatusBadge(myAttendance.status)}
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold border {myBadge.class}">
                                    <CheckCircle2 class="size-3.5" />
                                    {myBadge.label.toUpperCase()}
                                </span>
                            {/if}
                        </div>

                        <!-- GPS Masuk & Pulang Info -->
                        {#if myAttendance?.check_in}
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 mt-2 text-xs text-muted-foreground">
                                <span>Masuk: <strong class="font-mono text-foreground font-semibold">{myAttendance.check_in.substring(0, 5)}</strong></span>
                                {#if myAttendance.check_in_latitude && myAttendance.check_in_longitude}
                                    <a
                                        href={myAttendance.check_in_map_url || `https://www.google.com/maps?q=${myAttendance.check_in_latitude},${myAttendance.check_in_longitude}`}
                                        target="_blank"
                                        rel="noreferrer"
                                        class="inline-flex items-center gap-0.5 text-emerald-600 dark:text-emerald-400 hover:underline font-medium"
                                        title={`GPS Masuk: ${myAttendance.check_in_latitude.toFixed(5)}, ${myAttendance.check_in_longitude.toFixed(5)}`}
                                    >
                                        <MapPin class="size-3" />
                                        <span>Lokasi GPS</span>
                                        <ExternalLink class="size-2.5 opacity-70" />
                                    </a>
                                {/if}
                            </div>
                        {/if}

                        {#if myAttendance?.check_out}
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 mt-1 text-xs text-muted-foreground">
                                <span>Pulang: <strong class="font-mono text-foreground font-semibold">{myAttendance.check_out.substring(0, 5)}</strong></span>
                                {#if myAttendance.check_out_latitude && myAttendance.check_out_longitude}
                                    <a
                                        href={myAttendance.check_out_map_url || `https://www.google.com/maps?q=${myAttendance.check_out_latitude},${myAttendance.check_out_longitude}`}
                                        target="_blank"
                                        rel="noreferrer"
                                        class="inline-flex items-center gap-0.5 text-blue-600 dark:text-blue-400 hover:underline font-medium"
                                        title={`GPS Pulang: ${myAttendance.check_out_latitude.toFixed(5)}, ${myAttendance.check_out_longitude.toFixed(5)}`}
                                    >
                                        <MapPin class="size-3" />
                                        <span>Lokasi GPS</span>
                                        <ExternalLink class="size-2.5 opacity-70" />
                                    </a>
                                {/if}
                            </div>
                        {/if}
                    </div>

                    <div class="flex flex-col gap-2 items-stretch sm:items-end justify-center">
                        <div class="flex items-center gap-2">
                            {#if !myAttendance || !myAttendance.check_in}
                                <Button
                                    onclick={() => openGpsModal('check-in')}
                                    disabled={isClockingIn}
                                    class="w-full sm:w-auto flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm px-5 py-2.5 text-sm font-semibold"
                                >
                                    <LogIn class="size-4" />
                                    <span>Check-In Masuk (GPS)</span>
                                </Button>
                            {:else if !myAttendance.check_out}
                                <Button
                                    onclick={() => openGpsModal('check-out')}
                                    disabled={isClockingOut || isBeforeWorkEnd}
                                    class="w-full sm:w-auto flex items-center justify-center gap-2 {isBeforeWorkEnd ? 'bg-muted text-muted-foreground hover:bg-muted cursor-not-allowed shadow-none border border-border' : 'bg-primary hover:bg-primary/90 text-primary-foreground shadow-sm'} px-5 py-2.5 text-sm font-semibold transition-all"
                                    title={isBeforeWorkEnd ? `Presensi pulang belum dibuka (dibuka pukul ${workEndTime} WIB)` : undefined}
                                >
                                    {#if isBeforeWorkEnd}
                                        <Clock class="size-4 text-amber-500" />
                                        <span>Belum Jam Pulang ({workEndTime})</span>
                                    {:else}
                                        <LogOut class="size-4" />
                                        <span>Check-Out Pulang (GPS)</span>
                                    {/if}
                                </Button>
                            {:else}
                                <div class="text-xs font-medium text-emerald-700 dark:text-emerald-300 px-3.5 py-2 bg-emerald-50 dark:bg-emerald-950/50 rounded-lg border border-emerald-200 dark:border-emerald-800 flex items-center gap-2">
                                    <CheckCircle2 class="size-4 text-emerald-600" />
                                    <span>Presensi Lengkap Hari Ini</span>
                                </div>
                            {/if}
                        </div>

                        {#if !myAttendance || !myAttendance.check_out}
                            <div class="text-[11px] text-muted-foreground flex flex-col sm:items-end gap-0.5">
                                <div class="flex items-center gap-1">
                                    <MapPin class="size-3 text-amber-500 shrink-0" />
                                    <span>GPS wajib aktif untuk mencatat titik presensi</span>
                                </div>
                                <span class="text-[10px]">
                                    {#if !myAttendance || !myAttendance.check_in}
                                        <span class="text-muted-foreground">
                                            Jam masuk standar: <strong class="text-foreground">{workStartTime} WIB</strong>
                                        </span>
                                    {:else if isBeforeWorkEnd}
                                        <span class="text-amber-600 dark:text-amber-400 font-medium flex items-center gap-1">
                                            <Clock class="size-2.5" />
                                            Presensi pulang baru dapat dilakukan mulai pukul <strong>{workEndTime} WIB</strong>
                                        </span>
                                    {:else}
                                        <span class="text-muted-foreground">
                                            Jam pulang standar: <strong class="text-foreground">{workEndTime} WIB</strong>
                                        </span>
                                    {/if}
                                </span>
                            </div>
                        {/if}
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistik Kehadiran Saya Bulan Ini (4 KPI cards) -->
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-foreground flex items-center gap-2">
                    <TrendingUp class="size-4 text-emerald-600" />
                    <span>Statistik Kehadiran Saya ({monthStats?.month_name ?? 'Bulan Ini'})</span>
                </h2>
                {#if monthStats && monthStats.total_records > 0}
                    {@const disciplineRate = Math.round((monthStats.hadir / monthStats.total_records) * 100)}
                    <span class="text-xs font-semibold text-emerald-600 bg-emerald-50 dark:bg-emerald-950/60 px-2.5 py-0.5 rounded-full border border-emerald-200 dark:border-emerald-800">
                        Kedisiplinan: {disciplineRate}% Tepat Waktu
                    </span>
                {/if}
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Total Hari Hadir -->
                <div class="rounded-xl border bg-card p-5 text-card-foreground shadow-sm flex flex-col justify-between border-l-4 border-l-emerald-500">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-muted-foreground">Total Masuk Kerja</span>
                        <div class="flex size-8 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600">
                            <CheckCircle2 class="size-4" />
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-2xl font-bold text-emerald-600">{monthStats?.total_present ?? 0} Hari</div>
                        <p class="text-[11px] text-muted-foreground mt-0.5">Hadir & Terlambat</p>
                    </div>
                </div>

                <!-- Hadir Tepat Waktu -->
                <div class="rounded-xl border bg-card p-5 text-card-foreground shadow-sm flex flex-col justify-between border-l-4 border-l-blue-500">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-muted-foreground">Tepat Waktu</span>
                        <div class="flex size-8 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600">
                            <CalendarCheck class="size-4" />
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-2xl font-bold text-blue-600">{monthStats?.hadir ?? 0} Hari</div>
                        <p class="text-[11px] text-muted-foreground mt-0.5">Sesuai jam kerja</p>
                    </div>
                </div>

                <!-- Terlambat -->
                <div class="rounded-xl border bg-card p-5 text-card-foreground shadow-sm flex flex-col justify-between border-l-4 border-l-amber-500">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-muted-foreground">Terlambat</span>
                        <div class="flex size-8 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600">
                            <Clock class="size-4" />
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-2xl font-bold text-amber-600">{monthStats?.terlambat ?? 0} Hari</div>
                        <p class="text-[11px] text-muted-foreground mt-0.5">Check-in lewat batas</p>
                    </div>
                </div>

                <!-- Izin, Sakit, Cuti, Dinas -->
                <div class="rounded-xl border bg-card p-5 text-card-foreground shadow-sm flex flex-col justify-between border-l-4 border-l-purple-500">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-muted-foreground">Izin, Sakit & Cuti</span>
                        <div class="flex size-8 items-center justify-center rounded-lg bg-purple-500/10 text-purple-600">
                            <AlertCircle class="size-4" />
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-2xl font-bold text-purple-600">
                            {(monthStats?.izin ?? 0) + (monthStats?.sakit ?? 0) + (monthStats?.cuti ?? 0) + (monthStats?.dinas ?? 0)} Hari
                        </div>
                        <p class="text-[11px] text-muted-foreground mt-0.5">
                            {monthStats?.izin ?? 0} Izin • {monthStats?.sakit ?? 0} Sakit • {monthStats?.cuti ?? 0} Cuti
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dua Kolom: Riwayat Presensi Saya Terkini & Info Profil Kepegawaian -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Riwayat Presensi Saya Terkini (2 cols) -->
            <div class="lg:col-span-2 rounded-xl border bg-card text-card-foreground shadow-sm overflow-hidden flex flex-col">
                <div class="flex items-center justify-between p-5 border-b">
                    <div>
                        <h2 class="font-semibold text-base">Riwayat Presensi Saya Terkini</h2>
                        <p class="text-xs text-muted-foreground mt-0.5">Catatan kehadiran hari kerja terakhir Anda</p>
                    </div>
                    <Link
                        href="/attendances"
                        class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline px-2.5 py-1 rounded-md bg-primary/5 hover:bg-primary/10 transition-colors"
                    >
                        Lihat Semua Riwayat
                        <ArrowRight class="size-3" />
                    </Link>
                </div>

                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-muted/40 font-semibold text-xs text-muted-foreground border-b">
                            <tr>
                                <th class="p-3.5">Tanggal</th>
                                <th class="p-3.5 text-center">Masuk</th>
                                <th class="p-3.5 text-center">Pulang</th>
                                <th class="p-3.5 text-center">Status</th>
                                <th class="p-3.5">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-xs">
                            {#if recentAttendances.length === 0}
                                <tr>
                                    <td colspan="5" class="p-8 text-center text-muted-foreground">
                                        Belum ada catatan riwayat kehadiran yang tersimpan.
                                    </td>
                                </tr>
                            {:else}
                                {#each recentAttendances as item (item.id)}
                                    {@const badge = getStatusBadge(item.status)}
                                    <tr class="hover:bg-muted/30 transition-colors">
                                        <td class="p-3.5 font-medium">
                                            <div class="font-semibold text-foreground">
                                                {new Date(item.date).toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })}
                                            </div>
                                        </td>
                                        <td class="p-3.5 text-center">
                                            <div class="inline-flex items-center justify-center gap-1 font-mono font-medium text-foreground">
                                                <span>{item.check_in ? item.check_in.substring(0, 5) : '-'}</span>
                                                {#if item.check_in_latitude && item.check_in_longitude}
                                                    <a
                                                        href={item.check_in_map_url || `https://www.google.com/maps?q=${item.check_in_latitude},${item.check_in_longitude}`}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        class="text-emerald-600 dark:text-emerald-400 hover:text-emerald-700"
                                                        title={`GPS Masuk: ${item.check_in_latitude.toFixed(5)}, ${item.check_in_longitude.toFixed(5)}`}
                                                    >
                                                        <MapPin class="size-3" />
                                                    </a>
                                                {/if}
                                            </div>
                                        </td>
                                        <td class="p-3.5 text-center">
                                            <div class="inline-flex items-center justify-center gap-1 font-mono font-medium text-foreground">
                                                <span>{item.check_out ? item.check_out.substring(0, 5) : '-'}</span>
                                                {#if item.check_out_latitude && item.check_out_longitude}
                                                    <a
                                                        href={item.check_out_map_url || `https://www.google.com/maps?q=${item.check_out_latitude},${item.check_out_longitude}`}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        class="text-blue-600 dark:text-blue-400 hover:text-blue-700"
                                                        title={`GPS Pulang: ${item.check_out_latitude.toFixed(5)}, ${item.check_out_longitude.toFixed(5)}`}
                                                    >
                                                        <MapPin class="size-3" />
                                                    </a>
                                                {/if}
                                            </div>
                                        </td>
                                        <td class="p-3.5 text-center">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full font-medium border text-xs {badge.class}">
                                                {badge.label}
                                            </span>
                                        </td>
                                        <td class="p-3.5 text-muted-foreground truncate max-w-[150px]">
                                            {item.notes ?? '-'}
                                        </td>
                                    </tr>
                                {/each}
                            {/if}
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Profil & Unit Kerja Saya (1 col) -->
            <div class="rounded-xl border bg-card p-5 text-card-foreground shadow-sm flex flex-col justify-between">
                <div class="space-y-4">
                    <div class="flex items-center gap-3 pb-3 border-b">
                        {#if myEmployee?.avatar_url}
                            <img
                                src={myEmployee.avatar_url}
                                alt={myEmployee.name}
                                class="size-11 rounded-xl object-cover border border-border shadow-xs"
                            />
                        {:else}
                            <div class="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary font-bold text-sm border border-primary/20 shadow-xs">
                                {getInitials(myEmployee?.name ?? 'P')}
                            </div>
                        {/if}
                        <div class="min-w-0 flex-1">
                            <h2 class="font-semibold text-base truncate">{myEmployee?.name ?? 'Profil Pegawai'}</h2>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                {myEmployee?.status === 'active' ? 'Aktif' : (myEmployee?.status ?? 'Aktif')}
                            </span>
                        </div>
                    </div>

                    <div class="space-y-3 text-xs">
                        <div>
                            <span class="text-muted-foreground block text-[11px]">Nama Lengkap</span>
                            <span class="font-semibold text-foreground text-sm">{myEmployee?.name ?? '-'}</span>
                        </div>

                        <div>
                            <span class="text-muted-foreground block text-[11px]">Nomor Induk Pegawai (NIP)</span>
                            <span class="font-mono font-medium text-foreground">{myEmployee?.nip ?? '-'}</span>
                        </div>

                        <div>
                            <span class="text-muted-foreground block text-[11px]">Unit Bagian</span>
                            <div class="flex items-center gap-1.5 font-medium text-foreground mt-0.5">
                                <Building2 class="size-3.5 text-indigo-500" />
                                <span>{myEmployee?.department?.name ?? '-'}</span>
                            </div>
                        </div>

                        {#if myEmployee?.sub_department || myEmployee?.subDepartment}
                            <div>
                                <span class="text-muted-foreground block text-[11px]">Sub Bagian</span>
                                <div class="flex items-center gap-1.5 font-medium text-foreground mt-0.5">
                                    <GitFork class="size-3.5 text-purple-500" />
                                    <span>{(myEmployee?.sub_department ?? myEmployee?.subDepartment)?.name}</span>
                                </div>
                            </div>
                        {/if}

                        <div>
                            <span class="text-muted-foreground block text-[11px]">Jabatan / Peran</span>
                            <div class="flex items-center gap-1.5 font-medium text-foreground mt-0.5">
                                <Briefcase class="size-3.5 text-muted-foreground" />
                                <span>{myEmployee?.role?.name ?? '-'}</span>
                            </div>
                        </div>

                        {#if myEmployee?.email}
                            <div>
                                <span class="text-muted-foreground block text-[11px]">Email</span>
                                <div class="flex items-center gap-1.5 text-muted-foreground mt-0.5">
                                    <Mail class="size-3.5" />
                                    <span>{myEmployee.email}</span>
                                </div>
                            </div>
                        {/if}

                        {#if myEmployee?.phone}
                            <div>
                                <span class="text-muted-foreground block text-[11px]">Nomor Telepon</span>
                                <div class="flex items-center gap-1.5 text-muted-foreground mt-0.5">
                                    <Phone class="size-3.5" />
                                    <span>{myEmployee.phone}</span>
                                </div>
                            </div>
                        {/if}
                    </div>
                </div>

                <div class="pt-4 mt-4 border-t">
                    <Link
                        href="/attendances"
                        class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-primary/10 hover:bg-primary/20 text-primary text-xs font-semibold transition-colors"
                    >
                        <CalendarCheck class="size-4" />
                        <span>Buka Riwayat Presensi Lengkap</span>
                    </Link>
                </div>
            </div>
        </div>

    {:else}
        <!-- ======================================================== -->
        <!-- SUPER ADMIN & ADMIN BAGIAN DASHBOARD: EKSEKUTIF / DEPARTEMEN -->
        <!-- ======================================================== -->

        <!-- Top Greeting Card -->
        <div class="rounded-2xl border bg-gradient-to-br from-primary/5 via-card to-card p-6 shadow-sm">
            <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
                <div class="space-y-1.5">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-primary/10 text-primary text-xs font-semibold">
                            <CalendarCheck class="size-3.5" />
                            <span>
                                {isAdminBagian
                                    ? `Panel Pengelolaan Bagian: ${department?.name ?? (myEmployee?.department?.name ?? 'Bagian')}`
                                    : 'Sistem Manajemen Kehadiran Pegawai'}
                            </span>
                            {#if today_formatted}
                                <span class="opacity-40">•</span>
                                <span class="font-normal">{today_formatted}</span>
                            {/if}
                        </div>
                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-muted text-muted-foreground text-xs font-medium border border-border/80">
                            <Clock class="size-3.5 text-primary" />
                            <span>Jam Kerja: <strong class="text-foreground">{workStartTime} - {workEndTime} WIB</strong></span>
                        </div>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                        {#if isAdminBagian}
                            Selamat Datang, Admin {department?.name ?? (myEmployee?.department?.name ?? '')}!
                        {:else}
                            Selamat Datang, {myEmployee ? myEmployee.name : 'Administrator Utama'}!
                        {/if}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {#if isAdminBagian}
                            Pengawasan rekapitulasi presensi harian dan pengelolaan pegawai khusus pada unit kerja <strong class="text-foreground">{department?.name ?? myEmployee?.department?.name}</strong>.
                        {:else}
                            Pantau rekapitulasi kehadiran seluruh instansi, master bagian, sub bagian, dan data aparatur terdaftar.
                        {/if}
                    </p>
                </div>

                <!-- Personal Clock-in Widget (if user has an employee record) -->
                {#if myEmployee}
                    <div class="w-full lg:w-auto flex flex-col sm:flex-row items-stretch sm:items-center gap-3 bg-background/80 p-4 rounded-xl border">
                        <div class="flex flex-col pr-4 sm:border-r border-border">
                            <div class="flex items-center justify-between gap-3 text-xs text-muted-foreground">
                                <span>Presensi Pribadi</span>
                                {#if today_formatted}
                                    <span class="text-[11px] font-medium text-primary">{today_formatted}</span>
                                {/if}
                            </div>
                            <div class="flex items-center gap-2 mt-1">
                                {#if !myAttendance}
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                        <Clock class="size-3" />
                                        Belum Presensi
                                    </span>
                                {:else}
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                        <CheckCircle2 class="size-3" />
                                        {myAttendance.status.toUpperCase()} ({myAttendance.check_in?.substring(0, 5) ?? '-'})
                                    </span>
                                {/if}
                            </div>

                            {#if myAttendance?.check_in}
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 mt-1 text-[11px] text-muted-foreground">
                                    <span>Masuk: <strong class="font-mono text-foreground">{myAttendance.check_in.substring(0, 5)}</strong></span>
                                    {#if myAttendance.check_in_latitude && myAttendance.check_in_longitude}
                                        <a
                                            href={myAttendance.check_in_map_url || `https://www.google.com/maps?q=${myAttendance.check_in_latitude},${myAttendance.check_in_longitude}`}
                                            target="_blank"
                                            rel="noreferrer"
                                            class="inline-flex items-center gap-0.5 text-emerald-600 dark:text-emerald-400 hover:underline font-medium"
                                        >
                                            <MapPin class="size-2.5" />
                                            <span>GPS</span>
                                        </a>
                                    {/if}
                                </div>
                            {/if}

                            {#if myAttendance?.check_out}
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 mt-0.5 text-[11px] text-muted-foreground">
                                    <span>Pulang: <strong class="font-mono text-foreground">{myAttendance.check_out.substring(0, 5)}</strong></span>
                                    {#if myAttendance.check_out_latitude && myAttendance.check_out_longitude}
                                        <a
                                            href={myAttendance.check_out_map_url || `https://www.google.com/maps?q=${myAttendance.check_out_latitude},${myAttendance.check_out_longitude}`}
                                            target="_blank"
                                            rel="noreferrer"
                                            class="inline-flex items-center gap-0.5 text-blue-600 dark:text-blue-400 hover:underline font-medium"
                                        >
                                            <MapPin class="size-2.5" />
                                            <span>GPS</span>
                                        </a>
                                    {/if}
                                </div>
                            {/if}
                        </div>

                        <div class="flex flex-col gap-1.5 items-stretch sm:items-end">
                            <div class="flex items-center gap-2">
                                {#if !myAttendance || !myAttendance.check_in}
                                    <Button
                                        onclick={() => openGpsModal('check-in')}
                                        disabled={isClockingIn}
                                        size="sm"
                                        class="flex-1 sm:flex-none flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm"
                                    >
                                        <LogIn class="size-3.5" />
                                        <span>Check-In (GPS)</span>
                                    </Button>
                                {:else if !myAttendance.check_out}
                                    <Button
                                        onclick={() => openGpsModal('check-out')}
                                        disabled={isClockingOut}
                                        size="sm"
                                        variant="secondary"
                                        class="flex-1 sm:flex-none flex items-center gap-2 border border-border"
                                    >
                                        <LogOut class="size-3.5" />
                                        <span>Check-Out (GPS)</span>
                                    </Button>
                                {:else}
                                    <div class="text-xs text-muted-foreground px-2.5 py-1.5 bg-muted rounded-md text-center flex items-center gap-1.5">
                                        <CheckCircle2 class="size-3 text-emerald-600" />
                                        <span>Presensi Selesai</span>
                                    </div>
                                {/if}
                            </div>
                        </div>
                    </div>
                {/if}
            </div>
        </div>

        <!-- 4 Stats Overview Cards (Tailored for Admin Bagian vs Super Admin) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-xl border bg-card p-5 text-card-foreground shadow-sm flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-muted-foreground">
                        {isAdminBagian ? `Pegawai (${department?.name ?? 'Bagian'})` : 'Total Pegawai Instansi'}
                    </span>
                    <div class="flex size-9 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                        <Users class="size-5" />
                    </div>
                </div>
                <div class="mt-4">
                    <div class="text-3xl font-bold">{summary.total_employees}</div>
                    <p class="text-xs text-muted-foreground mt-1">
                        {isAdminBagian ? `Pegawai aktif di bagian ${department?.name ?? ''}` : 'Seluruh aparatur aktif terdaftar'}
                    </p>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-5 text-card-foreground shadow-sm flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-muted-foreground">
                        {isAdminBagian ? 'Sub Bagian Unit' : 'Bagian & Sub Bagian'}
                    </span>
                    <div class="flex size-9 items-center justify-center rounded-lg bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                        <Building2 class="size-5" />
                    </div>
                </div>
                <div class="mt-4">
                    <div class="text-3xl font-bold">
                        {isAdminBagian ? summary.total_sub_departments : summary.total_departments}
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        {#if isAdminBagian}
                            {summary.total_sub_departments} Sub Bagian di bawah {department?.name ?? 'bagian ini'}
                        {:else}
                            {summary.total_sub_departments} Sub Bagian terhubung
                        {/if}
                    </p>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-5 text-card-foreground shadow-sm flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-muted-foreground">Hadir Hari Ini</span>
                    <div class="flex size-9 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400">
                        <CalendarCheck class="size-5" />
                    </div>
                </div>
                <div class="mt-4">
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-bold text-blue-600 dark:text-blue-400">
                            {summary.hadir + summary.terlambat}
                        </span>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                            {attendanceRate}%
                        </span>
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        {summary.hadir} Tepat Waktu, {summary.terlambat} Terlambat
                    </p>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-5 text-card-foreground shadow-sm flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-muted-foreground">Izin, Dinas & Lainnya</span>
                    <div class="flex size-9 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400">
                        <AlertCircle class="size-5" />
                    </div>
                </div>
                <div class="mt-4">
                    <div class="text-3xl font-bold text-amber-600 dark:text-amber-400">
                        {summary.izin + summary.sakit + summary.alpa + (summary.dinas_pagi ?? 0) + (summary.dinas_sore ?? 0) + (summary.cuti ?? 0)}
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        {summary.izin} Izin • {summary.sakit} Sakit • {(summary.dinas_pagi ?? 0) + (summary.dinas_sore ?? 0)} Dinas • {summary.cuti ?? 0} Cuti • {summary.alpa} Alpa
                    </p>
                </div>
            </div>
        </div>

        <!-- Live Attendance Fulfillment Visual Bar -->
        <div class="rounded-xl border bg-card p-5 text-card-foreground shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-semibold text-foreground flex items-center gap-2">
                        <span class="size-2 rounded-full bg-emerald-500"></span>
                        <span>
                            {isAdminBagian
                                ? `Distribusi Kehadiran Bagian ${department?.name ?? ''} Hari Ini`
                                : 'Distribusi Kehadiran Seluruh Instansi Hari Ini'}
                        </span>
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        {isAdminBagian
                            ? `Proporsi status presensi pegawai di bawah bagian ${department?.name ?? ''}`
                            : 'Proporsi status presensi seluruh pegawai terdaftar'}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-muted-foreground">Tingkat Kehadiran:</span>
                    <span class="text-sm font-bold text-primary">{attendanceRate}%</span>
                </div>
            </div>

            <!-- Multi-segment visual bar -->
            <div class="w-full h-3.5 bg-muted rounded-full overflow-hidden flex shadow-inner">
                {#if hadirPct > 0}
                    <div class="bg-emerald-500 h-full transition-all" style={`width: ${hadirPct}%;`} title={`Hadir: ${hadirCount} (${Math.round(hadirPct)}%)`}></div>
                {/if}
                {#if terlambatPct > 0}
                    <div class="bg-amber-500 h-full transition-all" style={`width: ${terlambatPct}%;`} title={`Terlambat: ${terlambatCount} (${Math.round(terlambatPct)}%)`}></div>
                {/if}
                {#if izinSakitPct > 0}
                    <div class="bg-blue-500 h-full transition-all" style={`width: ${izinSakitPct}%;`} title={`Izin & Sakit: ${izinCount + sakitCount} (${Math.round(izinSakitPct)}%)`}></div>
                {/if}
                {#if dinasCutiPct > 0}
                    <div class="bg-indigo-500 h-full transition-all" style={`width: ${dinasCutiPct}%;`} title={`Dinas & Cuti: ${dinasCount + cutiCount} (${Math.round(dinasCutiPct)}%)`}></div>
                {/if}
                {#if alpaPct > 0}
                    <div class="bg-rose-500 h-full transition-all" style={`width: ${alpaPct}%;`} title={`Alpa: ${alpaCount} (${Math.round(alpaPct)}%)`}></div>
                {/if}
            </div>

            <!-- Legend Chips -->
            <div class="flex flex-wrap items-center gap-3 pt-1 text-xs">
                <div class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-full bg-emerald-500"></span>
                    <span class="text-muted-foreground">Hadir ({hadirCount})</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-full bg-amber-500"></span>
                    <span class="text-muted-foreground">Terlambat ({terlambatCount})</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-full bg-blue-500"></span>
                    <span class="text-muted-foreground">Izin/Sakit ({izinCount + sakitCount})</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-full bg-indigo-500"></span>
                    <span class="text-muted-foreground">Dinas/Cuti ({dinasCount + cutiCount})</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-full bg-rose-500"></span>
                    <span class="text-muted-foreground">Alpa ({alpaCount})</span>
                </div>
                {#if unrecordedCount > 0}
                    <div class="flex items-center gap-1.5 ml-auto text-muted-foreground">
                        <span class="size-2.5 rounded-full bg-muted-foreground/30"></span>
                        <span>Belum Tercatat ({unrecordedCount})</span>
                    </div>
                {/if}
            </div>
        </div>

        <!-- Two Columns: Recent Attendances & Quick Navigation -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Recent Attendance Table (2 cols) -->
            <div class="lg:col-span-2 rounded-xl border bg-card text-card-foreground shadow-sm overflow-hidden flex flex-col">
                <div class="flex items-center justify-between p-5 border-b">
                    <div>
                        <h2 class="font-semibold text-base flex flex-wrap items-center gap-2">
                            <span>
                                {isAdminBagian
                                    ? `Presensi Terkini Bagian ${department?.name ?? ''}`
                                    : 'Presensi Terkini Hari Ini'}
                            </span>
                            {#if today_formatted}
                                <span class="text-xs font-normal text-muted-foreground bg-muted px-2.5 py-0.5 rounded-full border border-border">
                                    {today_formatted}
                                </span>
                            {/if}
                        </h2>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            {isAdminBagian
                                ? `Aktivitas presensi pegawai terbaru di unit ${department?.name ?? ''}`
                                : 'Catatan presensi masuk/pulang terakhir yang terekam'}
                        </p>
                    </div>
                    <Link
                        href="/attendances"
                        class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline px-2.5 py-1 rounded-md bg-primary/5 hover:bg-primary/10 transition-colors"
                    >
                        Lihat Semua
                        <ArrowRight class="size-3" />
                    </Link>
                </div>

                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-muted/40 font-semibold text-xs text-muted-foreground border-b">
                            <tr>
                                <th class="p-3.5">Pegawai</th>
                                {#if !isAdminBagian}
                                    <th class="p-3.5">Bagian</th>
                                {/if}
                                <th class="p-3.5 text-center">Masuk</th>
                                <th class="p-3.5 text-center">Pulang</th>
                                <th class="p-3.5 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-xs">
                            {#if recentAttendances.length === 0}
                                <tr>
                                    <td colspan={isAdminBagian ? 4 : 5} class="p-8 text-center text-muted-foreground">
                                        Belum ada aktivitas presensi hari ini.
                                    </td>
                                </tr>
                            {:else}
                                {#each recentAttendances as item (item.id)}
                                    {@const badge = getStatusBadge(item.status)}
                                    <tr class="hover:bg-muted/30 transition-colors">
                                        <td class="p-3.5">
                                            <div class="flex items-center gap-3">
                                                {#if item.employee?.avatar_url}
                                                    <img
                                                        src={item.employee.avatar_url}
                                                        alt={item.employee.name}
                                                        class="size-8 shrink-0 rounded-lg object-cover border border-border shadow-xs"
                                                    />
                                                {:else}
                                                    <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary font-bold text-xs border border-primary/20">
                                                        {getInitials(item.employee?.name ?? 'P')}
                                                    </div>
                                                {/if}
                                                <div class="min-w-0">
                                                    <div class="font-semibold text-foreground text-xs truncate max-w-[180px]">{item.employee?.name ?? '-'}</div>
                                                    <div class="font-mono text-muted-foreground text-[11px]">{item.employee?.nip ?? '-'}</div>
                                                </div>
                                            </div>
                                        </td>
                                        {#if !isAdminBagian}
                                            <td class="p-3.5">
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-muted text-[11px] font-medium text-foreground">
                                                    <Building2 class="size-3 text-muted-foreground" />
                                                    <span>{item.employee?.department?.name ?? '-'}</span>
                                                </span>
                                            </td>
                                        {/if}
                                        <td class="p-3.5 text-center">
                                            <div class="inline-flex items-center justify-center gap-1 font-mono font-medium text-foreground">
                                                <span>{item.check_in ? item.check_in.substring(0, 5) : '-'}</span>
                                                {#if item.check_in_latitude && item.check_in_longitude}
                                                    <a
                                                        href={item.check_in_map_url || `https://www.google.com/maps?q=${item.check_in_latitude},${item.check_in_longitude}`}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        class="text-emerald-600 dark:text-emerald-400 hover:text-emerald-700"
                                                        title={`GPS Masuk: ${item.check_in_latitude.toFixed(5)}, ${item.check_in_longitude.toFixed(5)}`}
                                                    >
                                                        <MapPin class="size-3" />
                                                    </a>
                                                {/if}
                                            </div>
                                        </td>
                                        <td class="p-3.5 text-center">
                                            <div class="inline-flex items-center justify-center gap-1 font-mono font-medium text-foreground">
                                                <span>{item.check_out ? item.check_out.substring(0, 5) : '-'}</span>
                                                {#if item.check_out_latitude && item.check_out_longitude}
                                                    <a
                                                        href={item.check_out_map_url || `https://www.google.com/maps?q=${item.check_out_latitude},${item.check_out_longitude}`}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        class="text-blue-600 dark:text-blue-400 hover:text-blue-700"
                                                        title={`GPS Pulang: ${item.check_out_latitude.toFixed(5)}, ${item.check_out_longitude.toFixed(5)}`}
                                                    >
                                                        <MapPin class="size-3" />
                                                    </a>
                                                {/if}
                                            </div>
                                        </td>
                                        <td class="p-3.5 text-center">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full font-medium border text-xs {badge.class}">
                                                {badge.label}
                                            </span>
                                        </td>
                                    </tr>
                                {/each}
                            {/if}
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Access Navigation Panel (1 col) -->
            <div class="rounded-xl border bg-card p-5 text-card-foreground shadow-sm flex flex-col justify-between">
                <div>
                    <h2 class="font-semibold text-base">Akses Cepat Modul</h2>
                    <p class="text-xs text-muted-foreground mt-0.5">Navigasi langsung ke modul utama sistem</p>

                    <div class="mt-4 space-y-2">
                        <Link
                            href="/employees"
                            class="flex items-center justify-between p-3 rounded-lg border hover:bg-muted/50 transition-colors group"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex size-8 items-center justify-center rounded-md bg-emerald-500/10 text-emerald-600">
                                    <Users class="size-4" />
                                </div>
                                <div>
                                    <div class="text-sm font-medium group-hover:text-primary transition-colors">
                                        {isAdminBagian ? 'Data Pegawai Bagian' : 'Data Seluruh Pegawai'}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {isAdminBagian ? `Kelola pegawai ${department?.name ?? 'bagian'}` : 'Kelola data seluruh aparatur & staff'}
                                    </div>
                                </div>
                            </div>
                            <ArrowRight class="size-4 text-muted-foreground group-hover:text-primary transition-colors" />
                        </Link>

                        <Link
                            href="/attendances"
                            class="flex items-center justify-between p-3 rounded-lg border hover:bg-muted/50 transition-colors group"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex size-8 items-center justify-center rounded-md bg-blue-500/10 text-blue-600">
                                    <CalendarCheck class="size-4" />
                                </div>
                                <div>
                                    <div class="text-sm font-medium group-hover:text-primary transition-colors">
                                        {isAdminBagian ? 'Rekap Kehadiran Bagian' : 'Rekap Kehadiran'}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {isAdminBagian ? `Laporan kehadiran ${department?.name ?? 'bagian'}` : 'Laporan presensi & status harian'}
                                    </div>
                                </div>
                            </div>
                            <ArrowRight class="size-4 text-muted-foreground group-hover:text-primary transition-colors" />
                        </Link>

                        {#if isSuperAdmin}
                            <Link
                                href="/departments"
                                class="flex items-center justify-between p-3 rounded-lg border hover:bg-muted/50 transition-colors group"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="flex size-8 items-center justify-center rounded-md bg-indigo-500/10 text-indigo-600">
                                        <Building2 class="size-4" />
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium group-hover:text-primary transition-colors">Master Bagian</div>
                                        <div class="text-xs text-muted-foreground">Kelola departemen & unit kerja</div>
                                    </div>
                                </div>
                                <ArrowRight class="size-4 text-muted-foreground group-hover:text-primary transition-colors" />
                            </Link>

                            <Link
                                href="/sub-departments"
                                class="flex items-center justify-between p-3 rounded-lg border hover:bg-muted/50 transition-colors group"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="flex size-8 items-center justify-center rounded-md bg-purple-500/10 text-purple-600">
                                        <GitFork class="size-4" />
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium group-hover:text-primary transition-colors">Master Sub Bagian</div>
                                        <div class="text-xs text-muted-foreground">Struktur turunan tiap bagian</div>
                                    </div>
                                </div>
                                <ArrowRight class="size-4 text-muted-foreground group-hover:text-primary transition-colors" />
                            </Link>
                        {/if}
                    </div>
                </div>
            </div>
        </div>
    {/if}
</div>

<!-- Modal Dialog Permintaan Akses GPS Presensi -->
<Dialog bind:open={isGpsModalOpen}>
    <DialogContent class="sm:max-w-md">
        <DialogTitle class="flex items-center gap-2 text-base font-bold">
            <div class="flex size-9 items-center justify-center rounded-xl {gpsAction === 'check-in' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-blue-500/10 text-blue-600 dark:text-blue-400'}">
                <MapPin class="size-5" />
            </div>
            <span>{gpsAction === 'check-in' ? 'Akses Lokasi (GPS) Check-In Masuk' : 'Akses Lokasi (GPS) Check-Out Pulang'}</span>
        </DialogTitle>
        <DialogDescription class="text-xs">
            Presensi kehadiran mewajibkan titik koordinat GPS aktif untuk validasi posisi lokasi Anda secara sah.
        </DialogDescription>

        <div class="space-y-4 py-2">
            {#if gpsState === 'requesting'}
                <!-- State: Meminta Izin / Mencari Sinyal GPS -->
                <div class="rounded-xl border bg-muted/30 p-6 flex flex-col items-center justify-center text-center space-y-3">
                    <div class="relative flex size-16 items-center justify-center">
                        <span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-30"></span>
                        <div class="relative flex size-12 items-center justify-center rounded-full bg-emerald-500 text-white shadow-md">
                            <Navigation class="size-6 animate-pulse" />
                        </div>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-foreground">Meminta Akses Lokasi (GPS)...</h3>
                        <p class="text-xs text-muted-foreground mt-1 max-w-xs">
                            Jika muncul notifikasi perizinan lokasi di browser Anda, silakan klik <strong>"Izinkan" (Allow)</strong> untuk melanjutkan.
                        </p>
                    </div>
                    <div class="text-[11px] text-muted-foreground/80 flex items-center gap-1.5 pt-1">
                        <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>Mendeteksi koordinat presisi tinggi...</span>
                    </div>
                </div>

            {:else if gpsState === 'success' && detectedCoords}
                <!-- State: Berhasil Deteksi GPS -->
                <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-5 flex flex-col items-center justify-center text-center space-y-3">
                    <div class="flex size-12 items-center justify-center rounded-full bg-emerald-500 text-white shadow-md">
                        <CheckCircle2 class="size-6" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-emerald-700 dark:text-emerald-300">Lokasi GPS Terverifikasi!</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Menyimpan data presensi {gpsAction === 'check-in' ? 'masuk' : 'pulang'}...
                        </p>
                    </div>
                    <div class="w-full rounded-lg bg-background border p-3 text-xs font-mono space-y-1 text-left">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Latitude:</span>
                            <span class="font-semibold text-foreground">{detectedCoords.latitude.toFixed(6)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Longitude:</span>
                            <span class="font-semibold text-foreground">{detectedCoords.longitude.toFixed(6)}</span>
                        </div>
                        {#if detectedCoords.accuracy}
                            <div class="flex justify-between text-[11px] text-muted-foreground pt-1 border-t">
                                <span>Akurasi:</span>
                                <span>±{detectedCoords.accuracy} meter</span>
                            </div>
                        {/if}
                    </div>
                </div>

            {:else if gpsState === 'error'}
                <!-- State: Gagal / Izin Ditolak -->
                <div class="rounded-xl border border-destructive/30 bg-destructive/5 p-5 space-y-3">
                    <div class="flex items-start gap-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-destructive/10 text-destructive mt-0.5">
                            <ShieldAlert class="size-5" />
                        </div>
                        <div class="space-y-1">
                            <h3 class="text-sm font-semibold text-destructive">Akses GPS Belum Diizinkan</h3>
                            <p class="text-xs text-muted-foreground leading-relaxed">
                                {gpsErrorMessage || 'Sistem tidak dapat membaca lokasi Anda. Pastikan izin lokasi diberikan.'}
                            </p>
                        </div>
                    </div>

                    <div class="rounded-lg bg-muted/40 p-3 text-[11px] text-muted-foreground space-y-1.5 border">
                        <div class="font-semibold text-foreground flex items-center gap-1">
                            <AlertCircle class="size-3.5 text-amber-500" />
                            <span>Cara mengaktifkan akses lokasi:</span>
                        </div>
                        <ul class="list-disc pl-4 space-y-0.5">
                            <li>Klik ikon gembok / pengaturan di sebelah kiri URL browser (address bar).</li>
                            <li>Ubah setelan <strong>Lokasi (Location)</strong> menjadi <strong>Izinkan (Allow)</strong>.</li>
                            <li>Pastikan GPS pada sistem laptop/ponsel Anda juga dalam posisi aktif.</li>
                        </ul>
                    </div>
                </div>
            {/if}
        </div>

        <DialogFooter class="flex flex-col-reverse sm:flex-row justify-end gap-2 mt-2">
            <DialogClose>
                <Button type="button" variant="outline" size="sm">Batal</Button>
            </DialogClose>
            {#if gpsState === 'error'}
                <Button
                    type="button"
                    size="sm"
                    onclick={startGpsRequest}
                    class="gap-1.5 bg-primary text-primary-foreground"
                >
                    <RefreshCw class="size-3.5" />
                    <span>Coba Minta Akses Lagi</span>
                </Button>
            {:else if gpsState === 'success' && detectedCoords}
                <Button
                    type="button"
                    size="sm"
                    disabled={isClockingIn || isClockingOut}
                    onclick={() => detectedCoords && submitAttendanceWithGps(detectedCoords)}
                    class="gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white"
                >
                    <CheckCircle2 class="size-3.5" />
                    <span>{isClockingIn || isClockingOut ? 'Menyimpan...' : 'Kirim Presensi Sekarang'}</span>
                </Button>
            {/if}
        </DialogFooter>
    </DialogContent>
</Dialog>
