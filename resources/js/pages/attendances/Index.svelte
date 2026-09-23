<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: '/dashboard',
            },
            {
                title: 'Kehadiran Pegawai',
                href: '/attendances',
            },
        ],
    };
</script>

<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import AlertCircle from '@lucide/svelte/icons/alert-circle';
    import Building2 from '@lucide/svelte/icons/building-2';
    import Calendar from '@lucide/svelte/icons/calendar';
    import CalendarCheck from '@lucide/svelte/icons/calendar-check';
    import CheckCircle2 from '@lucide/svelte/icons/check-circle-2';
    import ChevronDown from '@lucide/svelte/icons/chevron-down';
    import ChevronLeft from '@lucide/svelte/icons/chevron-left';
    import ChevronRight from '@lucide/svelte/icons/chevron-right';
    import Clock from '@lucide/svelte/icons/clock';
    import ExternalLink from '@lucide/svelte/icons/external-link';
    import GitFork from '@lucide/svelte/icons/git-fork';
    import MapPin from '@lucide/svelte/icons/map-pin';
    import Pencil from '@lucide/svelte/icons/pencil';
    import Plus from '@lucide/svelte/icons/plus';
    import RotateCcw from '@lucide/svelte/icons/rotate-ccw';
    import Rows3 from '@lucide/svelte/icons/rows-3';
    import Search from '@lucide/svelte/icons/search';
    import SlidersHorizontal from '@lucide/svelte/icons/sliders-horizontal';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import Users from '@lucide/svelte/icons/users';
    import X from '@lucide/svelte/icons/x';
    import XCircle from '@lucide/svelte/icons/x-circle';
    import AppHead from '@/components/AppHead.svelte';
    import DataTable from '@/components/DataTable.svelte';
    import DateRangeFilter from '@/components/DateRangeFilter.svelte';
    import MultiSelectFilter from '@/components/MultiSelectFilter.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import { Button } from '@/components/ui/button';
    import { getInitials } from '@/lib/initials';
    import { renderSnippet, type ColumnDef } from '@tanstack/svelte-table';
    import {
        Dialog,
        DialogClose,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    interface DepartmentRef {
        id: number;
        name: string;
        code: string | null;
    }

    interface SubDepartmentRef {
        id: number;
        department_id: number;
        name: string;
        code: string | null;
    }

    interface EmployeeSummary {
        id: number;
        nip: string;
        name: string;
        department_id: number;
    }

    interface AttendanceItem {
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
        status: 'hadir' | 'izin' | 'terlambat' | 'sakit' | 'alpa' | 'dinas_pagi' | 'dinas_sore' | 'cuti';
        notes: string | null;
        employee?: {
            id: number;
            nip: string;
            name: string;
            department?: DepartmentRef;
            subDepartment?: SubDepartmentRef;
            sub_department?: SubDepartmentRef;
            role?: { id: number; name: string };
        };
    }

    interface PaginatedAttendances {
        data: AttendanceItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        from: number | null;
        to: number | null;
        total: number;
    }

    interface StatsSummary {
        total_employees: number;
        hadir: number;
        terlambat: number;
        izin: number;
        sakit: number;
        alpa: number;
        dinas_pagi?: number;
        dinas_sore?: number;
        cuti?: number;
        recorded: number;
        unrecorded: number;
    }

    let {
        attendances,
        departments,
        subDepartments = [],
        employees,
        selectedDate,
        stats,
        filters,
    }: {
        attendances: PaginatedAttendances;
        departments: DepartmentRef[];
        subDepartments?: SubDepartmentRef[];
        employees: EmployeeSummary[];
        selectedDate: string;
        startDate?: string;
        endDate?: string;
        stats: StatsSummary;
        filters: {
            date?: string;
            start_date?: string;
            end_date?: string;
            department_ids?: number[];
            sub_department_ids?: number[];
            department_id?: number | null;
            sub_department_id?: number | null;
            statuses?: string[];
            status?: string | null;
            search?: string;
            sort?: string | null;
            direction?: 'asc' | 'desc' | null;
            per_page?: number | string | null;
        };
    } = $props();

    const defaultPerPage = 15;

    const initialDeptIds = filters.department_ids && filters.department_ids.length > 0
        ? filters.department_ids.map(Number)
        : filters.department_id
          ? [Number(filters.department_id)]
          : [];

    const initialSubDeptIds = filters.sub_department_ids && filters.sub_department_ids.length > 0
        ? filters.sub_department_ids.map(Number)
        : filters.sub_department_id
          ? [Number(filters.sub_department_id)]
          : [];

    const initialStatuses = filters.statuses && filters.statuses.length > 0
        ? filters.statuses
        : filters.status
          ? [filters.status]
          : [];

    const statusOptions = [
        { id: 'hadir', name: 'Hadir' },
        { id: 'terlambat', name: 'Terlambat' },
        { id: 'izin', name: 'Izin' },
        { id: 'sakit', name: 'Sakit' },
        { id: 'dinas_pagi', name: 'Dinas Pagi' },
        { id: 'dinas_sore', name: 'Dinas Sore' },
        { id: 'cuti', name: 'Cuti' },
        { id: 'alpa', name: 'Alpa' },
    ];

    let startDate = $state(filters.start_date ?? filters.date ?? selectedDate);
    let endDate = $state(filters.end_date ?? filters.date ?? selectedDate);
    let date = $derived(startDate === endDate ? startDate : '');
    let search = $state(filters.search ?? '');
    let filterDeptIds = $state<number[]>(initialDeptIds);
    let filterSubDeptIds = $state<number[]>(initialSubDeptIds);
    let filterStatuses = $state<string[]>(initialStatuses);
    let perPage = $state<number>(Number(filters.per_page) || defaultPerPage);

    let isModalOpen = $state(false);
    let isEditing = $state(false);
    let editingId = $state<number | null>(null);

    // Form states
    let formEmployeeId = $state<string>('');
    let formDate = $state(selectedDate);
    let formCheckIn = $state('08:00');
    let formCheckOut = $state('');
    let formCheckInLat = $state<number | null>(null);
    let formCheckInLng = $state<number | null>(null);
    let formCheckOutLat = $state<number | null>(null);
    let formCheckOutLng = $state<number | null>(null);
    let formStatus = $state<'hadir' | 'izin' | 'terlambat' | 'sakit' | 'alpa' | 'dinas_pagi' | 'dinas_sore' | 'cuti'>('hadir');
    let formNotes = $state('');
    let isSubmitting = $state(false);
    let errors = $state<Record<string, string>>({});

    let availableFilterSubDepartments = $derived(
        filterDeptIds.length > 0
            ? subDepartments
                  .filter((s) => filterDeptIds.includes(s.department_id))
                  .map((s) => ({
                      ...s,
                      subtitle: departments.find((d) => d.id === s.department_id)?.name,
                  }))
            : subDepartments.map((s) => ({
                  ...s,
                  subtitle: departments.find((d) => d.id === s.department_id)?.name,
              }))
    );

    function onDateRangeChange(range: { startDate: string; endDate: string }) {
        startDate = range.startDate;
        endDate = range.endDate;
        handleFilter();
    }

    let searchTimeout: any = null;

    function handleFilter() {
        router.get(
            '/attendances',
            {
                start_date: startDate || undefined,
                end_date: endDate || undefined,
                date: (startDate === endDate && startDate) ? startDate : undefined,
                search: search || undefined,
                department_ids: filterDeptIds.length > 0 ? filterDeptIds : undefined,
                sub_department_ids: filterSubDeptIds.length > 0 ? filterSubDeptIds : undefined,
                statuses: filterStatuses.length > 0 ? filterStatuses : undefined,
                sort: filters.sort || undefined,
                direction: filters.direction || undefined,
                per_page: perPage !== defaultPerPage ? perPage : undefined,
            },
            { preserveState: true, replace: true }
        );
    }

    function onSearchInput() {
        if (searchTimeout) clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            handleFilter();
        }, 400);
    }

    function onDepartmentsChange(newDeptIds: number[]) {
        filterDeptIds = newDeptIds;
        if (filterDeptIds.length > 0) {
            filterSubDeptIds = filterSubDeptIds.filter((subId) => {
                const sub = subDepartments.find((s) => s.id === subId);
                return sub && filterDeptIds.includes(sub.department_id);
            });
        } else {
            filterSubDeptIds = [];
        }
        handleFilter();
    }

    function onSubDepartmentsChange(newSubIds: number[]) {
        filterSubDeptIds = newSubIds;
        handleFilter();
    }

    function onStatusesChange(newStatuses: string[]) {
        filterStatuses = newStatuses;
        handleFilter();
    }

    function onPerPageChange(newSize: number) {
        perPage = newSize;
        handleFilter();
    }

    function handleSort(field: string, direction: 'asc' | 'desc' | null) {
        router.get(
            '/attendances',
            {
                start_date: startDate || undefined,
                end_date: endDate || undefined,
                date: (startDate === endDate && startDate) ? startDate : undefined,
                search: search || undefined,
                department_ids: filterDeptIds.length > 0 ? filterDeptIds : undefined,
                sub_department_ids: filterSubDeptIds.length > 0 ? filterSubDeptIds : undefined,
                statuses: filterStatuses.length > 0 ? filterStatuses : undefined,
                sort: direction ? field : undefined,
                direction: direction || undefined,
                per_page: perPage !== defaultPerPage ? perPage : undefined,
            },
            { preserveState: true, replace: true, preserveScroll: true }
        );
    }

    const activeFiltersCount = $derived(
        Number(Boolean(search)) +
        filterDeptIds.length +
        filterSubDeptIds.length +
        filterStatuses.length +
        Number(Boolean((startDate && startDate !== selectedDate) || (endDate && endDate !== selectedDate))) +
        Number(perPage !== defaultPerPage)
    );

    const selectedDepartments = $derived(
        departments.filter((d) => filterDeptIds.includes(d.id))
    );

    const selectedSubDepartments = $derived(
        subDepartments.filter((s) => filterSubDeptIds.includes(s.id))
    );

    const selectedStatuses = $derived(
        statusOptions.filter((s) => filterStatuses.includes(s.id))
    );

    function removeDeptChip(deptId: number) {
        filterDeptIds = filterDeptIds.filter((id) => id !== deptId);
        if (filterDeptIds.length > 0) {
            filterSubDeptIds = filterSubDeptIds.filter((subId) => {
                const sub = subDepartments.find((s) => s.id === subId);
                return sub && sub.department_id !== deptId;
            });
        } else {
            filterSubDeptIds = [];
        }
        handleFilter();
    }

    function removeSubDeptChip(subDeptId: number) {
        filterSubDeptIds = filterSubDeptIds.filter((id) => id !== subDeptId);
        handleFilter();
    }

    function removeStatusChip(statusId: string) {
        filterStatuses = filterStatuses.filter((id) => id !== statusId);
        handleFilter();
    }

    function getStatusLabel(st: string): string {
        switch (st) {
            case 'hadir': return 'Hadir';
            case 'terlambat': return 'Terlambat';
            case 'izin': return 'Izin';
            case 'sakit': return 'Sakit';
            case 'dinas_pagi': return 'Dinas Pagi';
            case 'dinas_sore': return 'Dinas Sore';
            case 'cuti': return 'Cuti';
            case 'alpa': return 'Alpa';
            default: return st;
        }
    }

    function formatDateIndonesian(dateString: string): string {
        if (!dateString) return '';
        try {
            const [y, m, d] = dateString.split('-').map(Number);
            const dt = new Date(y, m - 1, d);
            return dt.toLocaleDateString('id-ID', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            });
        } catch {
            return dateString;
        }
    }

    function formatDateRangeDisplay(s: string, e: string): string {
        if (!s && !e) return '';
        if (s === e) return formatDateIndonesian(s);
        return `${formatDateIndonesian(s)} s/d ${formatDateIndonesian(e)}`;
    }

    function goToPrevDay() {
        const base = startDate || selectedDate;
        const [y, m, d] = base.split('-').map(Number);
        const dt = new Date(y, m - 1, d - 1);
        const nextY = dt.getFullYear();
        const nextM = String(dt.getMonth() + 1).padStart(2, '0');
        const nextD = String(dt.getDate()).padStart(2, '0');
        const nextStr = `${nextY}-${nextM}-${nextD}`;
        startDate = nextStr;
        endDate = nextStr;
        handleFilter();
    }

    function goToNextDay() {
        const base = endDate || selectedDate;
        const [y, m, d] = base.split('-').map(Number);
        const dt = new Date(y, m - 1, d + 1);
        const nextY = dt.getFullYear();
        const nextM = String(dt.getMonth() + 1).padStart(2, '0');
        const nextD = String(dt.getDate()).padStart(2, '0');
        const nextStr = `${nextY}-${nextM}-${nextD}`;
        startDate = nextStr;
        endDate = nextStr;
        handleFilter();
    }

    function goToToday() {
        startDate = selectedDate;
        endDate = selectedDate;
        handleFilter();
    }

    function selectQuickStatus(st: string) {
        if (!st) {
            filterStatuses = [];
        } else {
            filterStatuses = filterStatuses.includes(st)
                ? filterStatuses.filter((s) => s !== st)
                : [...filterStatuses, st];
        }
        handleFilter();
    }

    function clearSearch() {
        search = '';
        if (searchTimeout) clearTimeout(searchTimeout);
        handleFilter();
    }

    function clearDate() {
        startDate = selectedDate;
        endDate = selectedDate;
        handleFilter();
    }

    function removePerPageChip() {
        perPage = defaultPerPage;
        handleFilter();
    }

    function resetAllFilters() {
        startDate = selectedDate;
        endDate = selectedDate;
        search = '';
        if (searchTimeout) clearTimeout(searchTimeout);
        filterDeptIds = [];
        filterSubDeptIds = [];
        filterStatuses = [];
        perPage = defaultPerPage;
        handleFilter();
    }

    function openCreateModal() {
        isEditing = false;
        editingId = null;
        formEmployeeId = employees[0]?.id ? String(employees[0].id) : '';
        formDate = (startDate === endDate && startDate) ? startDate : selectedDate;
        formCheckIn = '08:00';
        formCheckOut = '';
        formCheckInLat = null;
        formCheckInLng = null;
        formCheckOutLat = null;
        formCheckOutLng = null;
        formStatus = 'hadir';
        formNotes = '';
        errors = {};
        isModalOpen = true;
    }

    function openEditModal(att: AttendanceItem) {
        isEditing = true;
        editingId = att.id;
        formEmployeeId = String(att.employee_id);
        formDate = att.date;
        formCheckIn = att.check_in ? att.check_in.substring(0, 5) : '';
        formCheckOut = att.check_out ? att.check_out.substring(0, 5) : '';
        formCheckInLat = att.check_in_latitude ?? null;
        formCheckInLng = att.check_in_longitude ?? null;
        formCheckOutLat = att.check_out_latitude ?? null;
        formCheckOutLng = att.check_out_longitude ?? null;
        formStatus = att.status;
        formNotes = att.notes ?? '';
        errors = {};
        isModalOpen = true;
    }

    function submitForm(e: SubmitEvent) {
        e.preventDefault();
        isSubmitting = true;
        errors = {};

        const payload: Record<string, any> = {
            id: editingId || undefined,
            employee_id: Number(formEmployeeId),
            date: formDate,
            check_in: formCheckIn ? `${formCheckIn}:00` : null,
            check_in_latitude: formCheckInLat,
            check_in_longitude: formCheckInLng,
            check_out: formCheckOut ? `${formCheckOut}:00` : null,
            check_out_latitude: formCheckOutLat,
            check_out_longitude: formCheckOutLng,
            status: formStatus,
            notes: formNotes || null,
        };

        if (isEditing && editingId) {
            router.put(`/attendances/${editingId}`, payload, {
                onSuccess: () => {
                    isModalOpen = false;
                },
                onError: (err) => {
                    errors = err;
                },
                onFinish: () => {
                    isSubmitting = false;
                },
            });
        } else {
            router.post('/attendances', payload, {
                onSuccess: () => {
                    isModalOpen = false;
                },
                onError: (err) => {
                    errors = err;
                },
                onFinish: () => {
                    isSubmitting = false;
                },
            });
        }
    }

    function deleteAttendance(att: AttendanceItem) {
        if (confirm(`Apakah Anda yakin ingin menghapus data kehadiran untuk "${att.employee?.name}" pada tanggal ${att.date}?`)) {
            router.delete(`/attendances/${att.id}`, {
                preserveScroll: true,
            });
        }
    }

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

    const authUser = $derived(page.props.auth?.user as { employee?: { department_id?: number; role?: { slug: string } } } | undefined);
    const roleSlug = $derived(authUser?.employee?.role?.slug ?? 'super-admin');
    const isPegawai = $derived(roleSlug === 'pegawai');

    const columns = $derived<ColumnDef<any, AttendanceItem>[]>([
        {
            id: 'index',
            header: '#',
            enableSorting: false,
            meta: { align: 'center', headerClass: 'w-12 text-center', class: 'text-center font-medium text-muted-foreground' },
            cell: (info) => renderSnippet(indexCell, { row: info.row }),
        },
        ...(!isPegawai
            ? [
                  {
                      accessorFn: (row: AttendanceItem) => row.employee?.name ?? '',
                      id: 'employee',
                      header: 'Pegawai',
                      enableSorting: true,
                      meta: { headerClass: 'min-w-[200px]' },
                      cell: (info: any) => renderSnippet(employeeCell, { row: info.row }),
                  },
                  {
                      accessorFn: (row: AttendanceItem) => row.employee?.department?.name ?? '',
                      id: 'department',
                      header: 'Bagian & Role',
                      enableSorting: true,
                      meta: { headerClass: 'min-w-[180px]' },
                      cell: (info: any) => renderSnippet(deptCell, { row: info.row }),
                  },
              ]
            : []),
        {
            accessorKey: 'date',
            header: 'Tanggal',
            enableSorting: true,
            meta: { align: 'center', headerClass: 'w-32 text-center', class: 'text-center whitespace-nowrap' },
            cell: (info) => renderSnippet(dateCell, { row: info.row }),
        },
        {
            accessorKey: 'check_in',
            header: 'Jam Masuk',
            enableSorting: true,
            meta: { align: 'center', headerClass: 'w-32 text-center', class: 'text-center whitespace-nowrap' },
            cell: (info) => renderSnippet(checkInCell, { row: info.row }),
        },
        {
            accessorKey: 'check_out',
            header: 'Jam Pulang',
            enableSorting: true,
            meta: { align: 'center', headerClass: 'w-32 text-center', class: 'text-center whitespace-nowrap' },
            cell: (info) => renderSnippet(checkOutCell, { row: info.row }),
        },
        {
            accessorKey: 'status',
            header: 'Status',
            enableSorting: true,
            meta: { align: 'center', headerClass: 'w-36 text-center', class: 'text-center whitespace-nowrap' },
            cell: (info) => renderSnippet(statusCell, { row: info.row }),
        },
        {
            accessorKey: 'notes',
            header: 'Keterangan',
            enableSorting: false,
            meta: { headerClass: 'min-w-[160px]', class: 'text-xs text-muted-foreground' },
            cell: (info) => renderSnippet(notesCell, { row: info.row }),
        },
        ...(!isPegawai
            ? [
                  {
                      id: 'actions',
                      header: 'Aksi',
                      enableSorting: false,
                      meta: { align: 'right', headerClass: 'w-24 text-right', class: 'text-right' },
                      cell: (info: any) => renderSnippet(actionsCell, { row: info.row }),
                  },
              ]
            : []),
    ]);
