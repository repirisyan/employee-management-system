<script lang="ts">
    import {
        Avatar,
        AvatarFallback,
        AvatarImage,
    } from '@/components/ui/avatar';
    import { getInitials } from '@/lib/initials';
    import type { User } from '@/types';

    let {
        user,
        showEmail = false,
    }: {
        user: User;
        showEmail?: boolean;
    } = $props();

    const avatarUrl = $derived(user.employee?.avatar_url || (user.avatar && user.avatar !== '' ? user.avatar : null));
</script>

<Avatar class="h-9 w-9 overflow-hidden rounded-lg border shadow-xs">
    {#if avatarUrl}
        <AvatarImage src={avatarUrl} alt={user.name} />
    {/if}
    <AvatarFallback class="rounded-lg bg-primary/10 text-primary font-bold text-xs">
        {getInitials(user.name)}
    </AvatarFallback>
</Avatar>

<div class="grid flex-1 text-left text-sm leading-tight">
    <span class="truncate font-semibold text-foreground">{user.name}</span>
    <span class="truncate text-[11px] text-muted-foreground flex items-center gap-1 mt-0.5">
        <span class="inline-block size-1.5 rounded-full bg-emerald-500"></span>
        <span class="truncate">{user.employee?.role?.name ?? 'Super Admin'}</span>
    </span>
</div>
