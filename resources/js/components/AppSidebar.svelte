<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import Building from '@lucide/svelte/icons/building';
    import Building2 from '@lucide/svelte/icons/building-2';
    import CalendarCheck from '@lucide/svelte/icons/calendar-check';
    import Clock from '@lucide/svelte/icons/clock';
    import GitFork from '@lucide/svelte/icons/git-fork';
    import LayoutGrid from '@lucide/svelte/icons/layout-grid';
    import Users from '@lucide/svelte/icons/users';
    import type { Snippet } from 'svelte';
    import AppLogo from '@/components/AppLogo.svelte';
    import NavMain from '@/components/NavMain.svelte';
    import NavUser from '@/components/NavUser.svelte';
    import {
        Sidebar,
        SidebarContent,
        SidebarFooter,
        SidebarHeader,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar';
    import { toUrl } from '@/lib/utils';
    import { dashboard } from '@/routes';
    import type { NavItem } from '@/types';

    let {
        children,
    }: {
        children?: Snippet;
    } = $props();

    interface UserAuth {
        id: number;
        name: string;
        email: string;
        employee?: {
            id: number;
            nip: string;
            role?: {
                id: number;
                name: string;
                slug: string;
            };
            department?: {
                id: number;
                name: string;
            };
        };
    }

    const authUser = $derived(page.props.auth?.user as UserAuth | undefined);
    const roleSlug = $derived(authUser?.employee?.role?.slug ?? 'super-admin');
    const isSuperAdmin = $derived(roleSlug === 'super-admin' || !authUser?.employee);
    const isAdminBagian = $derived(roleSlug === 'admin-bagian');

    const mainNavItems = $derived<NavItem[]>([
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        ...(isSuperAdmin || isAdminBagian
            ? [
                  {
                      title: 'Data Pegawai',
                      href: '/employees',
                      icon: Users,
                  },
              ]
            : []),
        {
            title: 'Kehadiran Pegawai',
            href: '/attendances',
            icon: CalendarCheck,
        },
    ]);

    const masterNavItems: NavItem[] = [
        {
            title: 'Bagian (Departemen)',
            href: '/departments',
            icon: Building2,
        },
        {
            title: 'Sub Bagian',
            href: '/sub-departments',
            icon: GitFork,
        },
        {
            title: 'Pengaturan Jam Kerja',
            href: '/work-hours',
            icon: Clock,
        },
        {
            title: 'Profil Perusahaan',
            href: '/company-profile',
            icon: Building,
        },
    ];
</script>

<Sidebar collapsible="icon" variant="inset">
    <SidebarHeader>
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton size="lg" asChild>
                    {#snippet children(props)}
                        <Link
                            {...props}
                            href={toUrl(dashboard())}
                            class={props.class}
                        >
                            <AppLogo />
                        </Link>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
        <NavMain items={mainNavItems} label="Menu Utama" />
        {#if isSuperAdmin}
            <NavMain items={masterNavItems} label="Master Data" />
        {/if}
    </SidebarContent>

    <SidebarFooter>
        <NavUser />
    </SidebarFooter>
</Sidebar>
{@render children?.()}
