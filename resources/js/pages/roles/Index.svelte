<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: '/dashboard',
            },
            {
                title: 'Role & Hak Akses',
                href: '/roles',
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Pencil from '@lucide/svelte/icons/pencil';
    import Plus from '@lucide/svelte/icons/plus';
    import Search from '@lucide/svelte/icons/search';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import AppHead from '@/components/AppHead.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import { Button } from '@/components/ui/button';
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

    interface RoleItem {
        id: number;
        name: string;
        slug: string;
        description: string | null;
        employees_count: number;
    }

    interface PaginatedRoles {
        data: RoleItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        from: number | null;
        to: number | null;
        total: number;
    }

    let {
        roles,
        filters,
    }: {
        roles: PaginatedRoles;
        filters: { search?: string };
    } = $props();

    let search = $state(filters.search ?? '');
    let isModalOpen = $state(false);
    let isEditing = $state(false);
    let editingId = $state<number | null>(null);

    let formName = $state('');
    let formSlug = $state('');
    let formDescription = $state('');
    let isSubmitting = $state(false);
    let errors = $state<Record<string, string>>({});

    function handleSearch() {
        router.get(
            '/roles',
            { search: search || undefined },
            { preserveState: true, replace: true }
        );
    }

    function generateSlug(text: string): string {
        return text
            .toLowerCase()
            .replace(/[^\w ]+/g, '')
            .replace(/ +/g, '-');
    }

    function handleNameInput(e: Event) {
        const val = (e.target as HTMLInputElement).value;
        formName = val;
        if (!isEditing) {
            formSlug = generateSlug(val);
        }
    }

    function openCreateModal() {
        isEditing = false;
        editingId = null;
        formName = '';
        formSlug = '';
        formDescription = '';
        errors = {};
        isModalOpen = true;
    }

    function openEditModal(role: RoleItem) {
        isEditing = true;
        editingId = role.id;
        formName = role.name;
        formSlug = role.slug;
        formDescription = role.description ?? '';
        errors = {};
        isModalOpen = true;
    }

    function submitForm(e: SubmitEvent) {
        e.preventDefault();
        isSubmitting = true;
        errors = {};

        const payload = {
            name: formName,
            slug: formSlug,
            description: formDescription || null,
        };

        if (isEditing && editingId) {
            router.put(`/roles/${editingId}`, payload, {
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
            router.post('/roles', payload, {
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

    function deleteRole(role: RoleItem) {
        if (role.employees_count > 0) {
            alert('Role tidak dapat dihapus karena masih digunakan oleh data pegawai.');
            return;
        }

        if (confirm(`Apakah Anda yakin ingin menghapus Role "${role.name}"?`)) {
            router.delete(`/roles/${role.id}`, {
                preserveScroll: true,
            });
        }
    }
</script>

<AppHead title="Role & Hak Akses" />

<div class="flex flex-col gap-6 p-4 md:p-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <div class="flex size-9 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400">
                    <ShieldCheck class="size-5" />
                </div>
                <h1 class="text-2xl font-bold tracking-tight">Role & Hak Akses</h1>
            </div>
            <p class="text-sm text-muted-foreground mt-1">
                Kelola tingkatan peran pengguna dan pegawai pada sistem kehadiran.
            </p>
        </div>

        <Button onclick={openCreateModal} class="flex items-center gap-2">
            <Plus class="size-4" />
            <span>Tambah Role</span>
        </Button>
    </div>

    <!-- Toolbar: Search -->
    <div class="flex flex-col sm:flex-row items-center gap-3 bg-muted/30 p-3.5 rounded-xl border">
        <div class="relative w-full sm:w-80">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
            <Input
                type="search"
                placeholder="Cari nama role, slug..."
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

    <!-- Table -->
    <div class="rounded-xl border bg-card text-card-foreground shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-muted/40 font-semibold text-xs text-muted-foreground">
                    <tr>
                        <th class="p-4 w-12 text-center">#</th>
                        <th class="p-4">Nama Role</th>
                        <th class="p-4 w-40">Slug Identifier</th>
                        <th class="p-4">Deskripsi</th>
                        <th class="p-4 w-36 text-center">Pegawai Terkait</th>
                        <th class="p-4 w-28 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    {#if roles.data.length === 0}
                        <tr>
                            <td colspan="6" class="p-8 text-center text-muted-foreground">
                                Tidak ada data Role yang ditemukan.
                            </td>
                        </tr>
                    {:else}
                        {#each roles.data as role, i (role.id)}
                            <tr class="hover:bg-muted/30 transition-colors">
                                <td class="p-4 text-center font-medium text-muted-foreground">
                                    {(roles.from ?? 1) + i}
                                </td>
                                <td class="p-4 font-semibold text-foreground">
                                    {role.name}
                                </td>
                                <td class="p-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-medium bg-muted text-muted-foreground border">
                                        {role.slug}
                                    </span>
                                </td>
                                <td class="p-4 text-muted-foreground max-w-xs truncate">
                                    {role.description || '-'}
                                </td>
                                <td class="p-4 text-center">
                                    <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                        {role.employees_count} Orang
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-8 text-muted-foreground hover:text-foreground"
                                            onclick={() => openEditModal(role)}
                                            title="Edit Role"
                                        >
                                            <Pencil class="size-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-8 text-destructive hover:bg-destructive/10"
                                            onclick={() => deleteRole(role)}
                                            title="Hapus Role"
                                        >
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        {/each}
                    {/if}
                </tbody>
            </table>
        </div>

        <Pagination
            links={roles.links}
            from={roles.from}
            to={roles.to}
            total={roles.total}
        />
    </div>
</div>

<!-- Modal Dialog Tambah / Edit Role -->
<Dialog bind:open={isModalOpen}>
    <DialogContent>
        <DialogTitle>{isEditing ? 'Edit Data Role' : 'Tambah Role Baru'}</DialogTitle>
        <DialogDescription>
            {isEditing
                ? 'Perbarui detail nama dan peruntukan role di bawah ini.'
                : 'Definisikan nama role dan identifier slug untuk pengaturan wewenang.'}
        </DialogDescription>

        <form onsubmit={submitForm} class="space-y-4 mt-2">
            <div class="space-y-2">
                <Label for="role-name">Nama Role <span class="text-destructive">*</span></Label>
                <Input
                    id="role-name"
                    placeholder="Contoh: Admin, HRD, Pegawai"
                    value={formName}
                    oninput={handleNameInput}
                    required
                />
                {#if errors.name}
                    <p class="text-xs text-destructive">{errors.name}</p>
                {/if}
            </div>

            <div class="space-y-2">
                <Label for="role-slug">Slug Identifier <span class="text-destructive">*</span></Label>
                <Input
                    id="role-slug"
                    placeholder="Contoh: admin, hrd, pegawai"
                    bind:value={formSlug}
                    required
                />
                {#if errors.slug}
                    <p class="text-xs text-destructive">{errors.slug}</p>
                {/if}
            </div>

            <div class="space-y-2">
                <Label for="role-desc">Deskripsi</Label>
                <textarea
                    id="role-desc"
                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-ring"
                    rows="3"
                    placeholder="Deskripsi kewenangan role..."
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