</script>

<AppHead title="Kehadiran Pegawai" />

{#snippet indexCell({ row }: { row: any })}
    <span class="font-medium text-muted-foreground/70">
        {(attendances.from ?? 1) + row.index}
    </span>
{/snippet}

{#snippet employeeCell({ row }: { row: any })}
    {@const att = row.original as AttendanceItem}
    <div class="flex items-center gap-3">
        <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary font-bold text-xs border border-primary/20 shadow-2xs">
            {getInitials(att.employee?.name ?? 'P')}
        </div>
        <div class="min-w-0">
            <div class="font-semibold text-foreground text-sm truncate max-w-[180px]" title={att.employee?.name}>
                {att.employee?.name ?? '-'}
            </div>
            <div class="text-xs font-mono text-muted-foreground mt-0.5">
                NIP: {att.employee?.nip ?? '-'}
            </div>
        </div>
    </div>
{/snippet}

{#snippet deptCell({ row }: { row: any })}
    {@const att = row.original as AttendanceItem}
    <div class="flex flex-col gap-0.5">
        <div class="font-medium text-foreground text-xs flex items-center gap-1.5">
            <Building2 class="size-3.5 text-indigo-500 shrink-0" />
            <span class="truncate max-w-[160px]" title={att.employee?.department?.name}>{att.employee?.department?.name ?? '-'}</span>
        </div>
        {#if att.employee?.subDepartment || att.employee?.sub_department}
            <div class="inline-flex items-center gap-1 text-[11px] text-muted-foreground pl-3.5">
                <GitFork class="size-2.5 text-purple-500 shrink-0" />
                <span class="truncate max-w-[150px]">{att.employee.subDepartment?.name ?? att.employee.sub_department?.name}</span>
            </div>
        {/if}
        <div class="text-[11px] text-muted-foreground pl-3.5">
            {att.employee?.role?.name ?? '-'}
        </div>
    </div>
{/snippet}

{#snippet dateCell({ row }: { row: any })}
    {@const att = row.original as AttendanceItem}
    <span class="inline-flex items-center px-2 py-0.5 rounded-md font-mono text-xs font-medium bg-muted/50 text-foreground border border-border/60">
        {att.date}
    </span>
{/snippet}

{#snippet checkInCell({ row }: { row: any })}
    {@const att = row.original as AttendanceItem}
    <div class="flex items-center justify-center gap-1.5">
        {#if att.check_in}
            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-muted/60 text-foreground font-mono font-medium text-xs border border-border/60">
                {att.check_in.substring(0, 5)}
            </span>
            {#if att.check_in_latitude && att.check_in_longitude}
                <a
                    href={att.check_in_map_url || `https://www.google.com/maps?q=${att.check_in_latitude},${att.check_in_longitude}`}
                    target="_blank"
                    rel="noreferrer"
                    class="text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300 p-1 rounded-md hover:bg-emerald-500/10 transition-colors inline-flex items-center"
                    title={`GPS Masuk: ${att.check_in_latitude.toFixed(5)}, ${att.check_in_longitude.toFixed(5)} (Buka Google Maps)`}
                >
                    <MapPin class="size-3.5" />
                </a>
            {/if}
        {:else}
            <span class="text-muted-foreground/40 font-mono">-</span>
        {/if}
    </div>
{/snippet}

{#snippet checkOutCell({ row }: { row: any })}
    {@const att = row.original as AttendanceItem}
    <div class="flex items-center justify-center gap-1.5">
        {#if att.check_out}
            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-muted/60 text-foreground font-mono font-medium text-xs border border-border/60">
                {att.check_out.substring(0, 5)}
            </span>
            {#if att.check_out_latitude && att.check_out_longitude}
                <a
                    href={att.check_out_map_url || `https://www.google.com/maps?q=${att.check_out_latitude},${att.check_out_longitude}`}
                    target="_blank"
                    rel="noreferrer"
                    class="text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 p-1 rounded-md hover:bg-blue-500/10 transition-colors inline-flex items-center"
                    title={`GPS Pulang: ${att.check_out_latitude.toFixed(5)}, ${att.check_out_longitude.toFixed(5)} (Buka Google Maps)`}
                >
                    <MapPin class="size-3.5" />
                </a>
            {/if}
        {:else}
            <span class="text-muted-foreground/40 font-mono">-</span>
        {/if}
    </div>
{/snippet}

{#snippet statusCell({ row }: { row: any })}
    {@const att = row.original as AttendanceItem}
    {@const badge = getStatusBadge(att.status)}
    <span class={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium border ${badge.class}`}>
        <span class="size-1.5 rounded-full bg-current opacity-80"></span>
        {badge.label}
    </span>
{/snippet}

{#snippet notesCell({ row }: { row: any })}
    {@const att = row.original as AttendanceItem}
    <span class="truncate max-w-[200px] block text-muted-foreground" title={att.notes || undefined}>
        {att.notes || '-'}
    </span>
{/snippet}

{#snippet actionsCell({ row }: { row: any })}
    {@const att = row.original as AttendanceItem}
    <div class="flex items-center justify-end gap-1">
        <Button
            variant="ghost"
            size="icon"
            class="size-8 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted cursor-pointer"
            onclick={() => openEditModal(att)}
            title="Koreksi Kehadiran"
        >
            <Pencil class="size-4" />
        </Button>
        <Button
            variant="ghost"
            size="icon"
            class="size-8 rounded-lg text-destructive hover:bg-destructive/10 cursor-pointer"
            onclick={() => deleteAttendance(att)}
            title="Hapus Kehadiran"
        >
            <Trash2 class="size-4" />
        </Button>
    </div>
{/snippet}

<div class="flex flex-col gap-6 p-4 md:p-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <div class="flex size-9 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400">
                    <CalendarCheck class="size-5" />
                </div>
                <h1 class="text-2xl font-bold tracking-tight">
                    {isPegawai ? 'Riwayat Kehadiran Saya' : 'Presensi & Kehadiran Pegawai'}
                </h1>
            </div>
            <p class="text-sm text-muted-foreground mt-1">
                {isPegawai
                    ? 'Catatan riwayat presensi pribadi harian, status kehadiran, dan verifikasi jam masuk/pulang.'
                    : 'Catatan presensi harian, status kehadiran, rekapitulasi, dan verifikasi jam masuk/pulang.'}
            </p>
        </div>

        {#if !isPegawai}
            <Button onclick={openCreateModal} class="flex items-center gap-2">
                <Plus class="size-4" />
                <span>Catat Kehadiran</span>
            </Button>
        {/if}
    </div>

    <!-- Daily Metrics Cards for Selected Date -->
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-9 gap-2.5">
        <div class="p-3.5 rounded-xl border bg-card text-card-foreground shadow-sm flex flex-col justify-between">
            <span class="text-[11px] font-medium text-muted-foreground">{isPegawai ? 'Status Saya' : 'Total Pegawai'}</span>
            <div class="flex items-baseline justify-between mt-2">
                <span class="text-xl font-bold">{stats.total_employees}</span>
                <Users class="size-3.5 text-muted-foreground" />
            </div>
        </div>

        <div class="p-3.5 rounded-xl border bg-card text-card-foreground shadow-sm flex flex-col justify-between border-l-4 border-l-emerald-500">
            <span class="text-[11px] font-medium text-emerald-600 dark:text-emerald-400">Hadir</span>
            <div class="flex items-baseline justify-between mt-2">
                <span class="text-xl font-bold text-emerald-600 dark:text-emerald-400">{stats.hadir}</span>
                <CheckCircle2 class="size-3.5 text-emerald-500" />
            </div>
        </div>

        <div class="p-3.5 rounded-xl border bg-card text-card-foreground shadow-sm flex flex-col justify-between border-l-4 border-l-blue-500">
            <span class="text-[11px] font-medium text-blue-600 dark:text-blue-400">Izin</span>
            <div class="flex items-baseline justify-between mt-2">
                <span class="text-xl font-bold text-blue-600 dark:text-blue-400">{stats.izin}</span>
                <AlertCircle class="size-3.5 text-blue-500" />
            </div>
        </div>

        <div class="p-3.5 rounded-xl border bg-card text-card-foreground shadow-sm flex flex-col justify-between border-l-4 border-l-amber-500">
            <span class="text-[11px] font-medium text-amber-600 dark:text-amber-400">Terlambat</span>
            <div class="flex items-baseline justify-between mt-2">
                <span class="text-xl font-bold text-amber-600 dark:text-amber-400">{stats.terlambat}</span>
                <Clock class="size-3.5 text-amber-500" />
            </div>
        </div>

        <div class="p-3.5 rounded-xl border bg-card text-card-foreground shadow-sm flex flex-col justify-between border-l-4 border-l-purple-500">
            <span class="text-[11px] font-medium text-purple-600 dark:text-purple-400">Sakit</span>
            <div class="flex items-baseline justify-between mt-2">
                <span class="text-xl font-bold text-purple-600 dark:text-purple-400">{stats.sakit}</span>
                <AlertCircle class="size-3.5 text-purple-500" />
            </div>
        </div>

        <div class="p-3.5 rounded-xl border bg-card text-card-foreground shadow-sm flex flex-col justify-between border-l-4 border-l-sky-500">
            <span class="text-[11px] font-medium text-sky-600 dark:text-sky-400">Dinas Pagi</span>
            <div class="flex items-baseline justify-between mt-2">
                <span class="text-xl font-bold text-sky-600 dark:text-sky-400">{stats.dinas_pagi ?? 0}</span>
                <Clock class="size-3.5 text-sky-500" />
            </div>
        </div>

        <div class="p-3.5 rounded-xl border bg-card text-card-foreground shadow-sm flex flex-col justify-between border-l-4 border-l-indigo-500">
            <span class="text-[11px] font-medium text-indigo-600 dark:text-indigo-400">Dinas Sore</span>
            <div class="flex items-baseline justify-between mt-2">
                <span class="text-xl font-bold text-indigo-600 dark:text-indigo-400">{stats.dinas_sore ?? 0}</span>
                <Clock class="size-3.5 text-indigo-500" />
            </div>
        </div>

        <div class="p-3.5 rounded-xl border bg-card text-card-foreground shadow-sm flex flex-col justify-between border-l-4 border-l-teal-500">
            <span class="text-[11px] font-medium text-teal-600 dark:text-teal-400">Cuti</span>
            <div class="flex items-baseline justify-between mt-2">
                <span class="text-xl font-bold text-teal-600 dark:text-teal-400">{stats.cuti ?? 0}</span>
                <CalendarCheck class="size-3.5 text-teal-500" />
            </div>
        </div>

        <div class="p-3.5 rounded-xl border bg-card text-card-foreground shadow-sm flex flex-col justify-between border-l-4 border-l-rose-500">
            <span class="text-[11px] font-medium text-rose-600 dark:text-rose-400">Alpa / Belum</span>
            <div class="flex items-baseline justify-between mt-2">
                <span class="text-xl font-bold text-rose-600 dark:text-rose-400">{stats.alpa + stats.unrecorded}</span>
                <XCircle class="size-3.5 text-rose-500" />
            </div>
        </div>
    </div>

    <!-- Professional Filter Card -->
    <div class="rounded-xl border border-border/80 bg-card p-4 shadow-xs space-y-3.5">
        <!-- Top bar: Header & Date Navigation Controls -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-border/60">
            <div class="flex items-center gap-2">
                <div class="flex size-7 items-center justify-center rounded-md bg-blue-500/10 text-blue-600 dark:text-blue-400">
                    <SlidersHorizontal class="size-3.5" />
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-foreground">Filter & Rentang Waktu</span>
                    {#if activeFiltersCount > 0}
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-blue-600 text-white">
                            {activeFiltersCount} Aktif
                        </span>
                    {/if}
                </div>
            </div>

            <!-- Date quick navigations -->
            <div class="flex items-center gap-1.5 self-start sm:self-auto">
                <div class="inline-flex items-center rounded-lg border border-input bg-background p-0.5 shadow-2xs">
                    <button
                        type="button"
                        onclick={goToPrevDay}
                        class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded-md transition-colors cursor-pointer"
                        title="Hari Sebelumnya"
                    >
                        <ChevronLeft class="size-4" />
                    </button>
                    <button
                        type="button"
                        onclick={goToToday}
                        class="px-2.5 py-1 text-xs font-semibold rounded-md transition-colors {startDate === selectedDate && endDate === selectedDate ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground hover:bg-muted'} cursor-pointer"
                    >
                        Hari Ini
                    </button>
                    <button
                        type="button"
                        onclick={goToNextDay}
                        class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded-md transition-colors cursor-pointer"
                        title="Hari Berikutnya"
                    >
                        <ChevronRight class="size-4" />
                    </button>
                </div>

                {#if activeFiltersCount > 0}
                    <button
                        type="button"
                        onclick={resetAllFilters}
                        class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-destructive px-2 py-1.5 rounded-md hover:bg-muted transition-colors cursor-pointer"
                    >
                        <RotateCcw class="size-3" />
                        <span>Reset</span>
                    </button>
                {/if}
            </div>
        </div>

        <!-- Filter Controls Row -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
            <!-- Tanggal Range Picker -->
            <div class="{isPegawai ? 'lg:col-span-6' : 'lg:col-span-3'} relative">
                <DateRangeFilter
                    bind:startDate
                    bind:endDate
                    today={selectedDate}
                    onchange={onDateRangeChange}
                />
            </div>

            <!-- Search Pegawai (non-pegawai) -->
            {#if !isPegawai}
                <div class="{filterDeptIds.length > 0 ? 'lg:col-span-3' : 'lg:col-span-5'} relative">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground pointer-events-none" />
                    <Input
                        type="text"
                        placeholder="Cari NIP atau nama pegawai..."
                        class="pl-9 pr-8 w-full bg-background"
                        bind:value={search}
                        oninput={onSearchInput}
                        onkeydown={(e) => e.key === 'Enter' && handleFilter()}
                    />
                    {#if search}
                        <button
                            type="button"
                            onclick={clearSearch}
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 size-4 text-muted-foreground hover:text-foreground inline-flex items-center justify-center rounded-sm cursor-pointer"
                            title="Hapus pencarian"
                        >
                            <X class="size-3.5" />
                        </button>
                    {/if}
                </div>

                <!-- Departemen / Bagian Filter -->
                <div class="lg:col-span-2 relative">
                    <MultiSelectFilter
                        title="Bagian"
                        placeholder="Semua Bagian"
                        items={departments}
                        bind:selected={filterDeptIds}
                        icon={Building2}
                        iconColor="text-indigo-500"
                        onchange={onDepartmentsChange}
                    />
                </div>

                <!-- Sub Departemen / Sub Bagian Filter - Hanya muncul jika bagian dipilih -->
                {#if filterDeptIds.length > 0}
                    <div class="lg:col-span-2 relative">
                        <MultiSelectFilter
                            title="Sub Bagian"
                            placeholder="Semua Sub Bagian"
                            items={availableFilterSubDepartments}
                            bind:selected={filterSubDeptIds}
                            icon={GitFork}
                            iconColor="text-purple-500"
                            onchange={onSubDepartmentsChange}
                        />
                    </div>
                {/if}
            {/if}

            <!-- Status Filter (Multi-Select) -->
            <div class="{isPegawai ? 'lg:col-span-6' : 'lg:col-span-2'} relative">
                <MultiSelectFilter
                    title="Status"
                    placeholder="Semua Status"
                    items={statusOptions}
                    bind:selected={filterStatuses}
                    icon={CheckCircle2}
                    iconColor="text-emerald-500"
                    onchange={onStatusesChange}
                />
            </div>
        </div>

        <!-- Quick Status Filter Pills (Tabs) -->
        <div class="pt-2 border-t border-border/60">
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none text-xs">
                <span class="text-muted-foreground text-[11px] shrink-0 mr-1 hidden sm:inline">Status Cepat:</span>
                
                <button
                    type="button"
                    onclick={() => selectQuickStatus('')}
                    class="shrink-0 px-2.5 py-1 rounded-md text-xs font-medium transition-colors cursor-pointer {filterStatuses.length === 0 ? 'bg-primary text-primary-foreground shadow-2xs' : 'bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground'}"
                >
                    Semua ({stats.total_employees})
                </button>

                <button
                    type="button"
                    onclick={() => selectQuickStatus('hadir')}
                    class="shrink-0 px-2.5 py-1 rounded-md text-xs font-medium transition-colors cursor-pointer {filterStatuses.includes('hadir') ? 'bg-emerald-600 text-white shadow-2xs' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/60 dark:text-emerald-300'}"
                >
                    Hadir ({stats.hadir})
                </button>

                <button
                    type="button"
                    onclick={() => selectQuickStatus('terlambat')}
                    class="shrink-0 px-2.5 py-1 rounded-md text-xs font-medium transition-colors cursor-pointer {filterStatuses.includes('terlambat') ? 'bg-amber-600 text-white shadow-2xs' : 'bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-950/60 dark:text-amber-300'}"
                >
                    Terlambat ({stats.terlambat})
                </button>

                <button
                    type="button"
                    onclick={() => selectQuickStatus('izin')}
                    class="shrink-0 px-2.5 py-1 rounded-md text-xs font-medium transition-colors cursor-pointer {filterStatuses.includes('izin') ? 'bg-blue-600 text-white shadow-2xs' : 'bg-blue-50 text-blue-700 hover:bg-blue-100 dark:bg-blue-950/60 dark:text-blue-300'}"
                >
                    Izin ({stats.izin})
                </button>

                <button
                    type="button"
                    onclick={() => selectQuickStatus('sakit')}
                    class="shrink-0 px-2.5 py-1 rounded-md text-xs font-medium transition-colors cursor-pointer {filterStatuses.includes('sakit') ? 'bg-purple-600 text-white shadow-2xs' : 'bg-purple-50 text-purple-700 hover:bg-purple-100 dark:bg-purple-950/60 dark:text-purple-300'}"
                >
                    Sakit ({stats.sakit})
                </button>

                <button
                    type="button"
                    onclick={() => selectQuickStatus('dinas_pagi')}
                    class="shrink-0 px-2.5 py-1 rounded-md text-xs font-medium transition-colors cursor-pointer {filterStatuses.includes('dinas_pagi') ? 'bg-sky-600 text-white shadow-2xs' : 'bg-sky-50 text-sky-700 hover:bg-sky-100 dark:bg-sky-950/60 dark:text-sky-300'}"
                >
                    Dinas Pagi ({stats.dinas_pagi ?? 0})
                </button>

                <button
                    type="button"
                    onclick={() => selectQuickStatus('dinas_sore')}
                    class="shrink-0 px-2.5 py-1 rounded-md text-xs font-medium transition-colors cursor-pointer {filterStatuses.includes('dinas_sore') ? 'bg-indigo-600 text-white shadow-2xs' : 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:text-indigo-300'}"
                >
                    Dinas Sore ({stats.dinas_sore ?? 0})
                </button>

                <button
                    type="button"
                    onclick={() => selectQuickStatus('cuti')}
                    class="shrink-0 px-2.5 py-1 rounded-md text-xs font-medium transition-colors cursor-pointer {filterStatuses.includes('cuti') ? 'bg-teal-600 text-white shadow-2xs' : 'bg-teal-50 text-teal-700 hover:bg-teal-100 dark:bg-teal-950/60 dark:text-teal-300'}"
                >
                    Cuti ({stats.cuti ?? 0})
                </button>

                <button
                    type="button"
                    onclick={() => selectQuickStatus('alpa')}
                    class="shrink-0 px-2.5 py-1 rounded-md text-xs font-medium transition-colors cursor-pointer {filterStatuses.includes('alpa') ? 'bg-rose-600 text-white shadow-2xs' : 'bg-rose-50 text-rose-700 hover:bg-rose-100 dark:bg-rose-950/60 dark:text-rose-300'}"
                >
                    Alpa / Belum ({stats.alpa + stats.unrecorded})
                </button>
            </div>
        </div>

        <!-- Active Filter Pills/Chips -->
        {#if activeFiltersCount > 0}
            <div class="flex flex-wrap items-center gap-1.5 pt-2 border-t border-border/60 text-xs">
                <span class="text-muted-foreground text-[11px] mr-1">Filter Aktif:</span>

                {#if (startDate && startDate !== selectedDate) || (endDate && endDate !== selectedDate)}
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-blue-50 dark:bg-blue-950/50 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800 text-xs font-medium">
                        <Calendar class="size-3" />
                        <span>Tanggal: {formatDateRangeDisplay(startDate, endDate)}</span>
                        <button type="button" onclick={clearDate} class="ml-0.5 hover:text-destructive cursor-pointer" title="Kembali ke hari ini">
                            <X class="size-3" />
                        </button>
                    </span>
                {/if}

                {#if search}
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-primary/10 text-primary border border-primary/20 text-xs font-medium">
                        <Search class="size-3" />
                        <span>"{search}"</span>
                        <button type="button" onclick={clearSearch} class="ml-0.5 hover:text-destructive cursor-pointer">
                            <X class="size-3" />
                        </button>
                    </span>
                {/if}

                {#each selectedDepartments as dept (dept.id)}
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 text-xs font-medium">
                        <Building2 class="size-3" />
                        <span>Bagian: {dept.name}</span>
                        <button type="button" onclick={() => removeDeptChip(dept.id)} class="ml-0.5 hover:text-destructive cursor-pointer">
                            <X class="size-3" />
                        </button>
                    </span>
                {/each}

                {#each selectedSubDepartments as sub (sub.id)}
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-purple-50 dark:bg-purple-950/50 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800 text-xs font-medium">
                        <GitFork class="size-3" />
                        <span>Sub Bagian: {sub.name}</span>
                        <button type="button" onclick={() => removeSubDeptChip(sub.id)} class="ml-0.5 hover:text-destructive cursor-pointer">
                            <X class="size-3" />
                        </button>
                    </span>
                {/each}

                {#each selectedStatuses as st (st.id)}
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-xs font-medium">
                        <CheckCircle2 class="size-3" />
                        <span>Status: {st.name}</span>
                        <button type="button" onclick={() => removeStatusChip(st.id)} class="ml-0.5 hover:text-destructive cursor-pointer">
                            <X class="size-3" />
                        </button>
                    </span>
                {/each}

                {#if perPage !== defaultPerPage}
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 border border-zinc-200 dark:border-zinc-700 text-xs font-medium">
                        <Rows3 class="size-3" />
                        <span>{perPage} baris / hal</span>
                        <button type="button" onclick={removePerPageChip} class="ml-0.5 hover:text-destructive cursor-pointer" title="Reset ukuran halaman">
                            <X class="size-3" />
                        </button>
                    </span>
                {/if}
            </div>
        {/if}
    </div>

    <!-- Table with TanStack Table -->
    <DataTable
        data={attendances.data}
        {columns}
        emptyMessage={startDate === endDate ? `Tidak ada data presensi pada tanggal ${formatDateIndonesian(startDate)} yang sesuai filter.` : `Tidak ada data presensi pada periode ${formatDateRangeDisplay(startDate, endDate)} yang sesuai filter.`}
        manualSorting={true}
        sortField={filters.sort}
        sortDirection={filters.direction}
        onSortChange={handleSort}
    >
        <Pagination
            links={attendances.links}
            from={attendances.from}
            to={attendances.to}
            total={attendances.total}
            {perPage}
            perPageOptions={[10, 15, 25, 50, 100]}
            {onPerPageChange}
        />
    </DataTable>
</div>

<!-- Modal Dialog Catat / Koreksi Kehadiran -->
<Dialog bind:open={isModalOpen}>
    <DialogContent class="max-w-md">
        <DialogTitle>{isEditing ? 'Koreksi Data Kehadiran' : 'Catat Kehadiran Pegawai'}</DialogTitle>
        <DialogDescription>
            {isEditing
                ? 'Ubah jam masuk, pulang, atau status presensi pegawai terpilih.'
                : 'Pilih pegawai dan tentukan tanggal serta status kehadiran.'}
        </DialogDescription>

        <form onsubmit={submitForm} class="space-y-4 mt-2">
            <!-- Pegawai -->
            <div class="space-y-2">
                <Label for="att-emp">Pegawai <span class="text-destructive">*</span></Label>
                <select
                    id="att-emp"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    bind:value={formEmployeeId}
                    disabled={isEditing}
                    required
                >
                    <option value="" disabled>-- Pilih Pegawai --</option>
                    {#each employees as emp}
                        <option value={String(emp.id)}>
                            {emp.name} ({emp.nip})
                        </option>
                    {/each}
                </select>
                {#if errors.employee_id}
                    <p class="text-xs text-destructive">{errors.employee_id}</p>
                {/if}
            </div>

            <!-- Tanggal & Status -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-2">
                    <Label for="att-date">Tanggal <span class="text-destructive">*</span></Label>
                    <Input
                        id="att-date"
                        type="date"
                        bind:value={formDate}
                        disabled={isEditing}
                        required
                    />
                    {#if errors.date}
                        <p class="text-xs text-destructive">{errors.date}</p>
                    {/if}
                </div>

                <div class="space-y-2">
                    <Label for="att-status">Status Presensi <span class="text-destructive">*</span></Label>
                    <select
                        id="att-status"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        bind:value={formStatus}
                        required
                    >
                        <option value="hadir">Hadir</option>
                        <option value="izin">Izin</option>
                        <option value="terlambat">Terlambat</option>
                        <option value="sakit">Sakit</option>
                        <option value="alpa">Alpa</option>
                        <option value="dinas_pagi">Dinas Pagi</option>
                        <option value="dinas_sore">Dinas Sore</option>
                        <option value="cuti">Cuti</option>
                    </select>
                    {#if errors.status}
                        <p class="text-xs text-destructive">{errors.status}</p>
                    {/if}
                </div>
            </div>

            <!-- Jam Masuk & Jam Keluar -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-2">
                    <Label for="att-checkin">Jam Masuk (Check-In)</Label>
                    <Input
                        id="att-checkin"
                        type="time"
                        bind:value={formCheckIn}
                    />
                    {#if errors.check_in}
                        <p class="text-xs text-destructive">{errors.check_in}</p>
                    {/if}
                </div>

                <div class="space-y-2">
                    <Label for="att-checkout">Jam Pulang (Check-Out)</Label>
                    <Input
                        id="att-checkout"
                        type="time"
                        bind:value={formCheckOut}
                    />
                    {#if errors.check_out}
                        <p class="text-xs text-destructive">{errors.check_out}</p>
                    {/if}
                </div>
            </div>

            <!-- GPS Coordinates Display if available -->
            {#if isEditing && (formCheckInLat || formCheckOutLat)}
                <div class="rounded-lg border bg-muted/40 p-3 text-xs flex flex-col gap-2">
                    <span class="font-medium text-foreground flex items-center gap-1.5">
                        <MapPin class="size-3.5 text-primary" />
                        Titik Koordinat GPS Tercatat
                    </span>
                    {#if formCheckInLat && formCheckInLng}
                        <div class="flex items-center justify-between text-muted-foreground">
                            <span>Masuk: {formCheckInLat.toFixed(5)}, {formCheckInLng.toFixed(5)}</span>
                            <a
                                href={`https://www.google.com/maps?q=${formCheckInLat},${formCheckInLng}`}
                                target="_blank"
                                rel="noreferrer"
                                class="text-emerald-600 dark:text-emerald-400 hover:underline inline-flex items-center gap-1 font-medium"
                            >
                                Peta Masuk <ExternalLink class="size-2.5" />
                            </a>
                        </div>
                    {/if}
                    {#if formCheckOutLat && formCheckOutLng}
                        <div class="flex items-center justify-between text-muted-foreground">
                            <span>Pulang: {formCheckOutLat.toFixed(5)}, {formCheckOutLng.toFixed(5)}</span>
                            <a
                                href={`https://www.google.com/maps?q=${formCheckOutLat},${formCheckOutLng}`}
                                target="_blank"
                                rel="noreferrer"
                                class="text-blue-600 dark:text-blue-400 hover:underline inline-flex items-center gap-1 font-medium"
                            >
                                Peta Pulang <ExternalLink class="size-2.5" />
                            </a>
                        </div>
                    {/if}
                </div>
            {/if}

            <!-- Keterangan / Catatan -->
            <div class="space-y-2">
                <Label for="att-notes">Keterangan / Catatan</Label>
                <textarea
                    id="att-notes"
                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-ring"
                    rows="2"
                    placeholder="Contoh: Izin urusan dinas luar kota, surat dokter terlampir..."
                    bind:value={formNotes}
                ></textarea>
                {#if errors.notes}
                    <p class="text-xs text-destructive">{errors.notes}</p>
                {/if}
            </div>

            <DialogFooter class="mt-6 flex justify-end gap-2">
                <DialogClose>
                    <Button type="button" variant="outline">Batal</Button>
                </DialogClose>
                <Button type="submit" disabled={isSubmitting}>
                    {isSubmitting ? 'Menyimpan...' : 'Simpan Kehadiran'}
                </Button>
            </DialogFooter>
        </form>
    </DialogContent>
</Dialog>

