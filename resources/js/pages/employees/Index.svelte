<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: '/dashboard',
            },
            {
                title: 'Data Pegawai',
                href: '/employees',
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Briefcase from '@lucide/svelte/icons/briefcase';
    import Building2 from '@lucide/svelte/icons/building-2';
    import Camera from '@lucide/svelte/icons/camera';
    import ChevronDown from '@lucide/svelte/icons/chevron-down';
    import Eye from '@lucide/svelte/icons/eye';
    import EyeOff from '@lucide/svelte/icons/eye-off';
    import GitFork from '@lucide/svelte/icons/git-fork';
    import KeyRound from '@lucide/svelte/icons/key-round';
    import Mail from '@lucide/svelte/icons/mail';
    import Pencil from '@lucide/svelte/icons/pencil';
    import Phone from '@lucide/svelte/icons/phone';
    import Plus from '@lucide/svelte/icons/plus';
    import RotateCcw from '@lucide/svelte/icons/rotate-ccw';
    import Rows3 from '@lucide/svelte/icons/rows-3';
    import Search from '@lucide/svelte/icons/search';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import SlidersHorizontal from '@lucide/svelte/icons/sliders-horizontal';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import Upload from '@lucide/svelte/icons/upload';
    import UserCheck from '@lucide/svelte/icons/user-check';
    import Users from '@lucide/svelte/icons/users';
    import X from '@lucide/svelte/icons/x';
    import AppHead from '@/components/AppHead.svelte';
    import DataTable from '@/components/DataTable.svelte';
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

    interface RoleRef {
        id: number;
        name: string;
    }

    interface EmployeeStats {
        total: number;
        active: number;
        inactive: number;
        with_user: number;
    }

    interface EmployeeItem {
        id: number;
        nip: string;
        name: string;
        email: string | null;
        phone: string | null;
        role_id: number;
        department_id: number;
        sub_department_id: number | null;
        gender: string | null;
        address: string | null;
        status: 'active' | 'inactive';
        avatar_url: string | null;
        role?: RoleRef;
        department?: DepartmentRef;
        sub_department?: SubDepartmentRef;
        user?: {
            id: number;
            name: string;
            email: string;
        } | null;
    }

    interface PaginatedEmployees {
        data: EmployeeItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        from: number | null;
        to: number | null;
        total: number;
    }

    let {
        employees,
        departments,
        subDepartments = [],
        roles,
        stats = { total: 0, active: 0, inactive: 0, with_user: 0 },
        filters,
    }: {
        employees: PaginatedEmployees;
        departments: DepartmentRef[];
        subDepartments: SubDepartmentRef[];
        roles: RoleRef[];
        stats?: EmployeeStats;
        filters: {
            search?: string;
            department_ids?: number[];
            sub_department_ids?: number[];
            department_id?: number | null;
            sub_department_id?: number | null;
            role_ids?: number[];
            role_id?: number | null;
            statuses?: string[];
            status?: string | null;
            sort?: string | null;
            direction?: 'asc' | 'desc' | null;
            per_page?: number | string | null;
        };
    } = $props();

    const defaultPerPage = 10;

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

    const initialRoleIds = filters.role_ids && filters.role_ids.length > 0
        ? filters.role_ids.map(Number)
        : filters.role_id
          ? [Number(filters.role_id)]
          : [];

    const initialStatuses = filters.statuses && filters.statuses.length > 0
        ? filters.statuses
        : filters.status
          ? [filters.status]
          : [];

    const statusOptions = [
        { id: 'active', name: 'Aktif' },
        { id: 'inactive', name: 'Nonaktif' },
    ];

    let search = $state(filters.search ?? '');
    let filterDeptIds = $state<number[]>(initialDeptIds);
    let filterSubDeptIds = $state<number[]>(initialSubDeptIds);
    let filterRoleIds = $state<number[]>(initialRoleIds);
    let filterStatuses = $state<string[]>(initialStatuses);
    let perPage = $state<number>(Number(filters.per_page) || defaultPerPage);

    let isModalOpen = $state(false);
    let isEditing = $state(false);
    let editingId = $state<number | null>(null);

    // Form states
    let formNip = $state('');
    let formName = $state('');
    let formEmail = $state('');
    let formPhone = $state('');
    let formRoleId = $state<string>('');
    let formDepartmentId = $state<string>('');
    let formSubDepartmentId = $state<string>('');
    let formGender = $state<string>('');
    let formAddress = $state('');
    let formStatus = $state<'active' | 'inactive'>('active');
    let formAvatarFile = $state<File | null>(null);
    let formAvatarPreview = $state<string | null>(null);
    let formRemoveAvatar = $state(false);
    let formCreateUser = $state(false);
    let formPassword = $state('');
    let isSubmitting = $state(false);
    let errors = $state<Record<string, string>>({});

    // Reset password modal state
    let isResetPasswordModalOpen = $state(false);
    let resetPasswordEmp = $state<EmployeeItem | null>(null);
    let resetPasswordValue = $state('password');
    let showResetPassword = $state(false);
    let isResetSubmitting = $state(false);
    let resetPasswordErrors = $state<Record<string, string>>({});

    // Filter sub-departments dynamically based on formDepartmentId
    let filteredSubDepartments = $derived(
        formDepartmentId
            ? subDepartments.filter((s) => s.department_id === Number(formDepartmentId))
            : []
    );

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

    let searchTimeout: any = null;

    function handleFilter() {
        router.get(
            '/employees',
            {
                search: search || undefined,
                department_ids: filterDeptIds.length > 0 ? filterDeptIds : undefined,
                sub_department_ids: filterSubDeptIds.length > 0 ? filterSubDeptIds : undefined,
                role_ids: filterRoleIds.length > 0 ? filterRoleIds : undefined,
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

    function onRolesChange(newRoleIds: number[]) {
        filterRoleIds = newRoleIds;
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
            '/employees',
            {
                search: search || undefined,
                department_ids: filterDeptIds.length > 0 ? filterDeptIds : undefined,
                sub_department_ids: filterSubDeptIds.length > 0 ? filterSubDeptIds : undefined,
                role_ids: filterRoleIds.length > 0 ? filterRoleIds : undefined,
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
        filterRoleIds.length +
        filterStatuses.length +
        Number(perPage !== defaultPerPage)
    );

    const selectedDepartments = $derived(
        departments.filter((d) => filterDeptIds.includes(d.id))
    );

    const selectedSubDepartments = $derived(
        subDepartments.filter((s) => filterSubDeptIds.includes(s.id))
    );

    const selectedRoles = $derived(
        roles.filter((r) => filterRoleIds.includes(r.id))
    );

    const selectedStatuses = $derived(
        statusOptions.filter((s) => filterStatuses.includes(s.id))
    );

    function clearSearch() {
        search = '';
        if (searchTimeout) clearTimeout(searchTimeout);
        handleFilter();
    }

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

    function removeRoleChip(roleId: number) {
        filterRoleIds = filterRoleIds.filter((id) => id !== roleId);
        handleFilter();
    }

    function removeStatusChip(statusId: string) {
        filterStatuses = filterStatuses.filter((id) => id !== statusId);
        handleFilter();
    }

    function removePerPageChip() {
        perPage = defaultPerPage;
        handleFilter();
    }

    function resetAllFilters() {
        search = '';
        if (searchTimeout) clearTimeout(searchTimeout);
        filterDeptIds = [];
        filterSubDeptIds = [];
        filterRoleIds = [];
        filterStatuses = [];
        perPage = defaultPerPage;
        handleFilter();
    }

    function handleAvatarChange(e: Event) {
        const target = e.target as HTMLInputElement;
        const file = target.files?.[0];
        if (file) {
            formAvatarFile = file;
            formRemoveAvatar = false;
            formAvatarPreview = URL.createObjectURL(file);
        }
    }

    function removeAvatar() {
        formAvatarFile = null;
        formAvatarPreview = null;
        formRemoveAvatar = true;
    }

    function openCreateModal() {
        isEditing = false;
        editingId = null;
        formNip = '';
        formName = '';
        formEmail = '';
        formPhone = '';
        formRoleId = roles[0]?.id ? String(roles[0].id) : '';
        formDepartmentId = departments[0]?.id ? String(departments[0].id) : '';
        formSubDepartmentId = '';
        formGender = 'L';
        formAddress = '';
        formStatus = 'active';
        formAvatarFile = null;
        formAvatarPreview = null;
        formRemoveAvatar = false;
        formCreateUser = false;
        formPassword = '';
        errors = {};
        isModalOpen = true;
    }

    function openEditModal(emp: EmployeeItem) {
        isEditing = true;
        editingId = emp.id;
        formNip = emp.nip;
        formName = emp.name;
        formEmail = emp.email ?? '';
        formPhone = emp.phone ?? '';
        formRoleId = String(emp.role_id);
        formDepartmentId = String(emp.department_id);
        formSubDepartmentId = emp.sub_department_id ? String(emp.sub_department_id) : '';
        formGender = emp.gender ?? 'L';
        formAddress = emp.address ?? '';
        formStatus = emp.status;
        formAvatarFile = null;
        formAvatarPreview = emp.avatar_url ?? null;
        formRemoveAvatar = false;
        formCreateUser = false;
        formPassword = '';
        errors = {};
        isModalOpen = true;
    }

    function onDepartmentChange(e: Event) {
        const val = (e.target as HTMLSelectElement).value;
        formDepartmentId = val;
        // Reset sub department if it doesn't belong to newly selected department
        const subs = subDepartments.filter((s) => s.department_id === Number(val));
        if (!subs.some((s) => String(s.id) === formSubDepartmentId)) {
            formSubDepartmentId = '';
        }
    }

    function submitForm(e: SubmitEvent) {
        e.preventDefault();
        isSubmitting = true;
        errors = {};

        const payload: Record<string, any> = {
            nip: formNip,
            name: formName,
            email: formEmail || null,
            phone: formPhone || null,
            role_id: Number(formRoleId),
            department_id: Number(formDepartmentId),
            sub_department_id: formSubDepartmentId ? Number(formSubDepartmentId) : null,
            gender: formGender || null,
            address: formAddress || null,
            status: formStatus,
        };

        if (formAvatarFile) {
            payload.avatar = formAvatarFile;
        }
        if (formRemoveAvatar) {
            payload.remove_avatar = true;
        }

        if (!isEditing && formCreateUser) {
            payload.create_user_account = true;
            payload.user_password = formPassword || 'password';
        }

        if (isEditing && editingId) {
            router.post(`/employees/${editingId}`, {
                ...payload,
                _method: 'put',
            }, {
                forceFormData: true,
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
            router.post('/employees', payload, {
                forceFormData: true,
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

    function deleteEmployee(emp: EmployeeItem) {
        if (confirm(`Apakah Anda yakin ingin menghapus data pegawai "${emp.name}" (NIP: ${emp.nip})?`)) {
            router.delete(`/employees/${emp.id}`, {
                preserveScroll: true,
            });
        }
    }

    function openResetPasswordModal(emp: EmployeeItem) {
        resetPasswordEmp = emp;
        resetPasswordValue = 'password';
        showResetPassword = false;
        resetPasswordErrors = {};
        isResetPasswordModalOpen = true;
    }

    function submitResetPassword(e: SubmitEvent) {
        e.preventDefault();
        if (!resetPasswordEmp) return;

        isResetSubmitting = true;
        resetPasswordErrors = {};

        router.post(
            `/employees/${resetPasswordEmp.id}/reset-password`,
            {
                password: resetPasswordValue,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    isResetPasswordModalOpen = false;
                    resetPasswordEmp = null;
                    resetPasswordValue = 'password';
                },
                onError: (err) => {
                    resetPasswordErrors = err;
                },
                onFinish: () => {
                    isResetSubmitting = false;
                },
            }
        );
    }

    const columns = $derived<ColumnDef<any, EmployeeItem>[]>([
        {
            id: 'index',
            header: '#',
            enableSorting: false,
            meta: { align: 'center', headerClass: 'w-12 text-center', class: 'text-center font-medium text-muted-foreground' },
            cell: (info) => renderSnippet(indexCell, { row: info.row }),
        },
        {
            accessorKey: 'name',
            header: 'Pegawai',
            enableSorting: true,
            meta: { headerClass: 'min-w-[220px]' },
            cell: (info) => renderSnippet(employeeCell, { row: info.row }),
        },
        {
            accessorKey: 'nip',
            header: 'NIP',
            enableSorting: true,
            meta: { headerClass: 'w-44', class: 'whitespace-nowrap' },
            cell: (info) => renderSnippet(nipCell, { row: info.row }),
        },
        {
            accessorFn: (row) => row.department?.name ?? '',
            id: 'department',
            header: 'Bagian & Sub Bagian',
            enableSorting: true,
            meta: { headerClass: 'min-w-[200px]' },
            cell: (info) => renderSnippet(deptCell, { row: info.row }),
        },
        {
            accessorFn: (row) => row.role?.name ?? '',
            id: 'role',
            header: 'Role',
            enableSorting: true,
            meta: { headerClass: 'min-w-[130px]' },
            cell: (info) => renderSnippet(roleCell, { row: info.row }),
        },
        {
            id: 'contact',
            header: 'Kontak',
            enableSorting: false,
            meta: { headerClass: 'min-w-[180px]' },
            cell: (info) => renderSnippet(contactCell, { row: info.row }),
        },
        {
            accessorKey: 'status',
            header: 'Status',
            enableSorting: true,
            meta: { align: 'center', headerClass: 'w-28 text-center', class: 'text-center' },
            cell: (info) => renderSnippet(statusCell, { row: info.row }),
        },
        {
            id: 'actions',
            header: 'Aksi',
            enableSorting: false,
            meta: { align: 'right', headerClass: 'w-28 text-right', class: 'text-right' },
            cell: (info) => renderSnippet(actionsCell, { row: info.row }),
        },
    ]);
</script>

<AppHead title="Data Pegawai" />

{#snippet indexCell({ row }: { row: any })}
    <span class="font-medium text-muted-foreground/70">
        {(employees.from ?? 1) + row.index}
    </span>
{/snippet}

{#snippet employeeCell({ row }: { row: any })}
    {@const emp = row.original as EmployeeItem}
    <div class="flex items-center gap-3">
        {#if emp.avatar_url}
            <img
                src={emp.avatar_url}
                alt={emp.name}
                class="size-9 shrink-0 rounded-xl object-cover border border-border shadow-2xs"
            />
        {:else}
            <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary font-bold text-xs border border-primary/20 shadow-2xs">
                {getInitials(emp.name)}
            </div>
        {/if}
        <div class="min-w-0">
            <div class="font-semibold text-foreground text-sm truncate max-w-[200px]" title={emp.name}>
                {emp.name}
            </div>
            <div class="text-xs text-muted-foreground flex items-center gap-2 mt-0.5">
                <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-medium bg-muted border border-border/60">
                    {emp.gender === 'L' ? 'Laki-laki' : emp.gender === 'P' ? 'Perempuan' : '-'}
                </span>
                {#if emp.user}
                    <span class="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-600 dark:text-emerald-400">
                        <span class="size-1.5 rounded-full bg-emerald-500"></span>
                        Akun Aktif
                    </span>
                {:else}
                    <span class="text-[11px] text-muted-foreground/60">
                        Belum ada akun
                    </span>
                {/if}
            </div>
        </div>
    </div>
{/snippet}

{#snippet nipCell({ row }: { row: any })}
    {@const emp = row.original as EmployeeItem}
    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-mono font-medium bg-muted/60 text-foreground border border-border/80">
        {emp.nip}
    </span>
{/snippet}

{#snippet deptCell({ row }: { row: any })}
    {@const emp = row.original as EmployeeItem}
    <div class="flex flex-col gap-0.5">
        <div class="inline-flex items-center gap-1.5 text-xs font-medium text-foreground">
            <Building2 class="size-3.5 text-indigo-500 shrink-0" />
            <span class="truncate max-w-[180px]" title={emp.department?.name}>{emp.department?.name ?? '-'}</span>
        </div>
        {#if emp.sub_department}
            <div class="inline-flex items-center gap-1 text-[11px] text-muted-foreground pl-3.5">
                <GitFork class="size-2.5 text-purple-500 shrink-0" />
                <span class="truncate max-w-[160px]" title={emp.sub_department.name}>{emp.sub_department.name}</span>
            </div>
        {/if}
    </div>
{/snippet}

{#snippet roleCell({ row }: { row: any })}
    {@const emp = row.original as EmployeeItem}
    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
        <span class="size-1.5 rounded-full bg-amber-500"></span>
        {emp.role?.name ?? '-'}
    </span>
{/snippet}

{#snippet contactCell({ row }: { row: any })}
    {@const emp = row.original as EmployeeItem}
    <div class="space-y-0.5 text-xs text-muted-foreground">
        {#if emp.email}
            <div class="flex items-center gap-1.5">
                <Mail class="size-3 shrink-0 text-muted-foreground/70" />
                <span class="truncate max-w-[150px]" title={emp.email}>{emp.email}</span>
            </div>
        {/if}
        {#if emp.phone}
            <div class="flex items-center gap-1.5">
                <Phone class="size-3 shrink-0 text-muted-foreground/70" />
                <span>{emp.phone}</span>
            </div>
        {/if}
        {#if !emp.email && !emp.phone}
            <span class="text-muted-foreground/50">-</span>
        {/if}
    </div>
{/snippet}

{#snippet statusCell({ row }: { row: any })}
    {@const emp = row.original as EmployeeItem}
    {#if emp.status === 'active'}
        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
            <span class="size-1.5 rounded-full bg-emerald-500"></span>
            Aktif
        </span>
    {:else}
        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700">
            <span class="size-1.5 rounded-full bg-zinc-400"></span>
            Nonaktif
        </span>
    {/if}
{/snippet}

{#snippet actionsCell({ row }: { row: any })}
    {@const emp = row.original as EmployeeItem}
    <div class="flex items-center justify-end gap-1">
        <Button
            variant="ghost"
            size="icon"
            class="size-8 rounded-lg text-amber-600 dark:text-amber-400 hover:bg-amber-500/10 cursor-pointer"
            onclick={() => openResetPasswordModal(emp)}
            title="Reset Password Pegawai"
        >
            <KeyRound class="size-4" />
        </Button>
        <Button
            variant="ghost"
            size="icon"
            class="size-8 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted cursor-pointer"
            onclick={() => openEditModal(emp)}
            title="Edit Data Pegawai"
        >
            <Pencil class="size-4" />
        </Button>
        <Button
            variant="ghost"
            size="icon"
            class="size-8 rounded-lg text-destructive hover:bg-destructive/10 cursor-pointer"
            onclick={() => deleteEmployee(emp)}
            title="Hapus Pegawai"
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
                <div class="flex size-9 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                    <Users class="size-5" />
                </div>
                <h1 class="text-2xl font-bold tracking-tight">Data Pegawai</h1>
            </div>
            <p class="text-sm text-muted-foreground mt-1">
                Kelola informasi pegawai, NIP, Bagian, Sub Bagian, dan Role penugasan.
            </p>
        </div>

        <Button onclick={openCreateModal} class="flex items-center gap-2 shadow-sm">
            <Plus class="size-4" />
            <span>Tambah Pegawai</span>
        </Button>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-xl border bg-card p-4 text-card-foreground shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-muted-foreground">Total Pegawai</p>
                <p class="text-2xl font-bold text-foreground mt-1">{stats.total}</p>
            </div>
            <div class="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <Users class="size-5" />
            </div>
        </div>

        <div class="rounded-xl border bg-card p-4 text-card-foreground shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-muted-foreground">Pegawai Aktif</p>
                <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{stats.active}</p>
            </div>
            <div class="flex size-9 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                <UserCheck class="size-5" />
            </div>
        </div>

        <div class="rounded-xl border bg-card p-4 text-card-foreground shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-muted-foreground">Akun Login</p>
                <p class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">{stats.with_user}</p>
            </div>
            <div class="flex size-9 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400">
                <ShieldCheck class="size-5" />
            </div>
        </div>

        <div class="rounded-xl border bg-card p-4 text-card-foreground shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-muted-foreground">Unit Bagian</p>
                <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{departments.length}</p>
            </div>
            <div class="flex size-9 items-center justify-center rounded-lg bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                <Building2 class="size-5" />
            </div>
        </div>
    </div>

    <!-- Professional Filter Card -->
    <div class="rounded-xl border border-border/80 bg-card p-4 shadow-xs space-y-3.5">
        <!-- Top bar: Title & reset -->
        <div class="flex flex-wrap items-center justify-between gap-2 pb-2.5 border-b border-border/60">
            <div class="flex items-center gap-2">
                <div class="flex size-7 items-center justify-center rounded-md bg-primary/10 text-primary">
                    <SlidersHorizontal class="size-3.5" />
                </div>
                <span class="text-sm font-semibold text-foreground">Filter & Pencarian Pegawai</span>
                {#if activeFiltersCount > 0}
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-primary text-primary-foreground">
                        {activeFiltersCount} Aktif
                    </span>
                {/if}
            </div>

            {#if activeFiltersCount > 0}
                <button
                    type="button"
                    onclick={resetAllFilters}
                    class="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground hover:text-destructive transition-colors cursor-pointer"
                >
                    <RotateCcw class="size-3" />
                    <span>Reset Semua Filter</span>
                </button>
            {/if}
        </div>

        <!-- Filter Controls Row -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
            <!-- Search Input -->
            <div class="{filterDeptIds.length > 0 ? 'sm:col-span-2 lg:col-span-4' : 'sm:col-span-2 lg:col-span-6'} relative">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground pointer-events-none" />
                <Input
                    type="text"
                    placeholder="Cari NIP, nama pegawai, email, telepon..."
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

            <!-- Bagian Filter (Multi-Select) -->
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

            <!-- Sub Bagian Filter (Multi-Select) - Hanya muncul jika bagian dipilih -->
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

            <!-- Role Filter (Multi-Select) -->
            <div class="lg:col-span-2 relative">
                <MultiSelectFilter
                    title="Role"
                    placeholder="Semua Role"
                    items={roles}
                    bind:selected={filterRoleIds}
                    icon={Briefcase}
                    iconColor="text-amber-500"
                    onchange={onRolesChange}
                />
            </div>

            <!-- Status Filter (Multi-Select) -->
            <div class="lg:col-span-2 relative">
                <MultiSelectFilter
                    title="Status"
                    placeholder="Semua Status"
                    items={statusOptions}
                    bind:selected={filterStatuses}
                    icon={UserCheck}
                    iconColor="text-emerald-500"
                    onchange={onStatusesChange}
                />
            </div>
        </div>

        <!-- Active Filter Pills/Chips -->
        {#if activeFiltersCount > 0}
            <div class="flex flex-wrap items-center gap-1.5 pt-2 border-t border-border/60 text-xs">
                <span class="text-muted-foreground text-[11px] mr-1">Filter Aktif:</span>

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

                {#each selectedRoles as role (role.id)}
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 text-xs font-medium">
                        <Briefcase class="size-3" />
                        <span>Role: {role.name}</span>
                        <button type="button" onclick={() => removeRoleChip(role.id)} class="ml-0.5 hover:text-destructive cursor-pointer">
                            <X class="size-3" />
                        </button>
                    </span>
                {/each}

                {#each selectedStatuses as st (st.id)}
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-xs font-medium">
                        <UserCheck class="size-3" />
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

    <!-- Table with TanStack Table (Server-side Sorting & Pagination) -->
    <DataTable
        data={employees.data}
        {columns}
        manualSorting={true}
        sortField={filters.sort}
        sortDirection={filters.direction}
        onSortChange={handleSort}
        emptyMessage="Tidak ada data Pegawai yang sesuai filter."
    >
        <Pagination
            links={employees.links}
            from={employees.from}
            to={employees.to}
            total={employees.total}
            {perPage}
            {onPerPageChange}
        />
    </DataTable>
</div>

<!-- Modal Dialog Tambah / Edit Pegawai -->
<Dialog bind:open={isModalOpen}>
    <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
        <DialogTitle>{isEditing ? 'Edit Data Pegawai' : 'Tambah Pegawai Baru'}</DialogTitle>
        <DialogDescription>
            {isEditing
                ? 'Perbarui profil dan penugasan bagian pegawai.'
                : 'Lengkapi data pegawai termasuk NIP, Bagian, Sub Bagian, dan Role.'}
        </DialogDescription>

        <form onsubmit={submitForm} class="space-y-4 mt-2">
            <!-- Upload Foto Profil (Opsional) -->
            <div class="flex flex-col sm:flex-row items-center gap-4 p-4 rounded-xl border bg-muted/20">
                <div class="relative size-20 shrink-0 rounded-2xl overflow-hidden border-2 border-border shadow-xs bg-muted flex items-center justify-center">
                    {#if formAvatarPreview}
                        <img src={formAvatarPreview} alt="Preview Foto" class="size-full object-cover" />
                    {:else}
                        <div class="size-full flex flex-col items-center justify-center bg-primary/10 text-primary font-bold text-lg">
                            {#if formName}
                                {getInitials(formName)}
                            {:else}
                                <Camera class="size-7 text-muted-foreground" />
                            {/if}
                        </div>
                    {/if}
                </div>

                <div class="flex-1 space-y-1.5 text-center sm:text-left">
                    <Label class="text-sm font-semibold">Foto Profil Pegawai <span class="text-xs font-normal text-muted-foreground">(Opsional)</span></Label>
                    <p class="text-xs text-muted-foreground">
                        Format JPG, PNG, GIF, WEBP (maks. 2MB). Foto akan dikonversi otomatis ke format WebP terkompresi.
                    </p>

                    <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2 pt-1">
                        <label class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border bg-background hover:bg-muted text-xs font-medium text-foreground transition-colors shadow-xs">
                            <Upload class="size-3.5" />
                            <span>{formAvatarPreview ? 'Ganti Foto' : 'Unggah Foto'}</span>
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp,image/gif"
                                class="hidden"
                                onchange={handleAvatarChange}
                            />
                        </label>

                        {#if formAvatarPreview}
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-8 text-xs text-destructive hover:bg-destructive/10"
                                onclick={removeAvatar}
                            >
                                <X class="size-3.5 mr-1" />
                                Hapus Foto
                            </Button>
                        {/if}
                    </div>
                    {#if errors.avatar}
                        <p class="text-xs text-destructive mt-1">{errors.avatar}</p>
                    {/if}
                </div>
            </div>

            <!-- NIP & Nama -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-2">
                    <Label for="emp-nip">NIP (Nomor Induk Pegawai) <span class="text-destructive">*</span></Label>
                    <Input
                        id="emp-nip"
                        placeholder="Contoh: 199001012015011001"
                        bind:value={formNip}
                        required
                    />
                    {#if errors.nip}
                        <p class="text-xs text-destructive">{errors.nip}</p>
                    {/if}
                </div>

                <div class="space-y-2">
                    <Label for="emp-name">Nama Lengkap Pegawai <span class="text-destructive">*</span></Label>
                    <Input
                        id="emp-name"
                        placeholder="Contoh: Budi Santoso, S.Kom"
                        bind:value={formName}
                        required
                    />
                    {#if errors.name}
                        <p class="text-xs text-destructive">{errors.name}</p>
                    {/if}
                </div>
            </div>

            <!-- Bagian & Sub Bagian (Hierarki Dinamis) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-muted/20 p-3 rounded-lg border">
                <div class="space-y-2">
                    <Label for="emp-dept">Bagian (Departemen) <span class="text-destructive">*</span></Label>
                    <select
                        id="emp-dept"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:bg-muted disabled:text-muted-foreground disabled:cursor-not-allowed"
                        value={formDepartmentId}
                        onchange={onDepartmentChange}
                        disabled={departments.length === 1}
                        required
                    >
                        <option value="" disabled>-- Pilih Bagian --</option>
                        {#each departments as dept}
                            <option value={String(dept.id)}>
                                {dept.name} {dept.code ? `(${dept.code})` : ''}
                            </option>
                        {/each}
                    </select>
                    {#if departments.length === 1}
                        <p class="text-[11px] text-muted-foreground">Bagian dikunci otomatis sesuai unit kerja Anda.</p>
                    {/if}
                    {#if errors.department_id}
                        <p class="text-xs text-destructive">{errors.department_id}</p>
                    {/if}
                </div>

                <div class="space-y-2">
                    <Label for="emp-sub-dept">Sub Bagian (Unit Kerja)</Label>
                    <select
                        id="emp-sub-dept"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        bind:value={formSubDepartmentId}
                        disabled={!formDepartmentId || filteredSubDepartments.length === 0}
                    >
                        <option value="">
                            {!formDepartmentId
                                ? '-- Pilih Bagian Terlebih Dahulu --'
                                : filteredSubDepartments.length === 0
                                ? '-- Tidak Ada Sub Bagian --'
                                : '-- Pilih Sub Bagian (Opsional) --'}
                        </option>
                        {#each filteredSubDepartments as sub}
                            <option value={String(sub.id)}>
                                {sub.name} {sub.code ? `(${sub.code})` : ''}
                            </option>
                        {/each}
                    </select>
                    {#if errors.sub_department_id}
                        <p class="text-xs text-destructive">{errors.sub_department_id}</p>
                    {/if}
                </div>
            </div>

            <!-- Role & Status & Gender -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="space-y-2">
                    <Label for="emp-role">Role Akses <span class="text-destructive">*</span></Label>
                    <select
                        id="emp-role"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        bind:value={formRoleId}
                        required
                    >
                        <option value="" disabled>-- Pilih Role --</option>
                        {#each roles as role}
                            <option value={String(role.id)}>{role.name}</option>
                        {/each}
                    </select>
                    {#if errors.role_id}
                        <p class="text-xs text-destructive">{errors.role_id}</p>
                    {/if}
                </div>

                <div class="space-y-2">
                    <Label for="emp-gender">Jenis Kelamin</Label>
                    <select
                        id="emp-gender"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        bind:value={formGender}
                    >
                        <option value="L">Laki-laki</option>
                        <option value="P">Perempuan</option>
                    </select>
                </div>

                <div class="space-y-2">
                    <Label for="emp-status">Status Pegawai <span class="text-destructive">*</span></Label>
                    <select
                        id="emp-status"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        bind:value={formStatus}
                        required
                    >
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                    </select>
                </div>
            </div>

            <!-- Email & Telepon -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-2">
                    <Label for="emp-email">Email</Label>
                    <Input
                        id="emp-email"
                        type="email"
                        placeholder="pegawai@instansi.go.id"
                        bind:value={formEmail}
                    />
                    {#if errors.email}
                        <p class="text-xs text-destructive">{errors.email}</p>
                    {/if}
                </div>

                <div class="space-y-2">
                    <Label for="emp-phone">Nomor Telepon / WhatsApp</Label>
                    <Input
                        id="emp-phone"
                        type="tel"
                        placeholder="08123456789"
                        bind:value={formPhone}
                    />
                    {#if errors.phone}
                        <p class="text-xs text-destructive">{errors.phone}</p>
                    {/if}
                </div>
            </div>

            <!-- Alamat -->
            <div class="space-y-2">
                <Label for="emp-address">Alamat Tempat Tinggal</Label>
                <textarea
                    id="emp-address"
                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-ring"
                    rows="2"
                    placeholder="Alamat domisili..."
                    bind:value={formAddress}
                ></textarea>
                {#if errors.address}
                    <p class="text-xs text-destructive">{errors.address}</p>
                {/if}
            </div>

            <!-- Akun Pengguna / User Login (hanya saat tambah baru) -->
            {#if !isEditing}
                <div class="rounded-lg border p-4 bg-muted/10 space-y-3">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            class="size-4 rounded border-gray-300 text-primary focus:ring-primary"
                            bind:checked={formCreateUser}
                        />
                        <span class="text-sm font-medium">Buatkan Akun Login Pengguna untuk Pegawai ini</span>
                    </label>

                    {#if formCreateUser}
                        <div class="space-y-2 pl-6">
                            <Label for="emp-pwd">Password Login (Default: 'password')</Label>
                            <Input
                                id="emp-pwd"
                                type="password"
                                placeholder="Masukkan password (min 8 karakter)"
                                bind:value={formPassword}
                            />
                            <p class="text-xs text-muted-foreground">
                                Pegawai dapat login menggunakan email di atas dan password ini untuk melakukan presensi.
                            </p>
                        </div>
                    {/if}
                </div>
            {/if}

            <DialogFooter class="mt-6 flex justify-end gap-2">
                <DialogClose>
                    <Button type="button" variant="outline">Batal</Button>
                </DialogClose>
                <Button type="submit" disabled={isSubmitting}>
                    {isSubmitting ? 'Menyimpan...' : isEditing ? 'Perbarui Data' : 'Simpan Pegawai'}
                </Button>
            </DialogFooter>
        </form>
    </DialogContent>
</Dialog>

<!-- Modal Dialog Reset Password Pegawai -->
<Dialog bind:open={isResetPasswordModalOpen}>
    <DialogContent class="sm:max-w-md">
        <DialogTitle class="flex items-center gap-2">
            <div class="flex size-8 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400">
                <KeyRound class="size-4" />
            </div>
            <span>Reset Password Pegawai</span>
        </DialogTitle>
        <DialogDescription>
            Atur ulang kata sandi login untuk pegawai ini.
        </DialogDescription>

        {#if resetPasswordEmp}
            <form onsubmit={submitResetPassword} class="space-y-4 mt-2">
                <!-- Info Pegawai Card -->
                <div class="rounded-lg border bg-muted/20 p-3 text-sm space-y-1">
                    <div class="font-medium text-foreground">{resetPasswordEmp.name}</div>
                    <div class="text-xs text-muted-foreground flex flex-wrap gap-x-3 gap-y-1">
                        <span>NIP: <span class="font-mono text-foreground">{resetPasswordEmp.nip}</span></span>
                        {#if resetPasswordEmp.email}
                            <span>Email: <span class="font-medium text-foreground">{resetPasswordEmp.email}</span></span>
                        {:else}
                            <span class="text-destructive font-medium">Belum ada email</span>
                        {/if}
                    </div>
                </div>

                {#if !resetPasswordEmp.email && !resetPasswordEmp.user}
                    <div class="rounded-md bg-destructive/10 p-3 text-xs text-destructive">
                        Pegawai ini belum memiliki alamat email. Silakan edit data pegawai dan tambahkan email terlebih dahulu agar akun pengguna dapat dibuat.
                    </div>
                {/if}

                <div class="space-y-2">
                    <Label for="reset-new-password">Kata Sandi Baru <span class="text-destructive">*</span></Label>
                    <div class="relative">
                        <Input
                            id="reset-new-password"
                            type={showResetPassword ? 'text' : 'password'}
                            placeholder="Minimal 8 karakter"
                            bind:value={resetPasswordValue}
                            class="pr-10"
                            required
                            minlength={8}
                        />
                        <button
                            type="button"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground focus:outline-none"
                            onclick={() => (showResetPassword = !showResetPassword)}
                            tabindex="-1"
                        >
                            {#if showResetPassword}
                                <EyeOff class="size-4" />
                            {:else}
                                <Eye class="size-4" />
                            {/if}
                        </button>
                    </div>
                    {#if resetPasswordErrors.password}
                        <p class="text-xs text-destructive">{resetPasswordErrors.password}</p>
                    {/if}
                    <div class="flex items-center justify-between text-xs text-muted-foreground pt-1">
                        <span>Minimal 8 karakter</span>
                        <button
                            type="button"
                            class="text-xs text-primary hover:underline font-medium"
                            onclick={() => (resetPasswordValue = 'password')}
                        >
                            Gunakan default ("password")
                        </button>
                    </div>
                </div>

                <DialogFooter class="mt-6 flex justify-end gap-2">
                    <DialogClose>
                        <Button type="button" variant="outline">Batal</Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        disabled={isResetSubmitting || (!resetPasswordEmp.email && !resetPasswordEmp.user)}
                        class="bg-amber-600 hover:bg-amber-700 text-white"
                    >
                        {isResetSubmitting ? 'Menyimpan...' : 'Reset Kata Sandi'}
                    </Button>
                </DialogFooter>
            </form>
        {/if}
    </DialogContent>
</Dialog>

