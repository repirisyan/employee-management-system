<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: '/dashboard',
            },
            {
                title: 'Master Sub Bagian',
                href: '/sub-departments',
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Building2 from '@lucide/svelte/icons/building-2';
    import GitFork from '@lucide/svelte/icons/git-fork';
    import Pencil from '@lucide/svelte/icons/pencil';
    import Plus from '@lucide/svelte/icons/plus';
    import RotateCcw from '@lucide/svelte/icons/rotate-ccw';
    import Search from '@lucide/svelte/icons/search';
    import SlidersHorizontal from '@lucide/svelte/icons/sliders-horizontal';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import X from '@lucide/svelte/icons/x';
    import AppHead from '@/components/AppHead.svelte';
    import DataTable from '@/components/DataTable.svelte';
    import MultiSelectFilter from '@/components/MultiSelectFilter.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import { Button } from '@/components/ui/button';
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

    interface SubDepartmentItem {
        id: number;
        department_id: number;
        name: string;
        code: string | null;
        description: string | null;
        department?: DepartmentRef;
        employees_count: number;
    }

    interface PaginatedSubDepartments {
        data: SubDepartmentItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        from: number | null;
        to: number | null;
        total: number;
    }

    let {
        subDepartments,
        departments,
        filters,
    }: {
        subDepartments: PaginatedSubDepartments;
        departments: DepartmentRef[];
        filters: {
            search?: string;
            department_ids?: number[];
            department_id?: number | null;
            sort?: string | null;
            direction?: 'asc' | 'desc' | null;
            per_page?: number;
        };
    } = $props();

    let search = $state(filters.search ?? '');
    let filterDeptIds = $state<number[]>(
        filters.department_ids && filters.department_ids.length > 0
            ? filters.department_ids
            : filters.department_id
              ? [filters.department_id]
              : []
    );

    const selectedDepartments = $derived(
        departments.filter((dept) => filterDeptIds.includes(dept.id))
    );

    const activeFiltersCount = $derived(
        (search ? 1 : 0) + filterDeptIds.length
    );

    let isModalOpen = $state(false);
    let isEditing = $state(false);
    let editingId = $state<number | null>(null);

    let formDepartmentId = $state<string>('');
    let formName = $state('');
    let formCode = $state('');
    let formDescription = $state('');
    let isSubmitting = $state(false);
    let errors = $state<Record<string, string>>({});
    let searchTimeout: any = null;

    function handleFilter() {
        router.get(
            '/sub-departments',
            {
                search: search || undefined,
                department_ids: filterDeptIds.length > 0 ? filterDeptIds : undefined,
                sort: filters.sort || undefined,
                direction: filters.direction || undefined,
                per_page: filters.per_page || undefined,
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

    function onDepartmentsChange(newSelected: number[]) {
        filterDeptIds = newSelected;
        handleFilter();
    }

    function removeDeptChip(id: number) {
        filterDeptIds = filterDeptIds.filter((item) => item !== id);
        handleFilter();
    }

    function clearSearch() {
        search = '';
        if (searchTimeout) clearTimeout(searchTimeout);
        handleFilter();
    }

    function resetAllFilters() {
        search = '';
        filterDeptIds = [];
        router.get(
            '/sub-departments',
            {
                sort: filters.sort || undefined,
                direction: filters.direction || undefined,
                per_page: filters.per_page || undefined,
            },
            { preserveState: true, replace: true }
        );
    }

    function handleSort(field: string, direction: 'asc' | 'desc' | null) {
        router.get(
            '/sub-departments',
            {
                search: search || undefined,
                department_ids: filterDeptIds.length > 0 ? filterDeptIds : undefined,
                sort: direction ? field : undefined,
                direction: direction || undefined,
                per_page: filters.per_page || undefined,
            },
            { preserveState: true, replace: true, preserveScroll: true }
        );
    }

    function openCreateModal() {
        isEditing = false;
        editingId = null;
        formDepartmentId = filterDeptIds.length === 1 ? String(filterDeptIds[0]) : (departments[0]?.id ? String(departments[0].id) : '');
        formName = '';
        formCode = '';
        formDescription = '';
        errors = {};
        isModalOpen = true;
    }

    function openEditModal(sub: SubDepartmentItem) {
        isEditing = true;
        editingId = sub.id;
        formDepartmentId = String(sub.department_id);
        formName = sub.name;
        formCode = sub.code ?? '';
        formDescription = sub.description ?? '';
        errors = {};
        isModalOpen = true;
    }

    function submitForm(e: SubmitEvent) {
        e.preventDefault();
        isSubmitting = true;
        errors = {};

        const payload = {
            department_id: Number(formDepartmentId),
            name: formName,
            code: formCode || null,
            description: formDescription || null,
        };

        if (isEditing && editingId) {
            router.put(`/sub-departments/${editingId}`, payload, {
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
            router.post('/sub-departments', payload, {
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

    function deleteSubDepartment(sub: SubDepartmentItem) {
        if (sub.employees_count > 0) {
            alert('Sub Bagian tidak dapat dihapus karena masih memiliki data pegawai terhubung.');
            return;
        }

        if (confirm(`Apakah Anda yakin ingin menghapus Sub Bagian "${sub.name}"?`)) {
            router.delete(`/sub-departments/${sub.id}`, {
                preserveScroll: true,
            });
        }
    }

    const columns = $derived<ColumnDef<any, SubDepartmentItem>[]>([
        {
            id: 'index',
            header: '#',
            enableSorting: false,
            meta: { align: 'center', headerClass: 'w-12 text-center', class: 'text-center font-medium text-muted-foreground' },
            cell: (info) => renderSnippet(indexCell, { row: info.row }),
        },
        {
            accessorKey: 'name',
            header: 'Nama Sub Bagian',
            enableSorting: true,
            meta: { class: 'font-semibold text-foreground' },
            cell: (info) => renderSnippet(nameCell, { row: info.row }),
        },
        {
            accessorKey: 'code',
            header: 'Kode',
            enableSorting: true,
            meta: { headerClass: 'w-32' },
            cell: (info) => renderSnippet(codeCell, { row: info.row }),
        },
        {
            accessorFn: (row) => row.department?.name ?? '',
            id: 'department',
            header: 'Bagian Induk',
            enableSorting: true,
            cell: (info) => renderSnippet(deptCell, { row: info.row }),
        },
        {
            accessorKey: 'description',
            header: 'Deskripsi',
            enableSorting: false,
            meta: { class: 'text-muted-foreground max-w-xs truncate' },
            cell: (info) => renderSnippet(descCell, { row: info.row }),
        },
        {
            accessorKey: 'employees_count',
            header: 'Jumlah Pegawai',
            enableSorting: true,
            meta: { align: 'center', headerClass: 'w-36 text-center', class: 'text-center' },
            cell: (info) => renderSnippet(empsCell, { row: info.row }),
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

<AppHead title="Master Data Sub Bagian" />

{#snippet indexCell({ row }: { row: any })}
    <span class="font-medium text-muted-foreground">
        {(subDepartments.from ?? 1) + row.index}
    </span>
{/snippet}

{#snippet nameCell({ row }: { row: any })}
    {@const sub = row.original as SubDepartmentItem}
    <span class="font-semibold text-foreground">
        {sub.name}
    </span>
{/snippet}

{#snippet codeCell({ row }: { row: any })}
    {@const sub = row.original as SubDepartmentItem}
    {#if sub.code}
        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-medium bg-muted text-muted-foreground border">
            {sub.code}
        </span>
    {:else}
        <span class="text-muted-foreground">-</span>
    {/if}
{/snippet}

{#snippet deptCell({ row }: { row: any })}
    {@const sub = row.original as SubDepartmentItem}
    {#if sub.department}
        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-50 text-purple-700 dark:bg-purple-950 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
            <Building2 class="size-3" />
            {sub.department.name}
        </span>
    {:else}
        <span class="text-muted-foreground">-</span>
    {/if}
{/snippet}

{#snippet descCell({ row }: { row: any })}
    {@const sub = row.original as SubDepartmentItem}
    <span class="text-muted-foreground max-w-xs truncate">
        {sub.description || '-'}
    </span>
{/snippet}

{#snippet empsCell({ row }: { row: any })}
    {@const sub = row.original as SubDepartmentItem}
    <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
        {sub.employees_count} Orang
    </span>
{/snippet}

{#snippet actionsCell({ row }: { row: any })}
    {@const sub = row.original as SubDepartmentItem}
    <div class="flex items-center justify-end gap-1">
        <Button
            variant="ghost"
            size="icon"
            class="size-8 text-muted-foreground hover:text-foreground"
            onclick={() => openEditModal(sub)}
            title="Edit Sub Bagian"
        >
            <Pencil class="size-4" />
        </Button>
        <Button
            variant="ghost"
            size="icon"
            class="size-8 text-destructive hover:bg-destructive/10"
            onclick={() => deleteSubDepartment(sub)}
            title="Hapus Sub Bagian"
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
                <div class="flex size-9 items-center justify-center rounded-lg bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                    <GitFork class="size-5" />
                </div>
                <h1 class="text-2xl font-bold tracking-tight">Master Data Sub Bagian</h1>
            </div>
            <p class="text-sm text-muted-foreground mt-1">
                Kelola sub-divisi / unit kerja spesifik di bawah Bagian induk.
            </p>
        </div>

        <Button onclick={openCreateModal} class="flex items-center gap-2">
            <Plus class="size-4" />
            <span>Tambah Sub Bagian</span>
        </Button>
    </div>

    <!-- Professional Filter Card -->
    <div class="rounded-xl border border-border/80 bg-card p-4 shadow-xs space-y-3.5">
        <!-- Top bar: Title & Reset -->
        <div class="flex flex-wrap items-center justify-between gap-2 pb-2.5 border-b border-border/60">
            <div class="flex items-center gap-2">
                <div class="flex size-7 items-center justify-center rounded-md bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                    <SlidersHorizontal class="size-3.5" />
                </div>
                <span class="text-sm font-semibold text-foreground">Filter & Pencarian Sub Bagian</span>
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
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
            <!-- Search Input -->
            <div class="relative flex-1">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                <Input
                    type="search"
                    placeholder="Cari sub bagian, kode, atau deskripsi..."
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

            <!-- Bagian Induk Multi-Select Filter -->
            <div class="w-full sm:w-72 relative">
                <MultiSelectFilter
                    title="Bagian Induk"
                    placeholder="Semua Bagian Induk"
                    items={departments}
                    bind:selected={filterDeptIds}
                    icon={Building2}
                    iconColor="text-indigo-500"
                    onchange={onDepartmentsChange}
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
            </div>
        {/if}
    </div>

    <!-- Table with TanStack Table -->
    <DataTable
        data={subDepartments.data}
        {columns}
        emptyMessage="Tidak ada data Sub Bagian yang ditemukan."
        manualSorting={true}
        sortField={filters.sort}
        sortDirection={filters.direction}
        onSortChange={handleSort}
    >
        <Pagination
            links={subDepartments.links}
            from={subDepartments.from}
            to={subDepartments.to}
            total={subDepartments.total}
            perPage={filters.per_page}
        />
    </DataTable>
</div>

<!-- Modal Dialog Tambah / Edit Sub Bagian -->
<Dialog bind:open={isModalOpen}>
    <DialogContent>
        <DialogTitle>{isEditing ? 'Edit Data Sub Bagian' : 'Tambah Sub Bagian Baru'}</DialogTitle>
        <DialogDescription>
            {isEditing
                ? 'Perbarui detail sub bagian dan bagian induk di bawah ini.'
                : 'Pilih bagian induk dan masukkan detail nama sub bagian kerja baru.'}
        </DialogDescription>

        <form onsubmit={submitForm} class="space-y-4 mt-2">
            <div class="space-y-2">
                <Label for="sub-dept-parent">Bagian Induk <span class="text-destructive">*</span></Label>
                <select
                    id="sub-dept-parent"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    bind:value={formDepartmentId}
                    required
                >
                    <option value="" disabled>-- Pilih Bagian Induk --</option>
                    {#each departments as dept}
                        <option value={String(dept.id)}>
                            {dept.name} {dept.code ? `(${dept.code})` : ''}
                        </option>
                    {/each}
                </select>
                {#if errors.department_id}
                    <p class="text-xs text-destructive">{errors.department_id}</p>
                {/if}
            </div>

            <div class="space-y-2">
                <Label for="sub-name">Nama Sub Bagian <span class="text-destructive">*</span></Label>
                <Input
                    id="sub-name"
                    placeholder="Contoh: Software Engineering, Rekrutmen"
                    bind:value={formName}
                    required
                />
                {#if errors.name}
                    <p class="text-xs text-destructive">{errors.name}</p>
                {/if}
            </div>

            <div class="space-y-2">
                <Label for="sub-code">Kode Sub Bagian</Label>
                <Input
                    id="sub-code"
                    placeholder="Contoh: TI-SE, SDM-REC"
                    bind:value={formCode}
                />
                {#if errors.code}
                    <p class="text-xs text-destructive">{errors.code}</p>
                {/if}
            </div>

            <div class="space-y-2">
                <Label for="sub-desc">Deskripsi / Keterangan</Label>
                <textarea
                    id="sub-desc"
                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-ring"
                    rows="3"
                    placeholder="Tupoksi atau catatan bidang kerja..."
                    bind:value={formDescription}
                ></textarea>
                {#if errors.description}
                    <p class="text-xs text-destructive">{errors.description}</p>
                {/if}
            </div>

            <DialogFooter class="mt-6 flex justify-end gap-2">
                <DialogClose>
                    <Button type="button" variant="outline">Batal</Button>
                </DialogClose>
                <Button type="submit" disabled={isSubmitting}>
                    {isSubmitting ? 'Menyimpan...' : isEditing ? 'Perbarui' : 'Simpan'}
                </Button>
            </DialogFooter>
        </form>
    </DialogContent>
</Dialog>

