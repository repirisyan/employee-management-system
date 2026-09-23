<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: '/dashboard',
            },
            {
                title: 'Master Bagian',
                href: '/departments',
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Building2 from '@lucide/svelte/icons/building-2';
    import Pencil from '@lucide/svelte/icons/pencil';
    import Plus from '@lucide/svelte/icons/plus';
    import Search from '@lucide/svelte/icons/search';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import AppHead from '@/components/AppHead.svelte';
    import DataTable from '@/components/DataTable.svelte';
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

    interface DepartmentItem {
        id: number;
        name: string;
        code: string | null;
        description: string | null;
        sub_departments_count: number;
        employees_count: number;
    }

    interface PaginatedDepartments {
        data: DepartmentItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        from: number | null;
        to: number | null;
        total: number;
    }

    let {
        departments,
        filters,
    }: {
        departments: PaginatedDepartments;
        filters: {
            search?: string;
            sort?: string | null;
            direction?: 'asc' | 'desc' | null;
        };
    } = $props();

    let search = $state(filters.search ?? '');
    let isModalOpen = $state(false);
    let isEditing = $state(false);
    let editingId = $state<number | null>(null);

    let formName = $state('');
    let formCode = $state('');
    let formDescription = $state('');
    let isSubmitting = $state(false);
    let errors = $state<Record<string, string>>({});

    function handleSearch() {
        router.get(
            '/departments',
            {
                search: search || undefined,
                sort: filters.sort || undefined,
                direction: filters.direction || undefined,
            },
            { preserveState: true, replace: true }
        );
    }

    function handleSort(field: string, direction: 'asc' | 'desc' | null) {
        router.get(
            '/departments',
            {
                search: search || undefined,
                sort: direction ? field : undefined,
                direction: direction || undefined,
            },
            { preserveState: true, replace: true, preserveScroll: true }
        );
    }

    function openCreateModal() {
        isEditing = false;
        editingId = null;
        formName = '';
        formCode = '';
        formDescription = '';
        errors = {};
        isModalOpen = true;
    }

    function openEditModal(department: DepartmentItem) {
        isEditing = true;
        editingId = department.id;
        formName = department.name;
        formCode = department.code ?? '';
        formDescription = department.description ?? '';
        errors = {};
        isModalOpen = true;
    }

    function submitForm(e: SubmitEvent) {
        e.preventDefault();
        isSubmitting = true;
        errors = {};

        const payload = {
            name: formName,
            code: formCode || null,
            description: formDescription || null,
        };

        if (isEditing && editingId) {
            router.put(`/departments/${editingId}`, payload, {
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
            router.post('/departments', payload, {
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

    function deleteDepartment(department: DepartmentItem) {
        if (department.employees_count > 0) {
            alert('Bagian tidak dapat dihapus karena masih memiliki data pegawai terhubung.');
            return;
        }

        if (confirm(`Apakah Anda yakin ingin menghapus Bagian "${department.name}"?`)) {
            router.delete(`/departments/${department.id}`, {
                preserveScroll: true,
            });
        }
    }

    const columns = $derived<ColumnDef<any, DepartmentItem>[]>([
        {
            id: 'index',
            header: '#',
            enableSorting: false,
            meta: { align: 'center', headerClass: 'w-12 text-center', class: 'text-center font-medium text-muted-foreground' },
            cell: (info) => renderSnippet(indexCell, { row: info.row }),
        },
        {
            accessorKey: 'name',
            header: 'Nama Bagian',
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
            accessorKey: 'description',
            header: 'Deskripsi',
            enableSorting: false,
            meta: { class: 'text-muted-foreground max-w-xs truncate' },
            cell: (info) => renderSnippet(descCell, { row: info.row }),
        },
        {
            accessorKey: 'sub_departments_count',
            header: 'Sub Bagian',
            enableSorting: true,
            meta: { align: 'center', headerClass: 'w-36 text-center', class: 'text-center' },
            cell: (info) => renderSnippet(subsCell, { row: info.row }),
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

<AppHead title="Master Data Bagian" />

{#snippet indexCell({ row }: { row: any })}
    <span class="font-medium text-muted-foreground">
        {(departments.from ?? 1) + row.index}
    </span>
{/snippet}

{#snippet nameCell({ row }: { row: any })}
    {@const department = row.original as DepartmentItem}
    <span class="font-semibold text-foreground">
        {department.name}
    </span>
{/snippet}

{#snippet codeCell({ row }: { row: any })}
    {@const department = row.original as DepartmentItem}
    {#if department.code}
        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-medium bg-muted text-muted-foreground border">
            {department.code}
        </span>
    {:else}
        <span class="text-muted-foreground">-</span>
    {/if}
{/snippet}

{#snippet descCell({ row }: { row: any })}
    {@const department = row.original as DepartmentItem}
    <span class="text-muted-foreground max-w-xs truncate">
        {department.description || '-'}
    </span>
{/snippet}

{#snippet subsCell({ row }: { row: any })}
    {@const department = row.original as DepartmentItem}
    <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
        {department.sub_departments_count} Sub
    </span>
{/snippet}

{#snippet empsCell({ row }: { row: any })}
    {@const department = row.original as DepartmentItem}
    <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
        {department.employees_count} Orang
    </span>
{/snippet}

{#snippet actionsCell({ row }: { row: any })}
    {@const department = row.original as DepartmentItem}
    <div class="flex items-center justify-end gap-1">
        <Button
            variant="ghost"
            size="icon"
            class="size-8 text-muted-foreground hover:text-foreground"
            onclick={() => openEditModal(department)}
            title="Edit Bagian"
        >
            <Pencil class="size-4" />
        </Button>
        <Button
            variant="ghost"
            size="icon"
            class="size-8 text-destructive hover:bg-destructive/10"
            onclick={() => deleteDepartment(department)}
            title="Hapus Bagian"
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
                <div class="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Building2 class="size-5" />
                </div>
                <h1 class="text-2xl font-bold tracking-tight">Master Data Bagian</h1>
            </div>
            <p class="text-sm text-muted-foreground mt-1">
                Kelola struktur bagian / divisi utama pada organisasi kepegawaian.
            </p>
        </div>

        <Button onclick={openCreateModal} class="flex items-center gap-2">
            <Plus class="size-4" />
            <span>Tambah Bagian</span>
        </Button>
    </div>

    <!-- Toolbar: Search -->
    <div class="flex flex-col sm:flex-row items-center gap-3 bg-muted/30 p-3.5 rounded-xl border">
        <div class="relative w-full sm:w-80">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
            <Input
                type="search"
                placeholder="Cari nama, kode bagian..."
                class="pl-9 w-full bg-background"
                bind:value={search}
                onkeydown={(e) => e.key === 'Enter' && handleSearch()}
            />
        </div>
        <Button variant="secondary" onclick={handleSearch} class="w-full sm:w-auto">
            Cari
        </Button>
        {#if filters.search}
            <Button
                variant="ghost"
                onclick={() => {
                    search = '';
                    handleSearch();
                }}
            >
                Reset
            </Button>
        {/if}
    </div>

    <!-- Table with TanStack Table -->
    <DataTable
        data={departments.data}
        {columns}
        emptyMessage="Tidak ada data Bagian yang ditemukan."
        manualSorting={true}
        sortField={filters.sort}
        sortDirection={filters.direction}
        onSortChange={handleSort}
    >
        <Pagination
            links={departments.links}
            from={departments.from}
            to={departments.to}
            total={departments.total}
        />
    </DataTable>
</div>

<!-- Modal Dialog Tambah / Edit Bagian -->
<Dialog bind:open={isModalOpen}>
    <DialogContent>
        <DialogTitle>{isEditing ? 'Edit Data Bagian' : 'Tambah Bagian Baru'}</DialogTitle>
        <DialogDescription>
            {isEditing
                ? 'Perbarui detail informasi bagian di bawah ini.'
                : 'Isi data bagian/departemen baru untuk struktur organisasi.'}
        </DialogDescription>

        <form onsubmit={submitForm} class="space-y-4 mt-2">
            <div class="space-y-2">
                <Label for="dept-name">Nama Bagian <span class="text-destructive">*</span></Label>
                <Input
                    id="dept-name"
                    placeholder="Contoh: Teknologi Informasi"
                    bind:value={formName}
                    required
                />
                {#if errors.name}
                    <p class="text-xs text-destructive">{errors.name}</p>
                {/if}
            </div>

            <div class="space-y-2">
                <Label for="dept-code">Kode Bagian</Label>
                <Input
                    id="dept-code"
                    placeholder="Contoh: TI, SDM, KEU"
                    bind:value={formCode}
                />
                {#if errors.code}
                    <p class="text-xs text-destructive">{errors.code}</p>
                {/if}
            </div>

            <div class="space-y-2">
                <Label for="dept-desc">Deskripsi / Keterangan</Label>
                <textarea
                    id="dept-desc"
                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    rows="3"
                    placeholder="Deskripsi singkat peran atau tugas bagian ini..."
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

